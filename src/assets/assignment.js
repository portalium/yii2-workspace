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

        // Stop at the first refusal (e.g. the plan's member limit) so the other modules are not assigned half-way.
        let succeeded = true;
        for (let key in moduleRoles) {
            let role = moduleRoles[key];
            let id_module = key;
            if (!await assignAction(selectedValues, role, id_workspace, '#roleModal', id_module, 'create')) {
                succeeded = false;
                break;
            }
        }
        iTag.removeClass('spinner-border spinner-border-sm');
        iTag.addClass('fa fa-arrow-right');
        if (succeeded) {
            reloadFlashMessages();
        }
    });

    // Handles the click event for the role-select-update button
    $('#role-select-update').on('click', async function () {
        var selectedRole = $('#role-list-update').val();
        var id_module = $('#module-list-update').val();
        var selectedUsers = $('#assigned-users').val();
        if (await assignAction(selectedUsers, selectedRole, id_workspace, '#roleModalUpdate', id_module, 'update')) {
            reloadFlashMessages();
        }
    });

    // Handles the click event for the removeButton element
    $('#removeButton').on('click', function () {
        $('#users-panel').hide();
        $('#spinner-div-page').show();

        var selectedValues = $('select[data-target="assigned"]').val();
        removeAction(selectedValues, id_workspace, '#roleModal').then(function (succeeded) {
            if (succeeded) {
                reloadFlashMessages();
            }
        });
    });

    function reloadFlashMessages() {
        try {
            $.pjax.reload({ container: '#pjax-flash-message' });
        } catch (error) {

        }
    }

    /**
     * Shows the server's refusal (plan limit, missing permission, ...) above the page. The error page is parsed
     * with DOMParser so its scripts never run, and the message is inserted as text.
     */
    function showRequestError(xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message;
        if (!message && xhr.responseText) {
            var errorBox = new DOMParser().parseFromString(xhr.responseText, 'text/html').querySelector('.site-error .alert-danger');
            message = errorBox ? errorBox.textContent.trim() : '';
        }
        if (!message) {
            message = xhr.status + ' ' + xhr.statusText;
        }
        var alert = $('<div class="alert alert-danger alert-dismissible fade show" role="alert"></div>').text(message);
        alert.append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        $('#pjax-flash-message').prepend(alert);
    }

    /**
     * Sends an AJAX request to the assign action with the selected users, role, and workspace ID as parameters.
     * When the response is received, it reloads the assigned and users sections using pjax, and hides the role modal.
     *
     * @param {Array} selectedValues An array of selected values.
     * @param {string} selectedRole The selected role.
     * @param {string} id_workspace The workspace ID.
     * @param {string} modalSelector The modal selector.
     * @return {Promise<boolean>} false when the server refused the request
     */
    async function assignAction(selectedValues, selectedRole, id_workspace, modalSelector, id_module, type) {
        if (selectedRole === '' || selectedRole === null) {
            return true;
        }
        // Send AJAX request to assign action with selected users, role, and workspace ID
        return await new Promise((resolve) => {
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
                        resolve(true);
                    });
                });
            }).fail(function (xhr) {
                $(modalSelector).modal('hide');
                showRequestError(xhr);
                resolve(false);
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
     * @return {Promise<boolean>} false when the server refused the request
     */
    function removeAction(selectedValues, id_workspace, modalSelector) {
        return new Promise((resolve) => {
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
                        resolve(true);
                    });
                });
            }).fail(function (xhr) {
                updateUserList.call(document.getElementById('available-roles'));
                showRequestError(xhr);
                resolve(false);
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
