# Add "SMS Reminder" Upload Flow (identical to Payment upload flow)

## Goal
Add a new **SMS Reminder** upload flow that mirrors the existing **Payment Files Upload** flow
(`PaymentUploadController` → `PaymentIngestionService` → `scripts/read_payment_excel.py` → `master_dataset_rows.payments_value`), but:
- Input Excel columns (exact headers): **`PRODUCT_LABEL`**, **`SMS_1`**, **`SMS_2`**, **`DATASET_MONTH`**
- Mapping key: `product_label` (instead of `account_num`)
- Values written: `sms_1`, `sms_2` on `master_dataset_rows` (new columns)
- Process scoping: resolved from `DATASET_MONTH` (latest `MasterDatasetProcess`) — identical to the payment flow

## Resolved design decisions
1. **Include `DATASET_MONTH`** in the SMS Excel (per user-approved option). `PaymentIngestionService` resolves the latest `MasterDatasetProcess` whose `dataset_month` matches; updates scoped to that `process_id`.
2. **Mapping key = `product_label`** scoped to the resolved `process_id`. Within a process `product_label` is enforced unique by the master importer (`dedupe_column => PRODUCT_LABEL`) and is part of the `master_dataset_rows` unique key `(process_id, run_date_raw, product_label, account_num)`, so a single row matches per process.
3. **New columns** `sms_1` and `sms_2` on `master_dataset_rows` (nullable `string`).
4. **Store raw value, no validation/normalization**: a non-empty cell is trimmed and written verbatim to `sms_1`/`sms_2`; a blank/empty cell skips that column (row still counts as `matched`, mirroring the payment flow's blank-skip so an absent value leaves the existing DB value untouched). No yes/no coercion.
5. **Data type** = trimmed string, NOT numeric. `matched/updated/not_found` counters reused as-is.
6. **Architecture copy**: same staging-on-disk validation, `register_shutdown_function` synchronous processing, SSE progress stream, polling fallback, JSON history index, and the `SMS_REMINDER_UPLOADS`-style tracking table mirroring `payment_uploads`. No queue/job refactor (out of scope; matches current payment behavior).

## Affected / new files

### New (mirror from payment equivalents)
- `database/migrations/<ts>_add_sms_columns_to_master_dataset_rows.php` — add nullable `sms_1`, `sms_2` strings to `master_dataset_rows`.
- `database/migrations/<ts>_create_sms_reminder_uploads_table.php` — mirror `2026_07_24_000001_create_payment_uploads_table` (cols: token, user_id, original_name, status, progress, message, processed_rows, total_rows, matched, updated, not_found, error, started_at, finished_at; index `[user_id, created_at]`).
- `app/Models/SmsReminderUpload.php` — mirror `PaymentUpload`.
- `app/Support/SmsReminderIngestionService.php` — mirror `PaymentIngestionService`, calling the SMS python script and matching on `product_label` / writing `sms_1`, `sms_2`.
- `scripts/read_sms_reminder_excel.py` — mirror `read_payment_excel.py`; required headers `PRODUCT_LABEL`, `SMS_1`, `SMS_2`, `DATASET_MONTH`; emits JSON `product_label_index`, `sms_1_index`, `sms_2_index`, `dataset_month_index`, `total_rows`, `data`.
- `app/Http/Controllers/SmsReminderUploadController.php` — mirror `PaymentUploadController`; cache prefix `process:sms_reminder:upload:` and user-prefix `user:sms_reminder:uploads:`.
- `resources/views/process/sms-reminder-upload.blade.php` — mirror `payment-upload.blade.php`; form POSTs to `sms_reminders.update`; dropzone hints headers `PRODUCT_LABEL`, `SMS_1`, `SMS_2`, `DATASET_MONTH`.
- `resources/views/process/sms-reminder-upload-history.blade.php` — mirror `payment-upload-history.blade.php`.
- Dashboard card in `resources/views/dashboard.blade.php` — add a normal-user card "Upload SMS Reminders" → `sms_reminder.upload`.

### Modified
- `app/Models/MasterDatasetRow.php` — add `sms_1`, `sms_2` to `$fillable`.
- `routes/web.php` — add routes (placed beside the payment block, inside `Route::middleware('session.auth')`):
  - `GET /sms-reminder/upload` → `SmsReminderUploadController@create` (alias `sms_reminder.upload`) — returns the upload view; admins redirected like payment.
  - `POST /sms-reminders/update` → `SmsReminderUploadController@update` (`sms_reminders.update`)
  - `GET /sms-reminders` → `SmsReminderUploadController@index` (`sms_reminders.index`)
  - `GET /sms-reminders/history` → `SmsReminderUploadController@history` (`sms_reminders.history`)
  - `GET /sms-reminders/progress/{token}` → `SmsReminderUploadController@progress` (`sms_reminders.progress`)
  - `GET /sms-reminders/progress/stream/{token}` → `SmsReminderUploadController@progressStream` (`sms_reminders.progress.stream`)
- `config/services.php` `master_ingest` block — reuse the existing `python_binary`; no change required (SMS service reads the same key).

## Data flow
1. User (non-admin) clicks dashboard card → upload view.
2. `SmsReminderUploadController@update`: validate `upload|required|file|mimes:xlsx|max:51200`; stage to `storage/app/sms_reminders/staged/{token}-{safename}`; create `SmsReminderUpload` row (status `processing`, progress 1, message "Opening Excel workbook…"); seed cache state; register `SmsReminderIngestionService::run` in `register_shutdown_function`.
3. `SmsReminderIngestionService::run`: resolve python (`config('services.master_ingest.python_binary')`/`PYTHON_BINARY`), run `scripts/read_sms_reminder_excel.py <abs path>`; parse JSON; resolve latest `MasterDatasetProcess` by `dataset_month`; update progress (10%); chunk data rows by 1000.
4. `processChunk`: collect non-empty `product_label` values → `whereIn('product_label', ...)->where('process_id', $processId)` → `keyBy('product_label')`; for each Excel row, if found write `sms_1`/`sms_2` verbatim (trimmed strings) — blank cells skip the column but the row still counts `matched`; non-empty values count as `updated`; accumulate `matched/updated/not_found`; persist progress (cache + `SmsReminderUpload`) at 10%…95%; on completion set 100%/status `complete`; delete staged file.
5. Errors: any step throws → shutdown handler sets `failed`, status `failed`, progress 100, stores `error` message in cache + DB; staged file deleted in error paths.
6. UI: SSE stream `sms_reminders.progress.stream` (with `sms_reminders.progress` heartbeat/fetch fallback); `handleProgressPayload` identical to payment page but targeting SMS element IDs; history page fetches `sms_reminders.index`.

## Edge cases & failure modes (mirror payment behavior)
- Missing/incorrect headers → python exits non-zero → `invalid_response` error captured → status `failed`.
- Empty worksheet, unreadable file, non-xlsx → validation / python errors → status `failed`.
- `DATASET_MONTH` not matching any process → process_id null; rows still attempted but scoped query returns none → all `not_found` (message notes "no dataset month match").
- Blank `sms_1`/`sms_2` cell → column skipped (row still `matched`, not `updated`); existing DB value left untouched — mirrors the payment flow's blank-value skip.
- product_label duplicated *within the same process* — import enforces uniqueness; `keyBy` keeps last if duplicates exist (defensive; matches payment `keyBy('account_num')`).
- Large files: 1000-row chunks, progress persisted every chunk; python output JSON can be large for very big sheets (same constraint as payment flow; out of scope to fix).

## Rollback / migration
- Drop `sms_reminder_uploads` table and remove `sms_1`/`sms_2` columns; revert `MasterDatasetRow` fillable; delete new files. No data migration of existing rows (columns nullable, so existing rows are unaffected). New table is self-contained; deleting it loses only upload history.

## Validation plan
- `vendor/bin/pint --test` (lint) on all new PHP.
- `php artisan config:clear && php artisan test` (run suite; new feature tests below).
- `php artisan migrate:fresh --force` (verify migrations apply cleanly; columns added).
- New feature test (recommended, not blocking): `tests/Feature/SmsReminderUploadTest.php`:
  - rejects missing/invalid MIME/oversized upload (422) from `payments.update`-style endpoint;
  - builds a tiny in-memory `.xlsx` (via `PhpOffice\PhpSpreadsheet`) with the 4 headers + rows, posts to `sms_reminders.update`, then asserts `sms_reminder_uploads` reaches `complete` and `master_dataset_rows.sms_1/sms_2` updated (and `matched/updated/not_found` counts). Requires python+polars available in CI; if unavailable, mark test skipped.
- Manual smoke: upload `scripts/read_sms_reminder_excel.py` produces expected JSON for a sample sheet; SSE stream emits progress to complete.

## Out of scope
- Queue-based processing (payment flow uses `register_shutdown_function`; SMS mirrors to stay "identical").
- SMS dispatch/sending — this flow only *ingests* reminder values into `master_dataset_rows`.
- Changing the payment flow itself.

## Open questions (resolved / N/A)
- product_label uniqueness within a process → verified unique by master importer dedupe + unique key.
- Python availability → reuse existing `PYTHON_BINARY`/`services.master_ingest.python_binary` (already used by payment flow).
