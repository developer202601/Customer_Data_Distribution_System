# AGENTS.md

## What this is

Laravel 12 / PHP 8.2 credit-control operations app. A monthly master billing workbook is validated, ingested, excluded, split into assignment buckets, exported, then worked by two session-authenticated areas:

- Call Center: `/cc/*` (`app/Http/Controllers/CallCenter`)
- Regional Billing: `/rb/*` (`app/Http/Controllers/RegionalBilling`)

Most master-pipeline domain logic is in `app/Support/` (not `app/Services/`).

## Commands

- Install: `composer install` then `npm ci`
- Dev: `composer dev` (serve + queue:listen + pail + vite) or `php artisan serve` + `npm run dev`
- Build: `npm run build` (`public/build` is gitignored and required for CI/deploy)
- Migrate: `php artisan migrate` (MySQL; fast bootstrap: `php artisan migrate --schema-path=database/schema/mysql-schema.sql`)
- Test: `php artisan test`; single test: `php artisan test --filter=ExampleTest`
- Format: `vendor/bin/pint --dirty` (no Pint config; formatting is not enforced in CI)
- Python deps: `python -m pip install -r scripts/requirements-ingest.txt -r scripts/requirements-validator.txt`
- CI: `composer install` -> `npm ci` -> `npm run build` -> `pip install -r scripts/requirements-ingest.txt` -> `php artisan migrate --force` -> `php artisan test`

There is no static analysis, ESLint, JS test, or typecheck script. Tests are only Laravel stubs (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`); do not assume meaningful coverage.

`.env` is gitignored and `.env.example` is absent, despite README instructions. Use the existing `.env` locally or construct env vars as CI does.

## Master dataset pipeline

End-to-end flow:

1. Chunked upload -> `MasterDatasetUploadController` -> `MasterDatasetWorkflowService::queueMasterArchive`.
2. `MasterDatasetImporter::queue` stores the archive, extracts the workbook, and runs Polars validation (`scripts/validate_master.py`).
3. `ProcessMasterIngestion` job -> `MasterDatasetImporter::processStoredArchive` -> `scripts/ingest_master.py` inserts into `master_dataset_rows_staging`.
4. `MasterDatasetStagingPromoter` promotes staging rows to `master_dataset_rows`; `MasterDatasetExclusionService` applies exclusion ZIP workbooks.
5. Confirmation page (`ProcessConfirmController`) captures range/medium/quota overrides.
6. `ProcessAssignmentJob` -> `MasterDatasetAssignmentService::assign`, then `MasterDatasetExportCoordinator` -> `BuildMasterDatasetExports` writes bucket workbooks.

Important queue gotcha: these jobs currently dispatch to the `exports` queue (`ProcessMasterIngestion`, `ProcessExclusionUpload`, `ProcessAssignmentJob`, `BuildMasterDatasetExports`), and production deploy restarts `cdds-queue-exports.service`. Run `php artisan queue:work --queue=exports` for this flow; `composer dev` starts `queue:listen` on the default queue, so it does not consume these jobs. This conflicts with older README/`change req.txt` claims of synchronous ingestion - inspect the specific path before changing queue behavior.

Python execution is synchronous inside `PythonIngestionService::run` via Symfony Process; `PYTHON_BINARY` must point at an interpreter with both requirement sets installed. `python` on Windows, `python3` elsewhere by default. CI pins Python 3.10.

Status/progress is DB + cache driven: use `MasterDatasetProcessStatus` constants, existing `process:upload:*` / `master:ingest:*` cache keys, and `python-status.json`; do not invent status strings. `MasterDatasetProcess` status drives redirects and polling.

## Assignment + export invariants

- `assigned_to` labels are defined in `MasterDatasetAssignmentService` (`call center staff`, `call center`, `staff`, `regional billing center`, `enterprise + wholesale`, `sme`, `VIP`, `Excluded`). Other code lowercases/trims these labels, so keep exact values in sync.
- Export buckets are defined in `MasterDatasetExportCoordinator::EXPORT_BUCKETS`; file names/labels come from `MasterDatasetViewService`. Adding a bucket requires changes in both.
- Assignment defaults live in `MasterDatasetAssignmentConfiguration` (DB `configurations` table, overridable from the confirmation page), not hardcoded only in prose.
- Auto-exclusion rules are in `scripts/ingest_master.py::_evaluate_auto_exclusion`: medium must be COPPER/FTTH/LTE, `LATEST_PRODUCT_STATUS` must be `OK`, and arrears must be `> 2400`.
- `master_dataset_rows` is large (800k+ rows/month). Never load whole processes into PHP; use `chunkById`, streaming responses, and OpenSpout exports.

## Auth and roles

Custom session auth, not `Auth::user()`:

- Login stores `session('user')` with `id`, `system` (`cc`/`rb`), `assignment`, `is_admin`.
- Middleware aliases in `bootstrap/app.php`: `session.auth`, `session.cc_user`, `session.rb_user`, `session.cc_admin`.
- Call Center users are `App\Models\CallCenter\CallCenterUser`; Regional Billing/admin users are `App\Models\User`.
- Username login requires exactly 6 digits. Microsoft OAuth maps the first 6 chars of `userPrincipalName` to `users.username`.

## Storage and DB

`FILESYSTEM_DISK=local` maps to `storage/app/private`, so real paths are:

- `storage/app/private/exports/{token}/...` (exports + Python manifest/status)
- `storage/app/private/exclusions/{process_id}/...`
- `storage/app/private/validation-reports/master/{token}/...`
- `storage/app/tmp/master/...` (temporary extraction, outside the private root)

README references to `storage/app/master-artifacts` are stale.

Tests use SQLite `:memory:` (`phpunit.xml`), while production/CI use MySQL. Some migrations contain MySQL-only `information_schema` statements, and the schema dump can lag migrations; prefer `php artisan migrate` after pulling.

## Repo quirks

- `.kilo/worktrees/veil-market` is a full duplicate worktree. Never edit it or search it for the "real" source.
- Root `check_*.php`, `.tinker_*.php`, `chat_*.md`, and `change req.txt` are scratch/requirements artifacts, not application entrypoints (`chat_*` is gitignored).
- `.github/workflows/cicd.yml` is the authoritative CI/deploy pipeline; `bitbucket-pipelines.yml` is a near-duplicate legacy config.
- `ProcessFileController` is a legacy queue-based uploader that now returns a "replaced by Master Dataset workflow" error. Do not extend it.
