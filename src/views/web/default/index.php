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

$this->title = Module::t('Workspaces');
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="workspace-index">

    <?php
    $actions[] = Html::button(Module::t(''), ['class' => 'fa fa-trash btn btn-danger', 'id' => 'delete-select', 'type' => 'button']);
    $actions[] = Html::a(Module::t(''), ['create-virtual'], ['class' => 'btn btn-warning fa fa-user-secret', 'id' => 'create-virtual-workspace', 'title' => Module::t('Create Virtual Workspace')]);
    $actions[] = Html::a(Module::t(''), ['create'], ['class' => 'btn btn-success fa fa-plus', 'id' => 'create-workspace']);
    Panel::begin(['title' => Module::t('Workspace'), 'actions' => $actions]);

    // index WorkspaceUser, manage Workspace satırı verir
    $ws = static function ($model) {
        return $model instanceof Workspace ? $model : $model->workspace;
    };
    ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        // index'in sorgusu (getJoinedWorkspaces) filtreleri uygulamıyor
        'filterModel' => $this->context->action->id === 'manage' ? $searchModel : null,
        'columns' => [
            [
                'class' => 'portalium\grid\CheckboxColumn',
                'checkboxOptions' => function ($model) use ($ws) {
                    return ['value' => $ws($model)->id_workspace];
                },
            ],
            ['class' => 'portalium\grid\SerialColumn'],
            [
                'attribute' => 'workspace.name',
                'value' => function ($model) use ($ws) {
                    return $ws($model)->name;
                },
                'label' => Module::t('Name'),
                'filterAttribute' => 'name',
            ],
            [
                'attribute' => 'workspace.user.username',
                'value' => function ($model) use ($ws) {
                    return $ws($model)->user->username ?? null;
                },
                'label' => \portalium\user\Module::t('Username'),
                'filterAttribute' => 'username',
            ],
            [
                'attribute' => 'workspace.is_virtual',
                'format' => 'raw',
                'value' => function ($model) use ($ws) {
                    return $ws($model)->is_virtual == Workspace::IS_VIRTUAL_TRUE
                        ? '<span class="badge bg-warning">' . Module::t('Virtual') . '</span>'
                        : '<span class="badge bg-secondary">' . Module::t('Real') . '</span>';
                },
                'filter' => Workspace::getIsVirtualList(),
                'filterAttribute' => 'is_virtual',
                'label' => Module::t('Type'),
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, $model, $key, $index, $column) use ($ws) {
                    return Url::toRoute([$action, 'id' => $ws($model)->id_workspace]);
                },
                'template' => '{view} {update} {delete} {assign} {invitation}',
                'buttons' => [
                    'assign' => function ($url, $model) use ($ws) {
                        return Html::a(
                            Html::tag('i', '', ['class' => 'fa fa-user text-warning']),
                            ['/workspace/assignment/assignment', 'id' => $ws($model)->id_workspace],
                            ['title' => Module::t('Assign'), 'class' => 'btn btn-warning btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
                        );
                    },
                    'invitation' => function ($url, $model) use ($ws) {
                        return Html::a(
                            Html::tag('i', '', ['class' => 'fa fa-thin fa-envelope']),
                            ['/workspace/invitation/index', 'id' => $ws($model)->id_workspace],
                            ['title' => Module::t('Invitation'), 'class' => 'btn btn-info btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
                        );
                    },
                ],
            ],
        ],
        'layout' => '{items}{summary}{pagesizer}{pager}',
    ]); ?>

    <?php Panel::end(); ?>

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
