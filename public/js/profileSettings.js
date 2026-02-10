const activeRight = $('input[type=checkbox][name="_plugin_transferticketentity_use[31_0]"]');
const bypassRight = $('input[type=checkbox][name="_plugin_transferticketentity_bypass[31_0]"]');

activeRight.on('click', function(event) {
    if (!activeRight.is(':checked')) {
        bypassRight.prop('checked', false);
    }
})

bypassRight.on('click', function(event) {
    if (bypassRight.is(':checked')) {
        activeRight.prop('checked', true);
    }
})
