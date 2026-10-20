(function () {
    'use strict';

    var $ = window.jQuery;

    /**
     * Rol ver / rol değiştir / çıkar uçları yalnız POST kabul eder (karar 7); CSRF jetonu gövdeye eklenir.
     */
    function csrfData(data) {
        var yii = window.yii;
        if (yii && typeof yii.getCsrfParam === 'function' && yii.getCsrfParam()) {
            data[yii.getCsrfParam()] = yii.getCsrfToken();
        }
        return data;
    }

    function errorBox() {
        return $('#assignment-error');
    }

    function hideError() {
        errorBox().addClass('d-none').text('');
    }

    /**
     * Reddedilen isteğin mesajı (ör. üye olmayana rol verme: 403 + {message}); yoksa sayfanın genel metni.
     */
    function errorMessage(xhr) {
        var body = xhr ? xhr.responseJSON : null;
        if (body && typeof body.message === 'string' && body.message !== '') {
            return body.message;
        }
        return errorBox().attr('data-default-message') || '';
    }

    function showError(xhr) {
        errorBox().text(errorMessage(xhr)).removeClass('d-none');
    }

    /**
     * Seçim yokken istek gönderilmez; kutuda ne yapılacağı söylenir (data-no-member-message / data-no-selection-message).
     */
    function showHint(attribute) {
        errorBox().text(errorBox().attr(attribute) || '').removeClass('d-none');
    }

    function isEmpty(values) {
        return !values || values.length === 0;
    }

    function reloadFlash() {
        try {
            $.pjax.reload({ container: '#pjax-flash-message' });
        } catch (error) {
        }
    }

    function reloadLists(modalSelector) {
        return new Promise(function (resolve) {
            $.pjax.reload({ container: '#assigned' }).done(function () {
                $.pjax.reload({ container: '#users' }).done(function () {
                    $(modalSelector).modal('hide');
                    resolve();
                });
            });
        });
    }

    function post(url, data) {
        return new Promise(function (resolve, reject) {
            $.ajax({ url: url, type: 'POST', dataType: 'json', data: csrfData(data) })
                .done(function (response) {
                    resolve(response);
                })
                .fail(function (xhr) {
                    reject(xhr);
                });
        });
    }

    function assignAction(selectedValues, role, idWorkspace, modalSelector, idModule, type) {
        if (role === '' || role === null || role === undefined) {
            return Promise.resolve();
        }
        return post('assign?id=' + encodeURIComponent(idWorkspace), {
            selected_values: selectedValues,
            role: role,
            id_module: idModule,
            type: type
        }).then(function () {
            return reloadLists(modalSelector);
        });
    }

    function removeAction(selectedValues, idWorkspace, modalSelector) {
        return post('remove?id_workspace=' + encodeURIComponent(idWorkspace), {
            selected_values: selectedValues
        }).then(function () {
            return reloadLists(modalSelector);
        });
    }

    function updateUserList() {
        $.ajax({
            url: 'get-users',
            type: 'POST',
            data: csrfData({
                role: this ? this.value : '',
                id_workspace: window.id_workspace
            })
        }).done(function () {
            $('#users-panel').show();
            $('#spinner-div-page').hide();
        });
    }

    function moduleRoles() {
        var roles = {};
        $('.module-list').each(function (index, element) {
            roles[element.getAttribute('data-key')] = element.value;
        });
        return roles;
    }

    function init() {
        $('#role-select').on('click', async function () {
            var selectedValues = ($('#users-select-list').val() || []).slice();
            var roles = moduleRoles();
            var icon = $('.fa-arrow-right');
            hideError();
            if (isEmpty(selectedValues)) {
                showHint('data-no-member-message');
                return;
            }
            icon.removeClass('fa fa-arrow-right');
            icon.addClass('spinner-border spinner-border-sm');
            try {
                for (var key in roles) {
                    await assignAction(selectedValues, roles[key], window.id_workspace, '#roleModal', key, 'create');
                }
            } catch (xhr) {
                showError(xhr);
            }
            icon.removeClass('spinner-border spinner-border-sm');
            icon.addClass('fa fa-arrow-right');
            reloadFlash();
        });

        $('#role-select-update').on('click', function () {
            var selectedUsers = $('#assigned-users').val();
            var selectedRole = $('#role-list-update').val();
            var idModule = $('#module-list-update').val();
            hideError();
            assignAction(selectedUsers, selectedRole, window.id_workspace, '#roleModalUpdate', idModule, 'update')
                .catch(function (xhr) {
                    $('#roleModalUpdate').modal('hide');
                    showError(xhr);
                })
                .then(reloadFlash);
        });

        $('#removeButton').on('click', function () {
            var selectedValues = $('select[data-target="assigned"]').val();
            hideError();
            if (isEmpty(selectedValues)) {
                showHint('data-no-selection-message');
                return;
            }
            $('#users-panel').hide();
            $('#spinner-div-page').show();
            removeAction(selectedValues, window.id_workspace, '#roleModal')
                .then(function () {
                    updateUserList.call(document.getElementById('available-roles'));
                }, function (xhr) {
                    $('#users-panel').show();
                    $('#spinner-div-page').hide();
                    showError(xhr);
                })
                .then(reloadFlash);
        });
    }

    $(init);
})();
