# Plan: Nightly OMNI API Payment Update (3:00 AM cron)

## Goal
Add a nightly cron job that runs the OMNI bill-payment refresh for **all** rows in `master_dataset_rows` at 03:00 local time, reusing the existing `OmniBillPaymentService` + the same dedup/accumulate logic currently embedded in the two assignment controllers.

## Context (verified in repo)
- Existing integration lives in `app/Services/OmniBillPaymentService.php` (GET request, `ClientKey` header from `config('omni.client_key')` / `OMNI_API_CLIENT_KEY`, 10s timeout). Used by `CallCenter\AssignmentController::userRows()` (app/Http/Controllers/CallCenter/AssignmentController.php:427-527) and `RegionalBilling\AssignmentController::userRows()` (app/Http/Controllers/RegionalBilling/AssignmentController.php:504-604).
- Model columns already exist + migration `database/migrations/2026_10_06_000001_add_omni_columns_to_master_dataset_rows.php`: `last_payment_amount`, `last_payment_date`, `outstanding_balance`, `last_api_fetched`; plus existing `payments_value`, `new_arrears_value`.
- **No scheduler is currently registered**. `bootstrap/app.php` uses `->withCommands([...])` only; there is no `app/Console/Kernel.php` and no `app/Console/Schedule.php`.
- Queue: `database` connection, named queue `exports` (job pattern: `config('queue.exports_queue', 'exports')`), single systemd worker `cdds-queue-exports.service` running `php artisan queue:work --queue=exports` (README_HOSTING.md:291). `retry_after` defaults to 90s.
- `master_dataset_rows` is 800k+ rows; existing indexes on `excluded`, `account_num`, `assigned_to`. No index on `last_api_fetched`/`product_label`.
- `.env` is gitignored; secrets are env-only. CLI/cron runs as the app user with the same `.env` (no session auth needed).

## Discrepancies to confirm (stale docs vs. shipped code)
1. **Telephone source**: shipped controllers pass `$row->product_label` as `telephoneNo`; docs (`docs/OMNI-BILL-PAYMENT-API.md`, `.kilo/plans/1790135782146-...md`) say `mobile_contact_tel`. **Decision: mirror the shipped controllers** (`product_label`) for consistency, but verify with the OMNI API owner which field the API actually expects before going live.
2. **Secret transport**: shipped code sends `ClientKey` **header** only (no `secretCode` query param); config key is `OMNI_API_CLIENT_KEY`, not `OMNI_API_SECRET_CODE` as docs state. Use `OMNI_API_CLIENT_KEY` (no code change needed; just provision the correct env var).
3. **Dedup window**: shipped controllers use 25s, not 30s as docs claim. Use 25s in any shared helper.

## Decisions (recommended defaults)
- **Cron mechanism (primary):** direct OS crontab line calling `php artisan omni:update-all` at `0 3 * * *`. Rationale: no scheduler is registered; a single nightly job needs no minute-tick `schedule:run` dependency. Alternative (Laravel scheduler) documented in §7.
- **Scope = "all records" (latest process only):** all non-excluded rows belonging to the latest `master_dataset_process` (`MasterDatasetProcess::max('id')`, mirroring `RegionalBilling\AssignmentController::deriveRegionFromRtom` at app/Http/Controllers/RegionalBilling/AssignmentController.php:88) where `account_num IS NOT NULL` and `product_label IS NOT NULL`. Historical/closed-out processes are not refreshed. *Latest = max(process_id); if a "latest confirmed/promoted process" is preferred, swap the predicate — flag if needed.*
- **Full refresh cadence (nightly 3am):** the cron invokes `omni:update-all --force` → unconditional full refresh of the latest process's eligible rows (Q3 resolved). The 24h staleness gate (`OMNI_REFRESH_INTERVAL_HOURS=24`) remains the safer default for ad-hoc/dry-run runs and is the documented fallback if a nightly full refresh can't fit the window or hits OMNI API rate limits (just drop `--force`).
- **Concurrency model:** chunk the eligible/due row IDs with `chunkById` (chunk size from `OMNI_CHUNK_SIZE`, default 100) and dispatch one queued job per chunk to the `exports` queue so the existing systemd worker consumes them. Wrap in `Bus::batch(...)` for progress tracking (requires `job_batches` table — verify).
- **Shared logic:** extract the duplicated per-row block from both controllers (`OmniBillPaymentService::refreshRow(MasterDatasetRow $row, bool $skipFreshness = false): string`) so controllers + nightly job use identical dedup + accumulate + date-guard. Refactor controllers to call it.
- **Accumulation safety (PRESERVE):** keep the existing guards — (a) skip-accumulate if API `lastPaymentDate`+`lastPaymentAmount` match stored (duplicate), (b) only accumulate when `apiLastPaymentDate > lastPaymentDate`. Never load whole table into PHP; use `chunkById`.

## Implementation task list
1. **Env** — add to prod `.env` (gitignored): `OMNI_API_URL`, `OMNI_API_CLIENT_KEY`, `OMNI_API_TIMEOUT=10`, `OMNI_API_VERIFY_SSL=true`. Add operational config: `OMNI_REFRESH_INTERVAL_HOURS=24`, `OMNI_CHUNK_SIZE=100`.
2. **Refactor + shared helper** — in `app/Services/OmniBillPaymentService.php` add `refreshRow(MasterDatasetRow $row, bool $skipFreshness = false)`: implement the exact per-row logic from the controllers (25s freshness skip, fetch, extract, duplicate check, date-guard accumulate, `outstanding_balance = new_arrears_value - payments_value`, `last_api_fetched = now()`). Return a short status string (`updated|skipped_duplicate|skipped_fresh|skipped_no_identifiers|api_error`).
3. **Refactor controllers** — CallCenter (app/Http/Controllers/CallCenter/AssignmentController.php:482-527) and RegionalBilling (app/Http/Controllers/RegionalBilling/AssignmentController.php:559-604) `userRows()` to call `$omniService->refreshRow($row)`, then build the JSON response from the row's current attributes. (Keeps behavior identical; removes duplication.) Out of scope if too risky — can be done separately; nightly job can inline the same logic.
4. **Chunk job** — create `app/Jobs/UpdateOmniPaymentsChunk.php`:
   - `implements ShouldQueue`; `use Dispatchable, InteractsWithQueue, Queueable, SerializesModels`.
   - `public $timeout = 300; public $tries = 2;` and `$this->onQueue('exports')` (mirror `ProcessMasterIngestion`).
   - `__construct(array $rowIds)`.
   - `handle()`: resolve `OmniBillPaymentService` via `app(...)`; `MasterDatasetRow::whereIn('id', $rowIds)->chunkById(50, fn($group) => ...->each(fn($row) => $svc->refreshRow($row)))`; catch per-row exceptions so one bad row doesn't fail the chunk; `Log::info('omni chunk', [...counts...])`.
5. **Artisan command** — create `app/Console/Commands/OmniUpdateAll.php`:
   - `$signature = 'omni:update-all {--dry-run} {--force}'`; `$description = 'Nightly OMNI bill-payment refresh for all eligible master_dataset_rows.'`.
   - `handle()`: guard `config('omni.client_key')` empty → fail fast. Resolve `$latestProcessId = MasterDatasetProcess::max('id')`. Build base query `where('process_id', $latestProcessId)` + `excluded=false` + `account_num is not null` + `product_label is not null`. If not `--force`, add `whereNull('last_api_fetched')->orWhere('last_api_fetched', '<', now()->subHours($interval))`.
   - `--dry-run`: report eligible count + due count (via `chunkById` counting) without dispatching.
   - Otherwise `chunkById($chunkSize)` over the query, dispatching `UpdateOmniPaymentsChunk::dispatch($chunk->pluck('id')->all())` per chunk; wrap in `Bus::batch(...)` (handle batch, allow failures) to the `exports` queue; report dispatched chunk count.
6. **Register command** — add `\App\Console\Commands\OmniUpdateAll::class` to `->withCommands([...])` in `bootstrap/app.php` (line 19-22). (No Kernel in this project.)
7. **Cron (deployment)** — on the prod VM:
   ```cron
    0 3 * * * cd /var/www/html/cdds-laravel && /usr/bin/php artisan omni:update-all --force >> /var/log/cdds/omni-update-all.log 2>&1
   ```
   Ensure log dir writable by app user. (Alternative Laravel-scheduler path: create `app/Console/Schedule.php` with `$schedule->command('omni:update-all')->cron('0 3 * * *')` and register `->withSchedule(...)` in `bootstrap/app.php`; requires the `* * * * * php artisan schedule:run` minute cron from README_HOSTING.md §10 — document that the minute cron must exist.)

## Risks & mitigations
- **Scale:** full-refresh nightly (`--force`) of ~800k (latest process) rows × API latency is infeasible synchronously → mitigated by chunk→queue (`exports`) + worker concurrency. Monitor nightly completion; if it can't finish or hits OMNI rate limits, switch the cron back to the default 24h staleness gate (drop `--force`).
- **Worker capacity:** single `cdds-queue-exports.service` process is slow for 800k rows → recommend running `--concurrency=N` workers or multiple worker processes during the 3am window (scale up then). Flag to ops.
- **Queue retry vs timeout:** `retry_after` default 90s; a 100-row chunk can exceed that on API timeouts → set chunk job `$timeout=300` and bump `DB_QUEUE_RETRY_AFTER` (>= 360) in prod `.env`, or keep `OMNI_CHUNK_SIZE` small (≈50) so a chunk typically finishes well under the retry window.
- **Double accumulation:** prevented by preserving the duplicate + date-guard checks in the shared `refreshRow`. The staleness gate only decides *whether to call the API*; it does not affect accumulation.
- **Cron vs controller coexistence:** cron writes `last_api_fetched=now()`; within 25s a page-view controller call will hit the freshness skip — no duplicate API call. Consistent.
- **Missing `job_batches` table:** if absent, fall back to dispatching chunk jobs individually without `Bus::batch` (loses progress view but still works).
- **Secrets:** cron uses repo `.env` (gitignored). Verify `OMNI_API_CLIENT_KEY` is set in prod `.env`; command fails fast if missing.

## Validation
1. `php artisan omni:update-all --dry-run` → prints eligible count + due count + interval.
2. `php artisan omni:update-all --dry-run --force` → prints total eligible count.
3. Manually run `php artisan omni:update-all` (staging) → confirm chunk jobs appear on the `exports` queue and process; confirm sample rows get updated `last_payment_amount`/`last_payment_date`/`outstanding_balance`/`payments_value` and `last_api_fetched` moves forward.
4. `php artisan test` (stub suite) still passes; `php -l` on new files.
5. (Ops) Confirm prod crontab installed and the log file is written.
6. Verify against the 3 contradictions above with the OMNI API owner / docs before full run.

## Open confirmations / ops recommendations (resolved)
- **Q1 — Telephone source:** ✅ resolved = `product_label` (mirror shipped code). Flag the API-owner verification as a pre-live checklist item.
- **Q2 — Scope:** ✅ resolved = latest process only (`MasterDatasetProcess::max('id')`). *Sub-decision: max(process_id) vs latest *confirmed/promoted* process — default to max(process_id) (matches existing RegionalBilling pattern).*
- **Q3 — Full refresh cadence:** ✅ resolved = nightly full refresh: cron runs `omni:update-all --force` (unconditional, latest process only). Fallback: if full refresh doesn't complete in the night window or hits OMNI API rate limits, drop `--force` to use the 24h staleness gate (rows still refreshed every ~24h).
- **Q4 — Worker concurrency:** ✅ settled ops recommendation (not a code blocker): the single `cdds-queue-exports.service` worker will be far too slow for an ~800k-row (latest process) nightly full refresh. Ops should run multiple `queue:work --queue=exports` worker processes (or `--concurrency=N`) during the 3am window and scale back afterward; tune `OMNI_CHUNK_SIZE` (start 50) and `DB_QUEUE_RETRY_AFTER` (>= 360) so chunk jobs finish before the 90s default re-release.

## Implementation log (done)
- `config/omni.php`: added `refresh_interval_hours`, `chunk_size`, `freshness_window_seconds` (env-overridable).
- `app/Services/OmniBillPaymentService.php`: added `FRESHNESS_WINDOW_SECONDS = 25` const + `refreshRow(MasterDatasetRow $row, bool $skipFreshness = false): string` (identical dedup/date-guard/accumulate logic previously inlined in both controllers).
- `CallCenter/AssignmentController.php` & `RegionalBilling/AssignmentController.php`: `userRows()` OMNI block replaced with `$omniService->refreshRow($row)` (behavior-preserving: response still reads pre-captured locals + fresh `last_api_fetched`).
- `app/Jobs/UpdateOmniPaymentsChunk.php`: new queued job → `exports` queue, `$timeout=300, $tries=2`, chunked `chunkById` over its row IDs, `skipFreshness=true`, per-row try/catch + summary log.
- `app/Console/Commands/OmniUpdateAll.php`: new command `omni:update-all {--dry-run} {--force}`; guards `OMNI_API_CLIENT_KEY`; scopes to latest `process_id` (`MasterDatasetProcess::max('id')`), non-excluded, eligible identifiers; dry-run counts; dispatch via `Bus::batch` (falls back to individual dispatch if `job_batches` missing).
- `bootstrap/app.php`: registered `\App\Console\Commands\OmniUpdateAll::class` in `->withCommands([...])`.

### Applied fixes from review
- **Overlap guard** in `OmniUpdateAll::handle()` (deploy safety): before dispatching, bail if an unfinished `omni:update-all` batch exists in `job_batches`; otherwise acquire a non-blocking `Cache::lock('omni:update-all', 7200)` (used when the `job_batches` fallback path is taken). Released in `finally`. (Guards against overlapping cron runs; the date-guard keeps data correct regardless.)
- **`isDuplicate` normalization** in `refreshRow` (business logic): cast `last_payment_amount` to `round((float), 2)` and convert `last_payment_date` (Carbon) to `Y-m-d` string before the `===` comparisons, so the duplicate check and date-guard compare like types. Verified the previous strict `===`/`>` was a no-op for duplicates and the date-guard worked by luck of PHP's string-cast of Carbon.

### Validated locally
- `php -l` clean on all touched files (incl. post-edit).
- `php artisan config:clear` + `php artisan list --raw | grep omni` → `omni:update-all` registered.
- `php artisan omni:update-all --dry-run` → passed the `OMNI_API_CLIENT_KEY` guard and reached `MasterDatasetProcess::max('id')`. **Only failed on DB connectivity** (local MySQL not running — environmental). Confirms bootstrap + wiring are correct.
- `php artisan test` → 2 passed.
- `vendor/bin/pint --dirty` → style fixed.

### Deployment checklist (prod, not yet applied)
1. `.env`: ensure `OMNI_API_CLIENT_KEY`, `OMNI_API_URL` set; add `OMNI_REFRESH_INTERVAL_HOURS=24`, `OMNI_CHUNK_SIZE=50`.
2. Confirm OMNI columns migration already applied (no new migration needed).
3. Run `php artisan config:cache`.
4. Confirm `job_batches` table exists (schema dump already includes it).
5. Scale `exports` queue worker(s): `queue:work --queue=exports --concurrency=N` during 3am window; single worker won't clear ~800k (latest process) rows nightly. Set `DB_QUEUE_RETRY_AFTER=360`.
6. Install crontab (app user): `0 3 * * * cd /var/www/html/cdds-laravel && /usr/bin/php artisan omni:update-all --force >> /var/log/cdds/omni-update-all.log 2>&1`.
7. Verify `OMNI_API_CLIENT_KEY` correctness + the `product_label` telephone source against the OMNI API owner before the first live run.
8. Smoke test staging: `php artisan omni:update-all --dry-run` and `--dry-run --force`, then a bounded real run, then spot-check DB rows (`last_api_fetched`, accumulation, dedup).
