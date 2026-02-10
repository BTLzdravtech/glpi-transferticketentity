function dropdownAllowTransferChange() {
    const form = $('#transferticketentity');
    form.off('change')
    const dropdownAllowTransfer = $('select[name=allow_transfer]')
    const dropdownOnlyTransfer = $('select[name=allow_entity_only_transfer]');
    const dropdownJustificationTransfer = $('select[name=justification_transfer]');
    const dropdownKeepCategory = $('select[name=keep_category]');
    const dropdownITILCategoriesId = $('select[name=itilcategories_id]');
    if (dropdownAllowTransfer.val() === '0') {
        dropdownOnlyTransfer.closest('.form-field').hide();
        dropdownOnlyTransfer.val('0');
        dropdownOnlyTransfer.trigger('change');
        dropdownJustificationTransfer.closest('.form-field').hide();
        dropdownJustificationTransfer.val('0');
        dropdownJustificationTransfer.trigger('change');
        dropdownKeepCategory.closest('.form-field').hide();
        dropdownKeepCategory.val('0');
        dropdownKeepCategory.trigger('change');
        dropdownITILCategoriesId.closest('.form-field').hide();
        dropdownITILCategoriesId.val('0');
        dropdownITILCategoriesId.trigger('change');
    } else {
        dropdownOnlyTransfer.closest('.form-field').show();
        dropdownJustificationTransfer.closest('.form-field').show();
        dropdownKeepCategory.closest('.form-field').show();
        if (dropdownKeepCategory.val() === '0') {
            dropdownITILCategoriesId.closest('.form-field').show();
        } else {
            dropdownITILCategoriesId.closest('.form-field').hide();
            dropdownITILCategoriesId.val('0');
            dropdownITILCategoriesId.trigger('change');
        }
    }
    form.on('change', function(event) {
        dropdownAllowTransferChange();
    })
}

$(function() {
    dropdownAllowTransferChange();
})
