<?php

use yii\helpers\Html;
use portalium\workspace\Module;

use portalium\theme\widgets\Panel;
use portalium\theme\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="workspace-form">

    <?php $form = ActiveForm::begin(); ?>
    <?php Panel::begin([
    'title' => ($model->isNewRecord) ? Module::t('Create Workspace') : $model->name,
    'actions' => [
        'header' => [

        ],
        'footer' => [
            ($model->isNewRecord) ?  Html::submitButton(Module::t('Save'), ['class' => 'btn btn-success']) : 
            Html::submitButton(Module::t('Update'), ['class' => 'btn btn-primary'])
        ]
    ]
]) ?>
    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?php Panel::end() ?>
    <?php ActiveForm::end(); ?>

</div>
