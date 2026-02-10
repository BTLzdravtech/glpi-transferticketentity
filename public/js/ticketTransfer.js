function tteGetEntityGroups() {
    const entityChoice = $('select[name=entity_choice]');
    const groupChoice = $('select[name=group_choice]');
    $.ajax({
        url: CFG_GLPI.root_doc + '/plugins/transferticketentity/ajax/get_entities_rights.php',
        method: "GET",
        data: {
            entity_id: entityChoice.val()
        },
        success: function (data) {
            const form = $('#tte_form');
            form.data('entity-right', data.rights);

            $(groupChoice).find('option[value!="0"]').remove();
            const groupChoiceFormField = groupChoice.closest('.form-field');
            if (entityChoice.val() === '-1') {
                groupChoiceFormField.hide();
            } else {
                data.groups.forEach((group) => {
                    $(groupChoice).append(new Option(group.name, group.id, false, false)).trigger('change');
                });
                groupChoiceFormField.show();
                if (data.rights.keep_category) {
                    $('#tte_adv-msg').show();
                } else {
                    $('#tte_adv-msg').hide();
                }
                const justificationText = $('#transferticketentity_dialog').find('#tte_justification');
                const optionalText = ' (' + __('optional', 'transferticketentity') + ')'
                if (data.rights.justification_transfer) {
                    justificationText.prop('required', true);
                    justificationText.prev().text(justificationText.prev().text().replace(optionalText, ''));
                } else {
                    justificationText.prop('required', false);
                    justificationText.prev().text(justificationText.prev().text() + optionalText);
                }
            }
        }
    });
}

$(function() {
    const form = $('#tte_form');
    form.on('submit', (event) => {
        event.preventDefault();
        const entityRights = form.data('entity-right');
        const entityChoice = $('select[name=entity_choice]');
        const groupChoice = $('select[name=group_choice]');
        if (entityChoice.val() === '-1') {
            glpi_toast_error(__('Mandatory fields are not filled. Please correct: %s').replace("%s", __('Entity')));
            return;
        }

        if (entityRights.allow_entity_only_transfer && groupChoice.val() === '0') {
            glpi_toast_error(__("No group found with « Assigned to » right while a group is required. Transfer impossible.", "transferticketentity"));
            return;
        }

        const modal = glpi_confirm({
            title: __("Confirm transfer ?", "transferticketentity"),
            message: $('#transferticketentity_dialog').html().trim(),
            confirm_callback: () => {
                const justificationText = $('#' + modal).find('#tte_justification');
                if (justificationText.prop('required') && !justificationText.val().length) {
                    glpi_toast_error(__("Justification required", "transferticketentity"));
                } else {
                    form.off('submit');
                    form.submit();
                }
            }
        });
        $('#' + modal).on('hide.bs.modal', function (event) {
            form.find('input[name=justification]').val($('#' + modal).find('#tte_justification').val());
        })
    })
})
