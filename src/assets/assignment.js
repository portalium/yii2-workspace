$(document).ready(function() {
    // Handles the click event for the role-select element
    $('#role-select').on('click', function() {
        $('#users-panel').hide();
        $('#spinner-div-page').show();
        var selectedValues = $('select[data-target="available"]').val();
        var selectedRole = $('#available-roles').val();
        var id_module = $('#module-list').val();

        assignAction(selectedValues, selectedRole, id_workspace, '#roleModal', id_module, 'create');
        $.pjax.reload({
            container: '#pjax-flash-message',
            async: false
        }).done(function() {
        });
    });

    // Handles the click event for the role-select-update button
    $('#role-select-update').on('click', function() {
        var selectedValues = $('select[data-target="assigned"]').val();
        var selectedRole = $('#role-list-update').val();
        var id_module = $('#module-list-update').val();
        assignAction(selectedValues, selectedRole, id_workspace, '#roleModalUpdate', id_module, 'update');
    });

    // Handles the click event for the removeButton element
    $('#removeButton').on('click', function() {
        $('#users-panel').hide();
        $('#spinner-div-page').show();
        
        var selectedValues = $('select[data-target="assigned"]').val();
        removeAction(selectedValues, id_workspace, '#roleModal');
        $.pjax.reload({
            container: '#pjax-flash-message',
            async: false
        }).done(function() {
        });
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
    function assignAction(selectedValues, selectedRole, id_workspace, modalSelector, id_module, type) {
        // Send AJAX request to assign action with selected users, role, and workspace ID
        $.get('assign', {
            selected_values: selectedValues,
            role: selectedRole,
            id: id_workspace,
            id_module: id_module,
            type: type
        }, function(data) {
            // Reload assigned and users sections using pjax
            $.pjax.reload({
                container: '#assigned'
            }).done(function() {
                $.pjax.reload({
                    container: '#users'
                }).done(function() {
                    // Hide role modal
                    updateUserList.call(document.getElementById('available-roles'));
                    $(modalSelector).modal('hide');
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
        }, function(data) {
            // Reload assigned and users sections using pjax
            $.pjax.reload({
                container: '#assigned'
            }).done(function() {
                $.pjax.reload({
                    container: '#users'
                }).done(function() {
                    // Hide role modal
                    updateUserList.call(document.getElementById('available-roles'));
                    $(modalSelector).modal('hide');
                });
            });
        });
    }

    function updateUserList(){
        const role = this.value;
        $.ajax({
            url: 'get-users',
            type: 'POST',
            data: {
                role: role,
                id_workspace: id_workspace,
                '_csrf-web': yii.getCsrfToken()
            },
            success: function(data) {
                $('#users-panel').show();
                $('#spinner-div-page').hide();
                data = JSON.parse(data);
                const usersList = document.querySelector('[data-target="available"]');
                var options = '';
                for (var id_user in data) {
                    options += '<option value="' + id_user + '">' + data[id_user] + '</option>';
                }
                usersList.innerHTML = options;
            }
        });
    }

});
