<?php

use yii\helpers\Html;
use portalium\workspace\Module;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */

$this->title = Module::t('Create Workspace');
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="workspace-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
