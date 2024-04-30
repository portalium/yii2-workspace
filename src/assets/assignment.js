$(document).ready(function () {
    // Handles the click event for the role-select element
    $('#role-select').on('click', async function (e) {
        var selectedUsers = $('#users-select-list').val();

        let modules = $('.module-list');

        let moduleRoles = {};
        modules.each(function (index, element) {
            let attr = element.attributes['data-key'];
            let dataKey = attr.value;
            moduleRoles[dataKey] = element.value;
        });
        let selectedValues = [];
        selectedUsers.forEach(function (element) {
            selectedValues.push(element);
        }
        );

        
        
        let iTag = $('.fa-arrow-right');
        iTag.removeClass('fa fa-arrow-right');
        iTag.addClass('spinner-border spinner-border-sm');

        for (let key in moduleRoles) {
            let role = moduleRoles[key];
            let id_module = key;
            await assignAction(selectedValues, role, id_workspace, '#roleModal', id_module, 'create');
        }
        iTag.removeClass('spinner-border spinner-border-sm');
        iTag.addClass('fa fa-arrow-right');
        try {
            $.pjax.reload({ container: '#pjax-flash-message' });
        } catch (error) {

        }
    });

    // Handles the click event for the role-select-update button
    $('#role-select-update').on('click', function () {
        var selectedValues = $('select[data-target="assigned"]').val();
        var selectedRole = $('#role-list-update').val();
        var id_module = $('#module-list-update').val();
        var selectedUsers = $('#assigned-users').val();
        assignAction(selectedUsers, selectedRole, id_workspace, '#roleModalUpdate', id_module, 'update');
        try {
            $.pjax.reload({ container: '#pjax-flash-message' });
        } catch (error) {

        }
    });

    // Handles the click event for the removeButton element
    $('#removeButton').on('click', function () {
        $('#users-panel').hide();
        $('#spinner-div-page').show();

        var selectedValues = $('select[data-target="assigned"]').val();
        removeAction(selectedValues, id_workspace, '#roleModal');
        try {
            $.pjax.reload({ container: '#pjax-flash-message' });
        } catch (error) {

        }
    });

    /**
     * Sends an AJAX request to the assign action with the selected users, role, and workspace ID as parameters.
     * When the response is received, it reloads the assigned and users sections using pjax, and hides the role modal.
     *
     * @param {Array} selectedValues An array of selected values.
     * @param {string} selectedRole The selected role.
     * @param {string} id_workspace The workspace ID.
     * @param {string} modalSelector The modal selector.
     */
    async function assignAction(selectedValues, selectedRole, id_workspace, modalSelector, id_module, type) {
        // Send AJAX request to assign action with selected users, role, and workspace ID
        return await new Promise((resolve, reject) => {
            $.get('assign', {
                selected_values: selectedValues,
                role: selectedRole,
                id: id_workspace,
                id_module: id_module,
                type: type
            }, function (data) {
                // Reload assigned and users sections using pjax
                $.pjax.reload({
                    container: '#assigned'
                }).done(function () {
                    $.pjax.reload({
                        container: '#users'
                    }).done(function () {
                        $(modalSelector).modal('hide');
                        resolve();
                    });
                });
            });
        });

    }

    /**
     * Sends an AJAX request to the remove action with the selected users and workspace ID as parameters.
     * When the response is received, it reloads the assigned and users sections using pjax, and hides the role modal.
     *
     * @param {Array} selectedValues An array of selected values.
     * @param {string} id_workspace The workspace ID.
     * @param {string} modalSelector The modal selector.
     */
    function removeAction(selectedValues, id_workspace, modalSelector) {
        // Send AJAX request to remove action with selected users and workspace ID
        $.get('remove', {
            selected_values: selectedValues,
            id_workspace: id_workspace
        }, function (data) {
            // Reload assigned and users sections using pjax
            $.pjax.reload({
                container: '#assigned'
            }).done(function () {
                $.pjax.reload({
                    container: '#users'
                }).done(function () {
                    // Hide role modal
                    updateUserList.call(document.getElementById('available-roles'));
                    $(modalSelector).modal('hide');
                });
            });
        });
    }

    function updateUserList() {
        const role = this.value;
        $.ajax({
            url: 'get-users',
            type: 'POST',
            data: {
                role: role,
                id_workspace: id_workspace,
                '_csrf-web': yii.getCsrfToken()
            },
            success: function (data) {
                $('#users-panel').show();
                $('#spinner-div-page').hide();
            }
        });
    }

});
