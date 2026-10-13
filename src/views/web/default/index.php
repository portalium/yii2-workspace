<?php

use yii\helpers\Url;
use yii\helpers\Html;

use portalium\workspace\Module;

use portalium\theme\widgets\Panel;
use portalium\theme\widgets\GridView;
use portalium\workspace\models\Workspace;
use portalium\theme\widgets\ActionColumn;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\WorkspaceSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var yii\data\ActiveDataProvider $joinedDataProvider */

$this->title = Module::t('Workspaces');
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="workspace-index">

    <?php
    // Offer only what the controller will allow; owners got 403 behind these buttons.
    $user = Yii::$app->user;
    $canBulkDelete = $user->can('workspaceWebDefaultDelete', ['id_module' => 'workspace']);
    $canCreate = $user->can('workspaceWebDefaultCreate', ['id_module' => 'workspace']);
    $actions = [];
    if ($canBulkDelete) {
        $actions[] = Html::button(Module::t(''), ['class' => 'fa fa-trash btn btn-danger', 'id' => 'delete-select', 'type' => 'button']);
    }
    if ($canCreate) {
        $actions[] = Html::a(Module::t(''), ['create-virtual'], ['class' => 'btn btn-warning fa fa-user-secret', 'id' => 'create-virtual-workspace', 'title' => Module::t('Create Virtual Workspace')]);
        $actions[] = Html::a(Module::t(''), ['create'], ['class' => 'btn btn-success fa fa-plus', 'id' => 'create-workspace']);
    }
    $can = function ($permission, $model) use ($user) {
        return $user->can($permission, ['id_module' => 'workspace', 'model' => $model]);
    };
    Panel::begin(['title' => Module::t('Workspace'), 'actions' => $actions]);
    ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'portalium\grid\CheckboxColumn', 'visible' => $canBulkDelete],
            ['class' => 'portalium\grid\SerialColumn'],
            'name',
            'user.username',
            [
                'attribute' => 'is_virtual',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->is_virtual == Workspace::IS_VIRTUAL_TRUE
                        ? '<span class="badge bg-warning">' . Module::t('Virtual') . '</span>'
                        : '<span class="badge bg-secondary">' . Module::t('Real') . '</span>';
                },
                'filter' => Workspace::getIsVirtualList(),
                'label' => Module::t('Type'),
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Workspace $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id_workspace]);
                },
                'template' => '{view} {update} {delete} {assign} {invitation}',
                'visibleButtons' => [
                    'view' => function ($model) use ($can) {
                        return $can('workspaceWebDefaultView', $model);
                    },
                    'update' => function ($model) use ($can) {
                        return $can('workspaceWebDefaultUpdate', $model);
                    },
                    'delete' => function ($model) use ($can) {
                        return $can('workspaceWebDefaultDelete', $model);
                    },
                    'assign' => function ($model) use ($can) {
                        return $can('workspaceWebDefaultAssignment', $model);
                    },
                    'invitation' => function ($model) use ($can) {
                        return $can('workspaceWebDefaultView', $model);
                    },
                ],
                'buttons' => [
                    'assign' => function ($url, $model) {
                        return Html::a(
                            Html::tag('i', '', ['class' => 'fa fa-user text-warning']),
                            ['/workspace/assignment/assignment', 'id' => $model->id_workspace],
                            ['title' => Module::t('Assign'), 'class' => 'btn btn-warning btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
                        );
                    },
                    'invitation' => function ($url, $model) {
                        return Html::a(
                            Html::tag('i', '', ['class' => 'fa fa-thin fa-envelope']),
                            ['/workspace/invitation/index', 'id' => $model->id_workspace],
                            ['title' => Module::t('Invitation'), 'class' => 'btn btn-info btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
                        );
                    },
                ],
            ],
        ],
        'layout' => '{items}{summary}{pagesizer}{pager}',
    ]); ?>

    <?php Panel::end(); ?>

    <?php // actionManage renders this view for every workspace and has no joined list. ?>
    <?php if (isset($joinedDataProvider) && Yii::$app->user->can('workspaceWebAssignmentLeave')) : ?>
        <?php Panel::begin(['title' => Module::t('Workspaces I joined')]); ?>
        <?= GridView::widget([
            'dataProvider' => $joinedDataProvider,
            'columns' => [
                ['class' => 'portalium\grid\SerialColumn'],
                'name',
                [
                    'attribute' => 'user.username',
                    'label' => Module::t('Owner'),
                ],
                [
                    'class' => ActionColumn::className(),
                    'template' => '{leave}',
                    'buttons' => [
                        'leave' => function ($url, $model) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-sign-out']),
                                ['/workspace/assignment/leave', 'id' => $model->id_workspace],
                                [
                                    'title' => Module::t('Leave workspace'),
                                    'class' => 'btn btn-danger btn-xs',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => Module::t('Are you sure you want to leave this workspace?'),
                                    ],
                                ]
                            );
                        },
                    ],
                ],
            ],
            'layout' => '{items}{summary}{pager}',
        ]); ?>
        <?php Panel::end(); ?>
    <?php endif; ?>

</div>
<?php
$currentUrl = Url::current();
$csrfParam = \Yii::$app->request->csrfParam;
$csrfToken = \Yii::$app->request->csrfToken;
$confirmMsg = json_encode(Module::t('Are you sure you want to delete the selected workspaces?'));

$js = <<< JS
$('#delete-select').on('click', function () {
    var ids = $('input[name="selection[]"]:checked').map(function () { return this.value; }).get();
    if (ids.length === 0) { return; }
    if (!confirm($confirmMsg)) { return; }
    var \$form = $('<form>', { method: 'post', action: '$currentUrl' }).hide().appendTo('body');
    \$form.append($('<input>', { type: 'hidden', name: '$csrfParam', value: '$csrfToken' }));
    $.each(ids, function (i, v) {
        \$form.append($('<input>', { type: 'hidden', name: 'selection[]', value: v }));
    });
    \$form.submit();
});
JS;
$this->registerJs($js, \yii\web\View::POS_END);
?>