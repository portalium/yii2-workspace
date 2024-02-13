<?php

use portalium\theme\widgets\ActionColumn;
use portalium\theme\widgets\ActiveForm;
use portalium\theme\widgets\GridView;
use portalium\theme\widgets\Modal;
use yii\helpers\Html;
use portalium\workspace\Module;
use yii\widgets\DetailView;
use portalium\theme\widgets\Panel;
use portalium\widgets\Pjax;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\Workspace;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */

$this->title = $model->workspace->name;
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/index']];
$this->params['breadcrumbs'][] = ['label' => Module::t('Invitations'), 'url' => ['index', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
$this->registerCss(
    <<<CSS
    
    CSS
);
?>
<div class="workspace-view">
    <p>
        <?php Panel::begin([
            'title' => Html::encode($this->title),
            'actions' => [
            ]
        ]) ?>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            // 'filterModel' => $searchModel,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                'email',
                [
                    'attribute' => 'status',
                    'label' => Module::t('Status'),
                    'format' => 'raw',
                    'value' => function ($model) {
                        return InvitationRole::getStatusList()[$model->status];
                    },
                    'filter' => InvitationRole::getStatusList()
                ],
                [
                    'class' => ActionColumn::className(),
                    'urlCreator' => function ($action, InvitationRole $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id_invitation]);
                    },
                    'template' => '{delete} {resend}',
                    'buttons' => [
                        'resend' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-refresh']),
                                ['resend', 'id' => $model->id_invitation],
                                ['class' => 'btn btn-primary btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;', 'data-confirm' => Module::t('Are you sure resend this invitation?')]
                            );
                        },
                        'delete' => function ($url, $model, $key) {
                            return Html::button(
                                Html::tag('i', '', ['class' => 'fa fa-trash']),
                                ['class' => 'btn btn-danger btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;', 'onclick' => 'deleteInvitation(' . $model->id_invitation . ')']
                            );
                        },
                    ],
                ],
            ],
        ]); ?>
        <?php
        Panel::end()
        ?>

</div>
