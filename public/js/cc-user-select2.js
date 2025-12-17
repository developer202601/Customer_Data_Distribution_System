// Select2 initialization for user multi-select with checkboxes and select-all
$(document).ready(function() {
    function formatUser(user) {
        if (!user.id) return user.text;
        return '<span><input type="checkbox" class="select2-user-checkbox" style="margin-right:8px;" />' + user.text + '</span>';
    }

    function formatSelection(user) {
        return user.text;
    }

    $('#cc-user-select').select2({
        placeholder: 'Select users...',
        closeOnSelect: false,
        allowClear: true,
        templateResult: formatUser,
        templateSelection: formatSelection,
        width: '100%'
    });

    // Select All functionality
    $('#cc-select-all-users').on('click', function() {
        var allOptions = $('#cc-user-select option');
        if ($(this).prop('checked')) {
            allOptions.prop('selected', true);
        } else {
            allOptions.prop('selected', false);
        }
        $('#cc-user-select').trigger('change');
    });

    // Sync select-all checkbox with selection
    $('#cc-user-select').on('change', function() {
        var allOptions = $('#cc-user-select option');
        var selectedOptions = $('#cc-user-select').val() || [];
        $('#cc-select-all-users').prop('checked', selectedOptions.length === allOptions.length);
    });
});
