<?php

use yii\helpers\Html;
use portalium\workspace\Module;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */

$this->title = Module::t('Update Workspace: {name}', [
    'name' => $model->name,
]);
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = Module::t('Update');
?>
<div class="workspace-update">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
