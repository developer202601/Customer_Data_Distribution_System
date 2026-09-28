# Fix Assignment Modal Not Opening After Close

## Problem
Modal fails to open when clicking a row after previously opening and closing the modal. Particularly happens when:
- First modal opened, then closed
- Click another row → modal doesn't appear

## Root Causes Identified

1. **Bootstrap/fallback state inconsistency** (`showModal()` / `hideModal()`):
   - `showModal()` tries `bootstrapModal.show()` first, falls back to manual `display: block` + `cc-fallback-modal` class
   - `hideModal()` only cleans up fallback mode (`display: none`, removes class) but **never calls `bootstrapModal.hide()`**
   - The `[data-bs-dismiss="modal"]` click handler calls `hideModal()` directly, bypassing Bootstrap's proper hide flow
   - After fallback use, `bootstrapModal` instance may be in inconsistent state

2. **No request deduplication**: Rapid row clicks trigger multiple simultaneous `fetchDetails()` calls, causing race conditions

3. **Modal state not reset**: After fallback mode, the modal element retains inline styles/classes that interfere with subsequent Bootstrap shows

## Affected Files
- `resources/views/callcenter/assignments/manage.blade.php`
- `resources/views/regionalbilling/assignments/manage.blade.php`
- `resources/views/callcenter/reports/index.blade.php` (similar pattern)

## Fix Plan

### 1. Add Request Deduplication Guard
Add a flag to prevent concurrent `fetchDetails()` calls:
```javascript
let isFetchingDetails = false;

btn.addEventListener('click', async () => {
    if (isFetchingDetails) return;
    isFetchingDetails = true;
    try {
        // ... fetch and show
    } finally {
        isFetchingDetails = false;
    }
});
```

### 2. Fix `hideModal()` to Properly Use Bootstrap
```javascript
function hideModal() {
    if (!assignmentRowModal) return;
    
    // If Bootstrap modal was used, let it handle hiding properly
    if (bootstrapModal && !assignmentRowModal.classList.contains('cc-fallback-modal')) {
        bootstrapModal.hide();
        return; // Bootstrap will trigger hidden.bs.modal event
    }
    
    // Fallback cleanup only
    assignmentRowModal.style.display = 'none';
    assignmentRowModal.classList.remove('cc-fallback-modal');
    const backdrop = document.getElementById('cc-fallback-backdrop');
    if (backdrop) backdrop.remove();
    // ... rest of cleanup
}
```

### 3. Fix `showModal()` to Re-initialize Bootstrap After Fallback
```javascript
function showModal() {
    if (!assignmentRowModal) return;
    
    // If we used fallback last time, re-create Bootstrap instance
    if (assignmentRowModal.classList.contains('cc-fallback-modal')) {
        assignmentRowModal.classList.remove('cc-fallback-modal');
        assignmentRowModal.style.display = '';
        if (window.bootstrap) {
            bootstrapModal = new bootstrap.Modal(assignmentRowModal, { keyboard: true });
        }
    }
    
    if (bootstrapModal) {
        try {
            bootstrapModal.show();
            return;
        } catch (e) {
            console.error('bootstrap modal show failed, falling back', e);
        }
    }
    // ... fallback display
}
```

### 4. Remove Manual `[data-bs-dismiss]` Click Handler
Let Bootstrap handle dismiss buttons natively. The `hidden.bs.modal` event listener already handles cleanup.

### 5. Reset Modal Element State on Hide
Ensure `style.display` is cleared (set to `''`) not `'none'` when using Bootstrap, so Bootstrap can manage it.

## Validation Steps
1. Open modal → close via X button → click another row → modal opens ✓
2. Open modal → close via Close button → click another row → modal opens ✓
3. Open modal → click backdrop → click another row → modal opens ✓
4. Rapid click multiple rows → only one modal opens, no errors ✓
5. Test in both Call Center and Regional Billing assignment pages

## Risk Assessment
- **Low**: Changes are isolated to modal display logic
- **No backend changes** required
- **Fallback mode preserved** for environments without Bootstrap JS