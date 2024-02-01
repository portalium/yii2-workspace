<?php

use yii\helpers\Html;
use portalium\workspace\Module;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Invitation $model */

$this->title = Module::t('Update Invite: {name}', [
    'name' => $model->workspace->name,
]);
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->workspace->name, 'url' => ['view', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = Module::t('Update');
?>
<div class="workspace-update">

    <?= $this->render('_form', [
        'model' => $model,
        'dynamicModuleModel' => $dynamicModuleModel,
        'availableRoles' => $availableRoles,
    ]) ?>

</div>
