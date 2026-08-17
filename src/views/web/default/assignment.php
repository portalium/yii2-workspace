<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\widgets\Pjax;
use yii\bootstrap5\Modal;
use kartik\depdrop\DepDrop;
use portalium\workspace\Module;
use portalium\theme\widgets\Panel;
use portalium\workspace\bundles\AssignmentAsset;

/** @var portalium\workspace\models\Workspace $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>

<div class="workspace-assignment">
    <p>
        <?php Panel::begin([
            'title' => Html::encode($this->title),
        ]) ?>
    </p>

    <div class="row">
        <div class="col-md-5">
            <?php
            echo Html::beginTag('div', ['id' => 'spinner-div-page', 'style' => 'display: none;', 'class' => 'row']);
            echo Html::tag('div', '', ['class' => 'spinner-border text-primary col-2', 'role' => 'status']) .
                Html::tag('span', 'Loading...', ['class' => 'sr-only col-2', 'style' => 'margin-left: 0px; margin-top: 5px;']);
            echo Html::endTag('div');
            ?>
            <?php Panel::begin(['title' => Html::encode('Users'), 'options' => ['id' => 'users-panel']]) ?>
            <?= Html::label('Assignable Module', 'available-module', ['class' => 'form-label']) ?>
            <?= Html::dropDownList('id_module', null, $moduleArray, ['id' => 'module-list', 'prompt' => 'Select Module', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;']) ?>
            <?= Html::label('Assignable roles', 'available-roles', ['class' => 'form-label']) ?>
            <?php
            echo DepDrop::widget([
                'name' => 'available-roles',
                'options' => ['id' => 'available-roles', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;'],
                'pluginOptions' => [
                    'depends' => ['module-list'],
                    'placeholder' => Module::t('Select...'),
                    'url' => Url::to(['/workspace/default/get-role-by-module']),
                    'paramsBase' => [
                        Yii::$app->request->csrfParam => Yii::$app->request->csrfToken,
                    ]
                ]
            ]);
            ?>

            <?= Html::textInput('users-search', '', ['class' => 'form-control', 'id' => 'users-search', 'placeholder' => 'Search users', 'style' => 'margin-bottom: 5px;']) ?>
            <?php
            Pjax::begin(['id' => 'users']);
            echo Html::dropDownList(
                'available',
                null,
                $users,
                ['class' => 'form-control list', 'multiple' => true, 'size' => 20, 'data-target' => 'available', 'data-pjax' => true]
            );
            Pjax::end() ?>
            <?php Panel::end() ?>
        </div>
        <div class="col-md-2">
            <div class="text-center" style="position: relative; top: 50%;">
                <div class="btn-group-vertical" style="transform: translateY(-50%);">
                    <?= Html::button(Html::tag('i', '', ['class' => 'fa fa-arrow-left']), ['class' => 'btn btn-danger', 'id' => 'removeButton']) ?>
                    <?= Html::button(Html::tag('i', '', ['class' => 'fa fa-arrow-right']), ['class' => 'btn btn-success', 'id' => 'role-select',]) ?>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <?php Modal::begin([
                'id' => 'roleModalUpdate',
                'title' => 'Roles',
                'footer' => Html::button('Close', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) . Html::button('Select', ['class' => 'btn btn-primary', 'id' => 'role-select-update']),
            ]) ?>
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label" for="role-list-update">Roles</label>
                <div class="col-sm-10">
                <?= Html::dropDownList('id_module_update', null, $moduleArray, ['id' => 'module-list-update', 'prompt' => 'Select Module', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;']) ?>
                <?php
                    echo DepDrop::widget([
                        'name' => 'rolesUpdate',
                        'options' => ['id' => 'role-list-update', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;'],
                        'pluginOptions' => [
                            'depends' => ['module-list-update'],
                            'placeholder' => Module::t('Select...'),
                            'url' => Url::to(['/workspace/default/get-role-by-module']),
                            'paramsBase' => [
                                Yii::$app->request->csrfParam => Yii::$app->request->csrfToken,
                            ]
                        ]
                    ]);
                ?>
                </div>
            </div>
            <?php Modal::end() ?>
            <?php Panel::begin([
                'title' => Html::encode('Assigned Users'),
                'actions' => [
                    Html::button(Html::tag('i', '', ['class' => 'fa fa-pencil']), ['class' => 'btn btn-primary btn-sm', 'id' => 'editButton', 'style' => 'margin-top: -4px; margin-bottom: -5px;', 'data-bs-toggle' => 'modal', 'data-bs-target' => '#roleModalUpdate',]),
                ],
            ]) ?>
            <?= Html::textInput('assigned-users-search', '', ['class' => 'form-control', 'id' => 'assigned-users-search', 'placeholder' => 'Search assigned users', 'style' => 'margin-bottom: 5px;']) ?>
            <?php
            Pjax::begin(['id' => 'assigned']);
            echo Html::dropDownList(
                'assigned',
                null,
                $assignedUsers,
                ['class' => 'form-control list', 'multiple' => true, 'size' => 20, 'data-target' => 'assigned', 'data-pjax' => true, 'id' => 'assigned-users']
            );
            Pjax::end()
            ?>
            <?php Panel::end() ?>
        </div>

    </div>
    <?php Panel::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
    var id_workspace = $model->id_workspace;
    JS, \yii\web\View::POS_HEAD);
AssignmentAsset::register($this);
?>

<?php
$this->registerJs(
    <<<JS
    $('#users-panel').hide();
    $('#spinner-div-page').show();
    //trigger module list
    $('#module-list').trigger('change');

    function filterList(inputElement, listElement) {
        const filter = inputElement.value.toUpperCase();
        const options = listElement.getElementsByTagName('option');
        for (let i = 0; i < options.length; i++) {
            const optionValue = options[i].innerText;
            if (optionValue.toUpperCase().indexOf(filter) > -1) {
                options[i].style.display = '';
            } else {
                options[i].style.display = 'none';
            }
        }
    }

    document.getElementById('users-search').addEventListener('keyup', function() {
        const usersList = document.querySelector('[data-target="available"]');
        filterList(this, usersList);
    });

    document.getElementById('assigned-users-search').addEventListener('keyup', function() {
        const assignedUsersList = document.querySelector('[data-target="assigned"]');
        filterList(this, assignedUsersList);
    });

    document.getElementById('available-roles').addEventListener('change', function() {
        updateUserList.call(this);
    });

    updateUserList.call(document.getElementById('available-roles'));

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
    
    $('#roleModalUpdate').on('show.bs.modal', function (event) {
            var csrfParam = $('meta[name="csrf-param"]').attr("content");
            var csrfToken = $('meta[name="csrf-token"]').attr("content");
            const id_user = document.getElementById('assigned-users').value;
            $.ajax({
                url: 'get-roles',
                type: 'POST',
                data: {
                    id_user: id_user,
                    //'_csrf-web': yii.getCsrfToken()
                    [csrfParam]: csrfToken
                },
                success: function(data) {
                    data = JSON.parse(data);
                    const rolesList = document.getElementById('role-list-update');
                    var options = '';
                    for (var name in data) {
                        options += '<option value="' + name + '">' + data[name] + '</option>';
                    }
                    rolesList.innerHTML = options;
                }
            });
    });

    $('#assigned-users').on('change', function() {
        var selectedValues = $('select[data-target="assigned"]').val();
        if(selectedValues.length > 1) {
            $('#editButton').hide();
        } else {
            $('#editButton').show();
        }
    });

JS
);
?>