# Fix: Pass to Calling Units buttons not auto-updating when exports finish

## Problem
On the Assignment Overview page, the "Pass to Calling Units" buttons remain stuck showing "Waiting for exports" (disabled) until the user manually refreshes the page, even after all exports have finished generating. The download buttons update automatically via JS polling, but the pass buttons do not.

## Root Cause
- The pass button state is rendered server-side in `overview.blade.php` using the `$exportsReady` boolean computed in `AssignmentController::index()`.
- The JavaScript `pollExportStatus()` function polls `process.assignments.exports.status` every 3 seconds, but it only re-renders the **download** buttons (`[data-export-buttons]` blocks).
- There is no client-side logic to update the pass buttons when export statuses change from `processing` to `ready`.

## Fix Plan

### 1. Blade template changes (`resources/views/process/assignments/overview.blade.php`)

**A. Add a wrapper with data config around the pass buttons row**
Around the `.row.g-3` that contains the pass buttons (line 195), add:
```html
<div data-pass-buttons
     data-pass-config="{{ json_encode([
         'routes' => [
             route('process.assignments.pass-ccs', $process),
             route('process.assignments.pass-cc', $process),
             route('process.assignments.pass-s', $process),
             route('process.assignments.pass-rb', $process),
         ],
         'labels' => ['Call Center Staff', 'Call Center', 'Staff', 'Regional Billing'],
         'btnIds' => ['btn-pass-ccs', 'btn-pass-cc', 'btn-pass-s', 'btn-pass-rb'],
     ]) }}">
```

**B. Mark waiting buttons with a data attribute**
On each disabled "Waiting for exports" button (line 210-215), add `data-pass-waiting="1"`:
```html
<button type="button"
        class="btn btn-outline-secondary w-100"
        disabled
        title="All exports must be ready before passing."
        data-pass-waiting="1">
    Pass — {{ $btn['label'] }}
</button>
```

### 2. JavaScript changes (inside the existing `<script>` block in `overview.blade.php`)

**A. Add a `renderPassButton` helper**
After the existing `renderButtons` function, add:
```js
const renderPassButton = (index) => {
    const container = document.querySelector('[data-pass-buttons]');
    if (!container) return;

    const config = JSON.parse(container.dataset.passConfig || '{}');
    const route = config.routes?.[index];
    const label = config.labels?.[index];
    const btnId = config.btnIds?.[index];

    if (!route || !label) return;

    return `<form method="POST" action="${route}" onsubmit="return confirm('Pass ${label} records to calling units?');">
        @csrf
        <button type="submit" id="${btnId}" class="btn btn-primary w-100">
            Pass — ${label}
        </button>
    </form>`;
};
```

**B. Update `pollExportStatus` to also refresh pass buttons**
At the end of the `.then((payload) => { ... })` block inside `pollExportStatus`, after the export button loop, add:
```js
const requiredBuckets = ['call-center-staff', 'call-center', 'staff', 'region-billing'];
const exportsReady = requiredBuckets.every(
    (bucket) => (payload.exports?.[bucket]?.status || null) === 'ready'
);

if (exportsReady) {
    const waitingButtons = document.querySelectorAll('[data-pass-buttons] [data-pass-waiting]');
    waitingButtons.forEach((btn) => {
        const col = btn.closest('.col-12, .col-sm-6, .col-xl-3');
        if (!col) return;

        const index = Array.from(waitingButtons).indexOf(btn);
        col.innerHTML = renderPassButton(index);
    });
}
```

### 3. No controller changes required
The existing `exportStatus` endpoint already returns all bucket statuses. The JS computes `exportsReady` client-side using the same logic as the server, so no backend changes are needed.

## Validation
1. Open the Assignment Overview page while exports are still generating — pass buttons should show "Waiting for exports".
2. Wait for exports to complete (or trigger export generation).
3. Within 3 seconds of the last export reaching `ready`, the pass buttons should automatically become clickable without a page refresh.
4. Click a pass button — it should submit normally and show the "passed" state after reload.
5. If an export becomes stale and is requeued, the pass buttons should revert to disabled.

## Risk / Notes
- If a pass button was already clicked before exports were fully ready, the page would have reloaded and the button would already show the "passed" state (no `data-pass-waiting`), so JS will not touch it.
- The `renderPassButton` output includes a CSRF token via `@csrf` in the blade string. Since the JS string is inside a blade file, Blade will render the actual token.
