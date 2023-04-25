<?php

use yii\helpers\Html;
use portalium\theme\widgets\ActiveForm;
use portalium\workspace\Module;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\WorkspaceSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="workspace-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_workspace') ?>

    <?= $form->field($model, 'name') ?>

    <?= $form->field($model, 'date_create') ?>

    <?= $form->field($model, 'date_update') ?>

    <div class="form-group">
        <?= Html::submitButton(Module::t('Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Module::t('Reset'), ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
