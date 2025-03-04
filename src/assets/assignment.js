$(document).ready(function () {

    /**
     * Handles the role assignment process when the "Assign Role" button is clicked.
     * Retrieves selected users and their associated module roles, then iterates through
     * each module to assign roles using the `assignAction` function.
     * Displays a loading spinner during the process and reloads relevant sections via pjax.
     */
    $("#role-select").on("click", async function (e) {
        var selectedUsers = $("#users-select-list").val();

        let modules = $(".module-list");

        let moduleRoles = {};
        modules.each(function (index, element) {
            let attr = element.attributes["data-key"];
            let dataKey = attr.value;
            moduleRoles[dataKey] = element.value;
        });
        let selectedValues = [];
        selectedUsers.forEach(function (element) {
            selectedValues.push(element);
        });

        let iTag = $(".fa-arrow-right");
        iTag.removeClass("fa fa-arrow-right");
        iTag.addClass("spinner-border spinner-border-sm");

        for (let key in moduleRoles) {
            let role = moduleRoles[key];
            let id_module = key;
            await assignAction(
                selectedValues,
                role,
                id_workspace,
                "#roleModal",
                id_module,
                "create"
            );
        }
        iTag.removeClass("spinner-border spinner-border-sm");
        iTag.addClass("fa fa-arrow-right");
        try {
            $.pjax.reload({ container: "#pjax-flash-message" });
        } catch (error) {}
    });

    /**
     * Handles role updates when the "Update Role" button is clicked.
     * Retrieves selected users, roles, and module ID, then calls `assignAction` with "update" type.
     * Uses pjax to reload relevant sections upon completion.
     */

    $("#role-select-update").on("click", function () {
        var selectedValues = $('select[data-target="assigned"]').val();
        var selectedRole = $("#role-list-update").val();
        var id_module = $("#module-list-update").val();
        var selectedUsers = $("#assigned-users").val();
        assignAction(
            selectedUsers,
            selectedRole,
            id_workspace,
            "#roleModalUpdate",
            id_module,
            "update"
        );
        try {
            $.pjax.reload({ container: "#pjax-flash-message" });
        } catch (error) {}
    });

    /**
     * Handles the removal of assigned roles when the "Remove" button is clicked.
     * Hides the users panel, shows a loading spinner, and calls `removeAction` to remove selected users from roles.
     */

    $("#removeButton").on("click", function () {
        $("#users-panel").hide();
        $("#spinner-div-page").show();

        var selectedValues = $('select[data-target="assigned"]').val();

        removeAction(selectedValues, id_workspace, "#roleModal");
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
    async function assignAction(
        selectedValues,
        selectedRole,
        id_workspace,
        modalSelector,
        id_module,
        type
    ) {
        return await new Promise((resolve, reject) => {
            $.get(
                "assign",
                {
                    selected_values: selectedValues,
                    role: selectedRole,
                    id: id_workspace,
                    id_module: id_module,
                    type: type,
                },
                function (data) {
                    $.pjax
                        .reload({
                            container: "#assigned",
                        })
                        .done(function () {
                            $.pjax
                                .reload({
                                    container: "#users",
                                })
                                .done(function () {
                                    $(modalSelector).modal("hide");
                                    resolve();
                                });
                        });
                }
            );
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
        return $.get("remove", {
            selected_values: selectedValues,
            id_workspace: id_workspace,
        }).done(function () {

            return $.pjax
                .reload({ container: "#pjax-flash-message", timeout: false })
                .done(function () {

                    return $.pjax
                        .reload({ container: "#assigned", timeout: false })
                        .done(function () {

                            return $.pjax
                                .reload({ container: "#users", timeout: false })
                                .done(function () {

                                    $(modalSelector).modal("hide");
                                    $("#users-panel").show();
                                    $("#spinner-div-page").hide();
                                });
                        });
                });
        });
    }

    /**
     * Fetches users based on the selected role and updates the user list.
     * Sends an AJAX POST request to retrieve users associated with the selected role in the given workspace.
     * On success, it displays the users panel and hides the loading spinner.
     */
    function updateUserList() {
        const role = this.value;
        $.ajax({
            url: "get-users",
            type: "POST",
            data: {
                role: role,
                id_workspace: id_workspace,
                "_csrf-web": yii.getCsrfToken(),
            },
            success: function (data) {
                $("#users-panel").show();
                $("#spinner-div-page").hide();
            },
        });
    }
});