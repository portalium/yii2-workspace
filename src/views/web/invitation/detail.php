<?php

use portalium\theme\widgets\GridView;
use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\theme\widgets\Panel;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationRole;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Invitation $model */

$this->title = $model->inviteeEmail() ?? $model->workspace->displayTitle();
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/index']];
$this->params['breadcrumbs'][] = ['label' => $model->workspace->displayTitle(), 'url' => ['/workspace/default/view', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = ['label' => Module::t('Invitations'), 'url' => ['index', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="workspace-view">
        <?php Panel::begin([
            'title' => Html::encode($this->title),
            'actions' => [
            ]
        ]) ?>

        <div class="mb-3"><?= Html::encode(Module::t('Status')) ?>: <?= Html::encode(Invitation::statusLabels()[$model->addressStatus()]) ?></div>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                ['class' => 'portalium\grid\SerialColumn'],
                [
                    'attribute' => 'module',
                    'label' => Module::t('Module'),
                ],
                [
                    'attribute' => 'role',
                    'label' => Module::t('Role'),
                ],
                [
                    'attribute' => 'status',
                    'label' => Module::t('Status'),
                    'value' => static fn (InvitationRole $role) => InvitationRole::getStatusList()[$role->status] ?? '',
                ],
            ],
        ]); ?>
        <?php
        Panel::end()
        ?>

</div>
