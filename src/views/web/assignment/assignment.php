<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\widgets\Pjax;
use yii\bootstrap5\Modal;
use kartik\depdrop\DepDrop;
use kartik\select2\Select2;
use portalium\workspace\Module;
use portalium\theme\widgets\Panel;
use portalium\user\models\User;
use portalium\workspace\bundles\AssignmentAsset;

/** @var portalium\workspace\models\Workspace $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
$this->registerCss(
    <<<CSS
    .select2 .selection {
        width: 100%;
    }

    legend {
        float: none;
        width: auto;
        font-size: inherit;
    }
    CSS
);
?>

<div class="workspace-assignment">
    <p>
        <?php Panel::begin([
            'title' => Html::encode($this->title),
        ]) ?>
    </p>

    <div class="row">
        <div class="col-md-5">

            <?php Panel::begin(['title' => Html::encode(Module::t('Users')), 'options' => ['id' => 'users-panel']]) ?>
            <?php
            echo Select2::widget([
                'name' => 'users-select-list',
                'id' => 'users-select-list',
                'data' => array_map(function ($item) {
                    return Module::t($item);
                }, User::find()->select('username')->indexBy('id_user')->column()),
                'maintainOrder' => true,
                'toggleAllSettings' => [
                    'selectLabel' => '<i class="fa fa-check-circle"></i> Tag All',
                    'unselectLabel' => '<i class="fa fa-times-circle"></i> Untag All',
                    'selectOptions' => ['class' => 'text-success'],
                    'unselectOptions' => ['class' => 'text-danger'],
                ],
                'options' => ['placeholder' => Module::t('Select and add a user ...'), 'multiple' => true, 'style' => 'width:100%'],
                'pluginOptions' => [
                    'tags' => true,
                ],
            ]);
            ?>


            <fieldset style="border-width: 1px; border-style: groove; border-color: #dee2e6; border-image: initial;    padding-left: 10px;
                padding-right: 10px;
                margin-bottom: 1rem;">
                <legend><?= Module::t('Roles') ?></legend>
                <?php
                foreach ($dynamicModuleModel as $key => $value) {
                    $availableRole = [];
                    if (!isset($availableRoles[$key]))
                        continue;
                    foreach ($availableRoles[$key] as $role) {
                        $availableRole[$role] = $role;
                    }
                    echo Html::beginTag('div', ['class' => 'mb-3 row field-dynamicmodel-' . $key, 'style' => 'padding-top: 10px;']);
                    echo Html::label($dynamicModuleModel['_labels'][$key], $key . '-dd-list', ['class' => 'col-sm-2 col-form-label']);
                    echo Html::beginTag('div', ['class' => 'col-sm-10']);
                    echo Html::dropDownList($key, null, $availableRole, ['id' => $key . '-dd-list', 'prompt' => Module::t('Select Role'), 'name' => 'module-list', 'data-key' => $key, 'class' => 'form-control form-select module-list', 'style' => 'margin-bottom: 5px;']);
                    echo Html::endTag('div');
                    echo Html::endTag('div');
                }
                ?>

            </fieldset>
            <?php
            Pjax::begin(['id' => 'users']);

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
                'title' => Module::t('Roles'),
                'footer' => Html::button(Module::t('Close'), ['class' => 'btn btn-assignment', 'data-bs-dismiss' => 'modal']) . Html::button(Module::t('Select'), ['class' => 'btn btn-primary', 'id' => 'role-select-update']),
            ]) ?>
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label" for="role-list-update"><?= Module::t('Roles')?></label>
                <div class="col-sm-10">
                    <?= Html::dropDownList('id_module_update', null, $moduleArray, ['id' => 'module-list-update', 'prompt' => 'Select Module', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;']) ?>
                    <?php
                    echo DepDrop::widget([
                        'name' => 'rolesUpdate',
                        'options' => ['id' => 'role-list-update', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;'],
                        'pluginOptions' => [
                            'depends' => ['module-list-update'],
                            'placeholder' => Module::t('Select...'),
                            'url' => Url::to(['/workspace/assignment/get-role-by-module']),
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
                'title' => Html::encode(Module::t('Assigned Users')),
                'actions' => [
                    Html::button(Html::tag('i', '', ['class' => 'fa fa-pencil']), ['class' => 'btn btn-primary btn-sm', 'id' => 'editButton', 'style' => 'margin-top: -4px; margin-bottom: -5px;', 'data-bs-toggle' => 'modal', 'data-bs-target' => '#roleModalUpdate',]),
                ],
            ]) ?>
            <?= Html::textInput('assigned-users-search', '', ['class' => 'form-control', 'id' => 'assigned-users-search', 'placeholder' => Module::t('Search assigned users'), 'style' => 'margin-bottom: 5px;']) ?>
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

    
    document.getElementById('assigned-users-search').addEventListener('keyup', function() {
        const assignedUsersList = document.querySelector('[data-target="assigned"]');
        filterList(this, assignedUsersList);
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

<?php
$this->registerJs('
    $(document).ajaxSend(function(event, jqxhr, settings) {
        if (settings.type == "POST") {
            settings.data = settings.data + "&' . Yii::$app->request->csrfParam . '=' . Yii::$app->request->csrfToken . '";
        }
    });
');
?>