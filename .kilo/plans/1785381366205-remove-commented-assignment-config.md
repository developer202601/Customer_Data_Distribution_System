# Remove commented-out assignment config block from master-upload

## Context
In `resources/views/process/master-upload.blade.php`, lines 197–235 contain an `@if(!empty($assignmentConfig))` block whose entire body (lines 198–234) is an HTML comment. The user confirmed this segment is not required.

## Decision
Remove the dead markup and its empty conditional wrapper. Leave the second inline script (lines 959–1041) untouched because it already guards with `if (!refreshButton) { return; }` and will no-op safely when the elements are absent.

## Steps
1. Delete lines 198–234 (commented-out HTML).
2. Delete the surrounding `@if(!empty($assignmentConfig))` and `@endif` lines (197 and 235) so no empty conditional remains.
3. Verify no other template references the removed IDs (`assignment-config-block`, `assignment-config-cards`, `assignment-config-refresh`, etc.) outside of the already-identified second script block.

## Validation
- Open `resources/views/process/master-upload.blade.php` and confirm the assignment-config block and its conditional are gone.
- Run the app’s lint / blade-check command if available to ensure no template syntax errors were introduced.
