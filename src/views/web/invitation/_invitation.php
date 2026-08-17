<?php

use kartik\select2\Select2;
use portalium\user\models\User;
use portalium\workspace\Module;
use yii\jui\DatePicker;

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
// echo $form->field($model, 'email')->textInput(['maxlength' => true]);
echo $form->field($model, 'emails')->widget(Select2::className(), [
    'name' => 'color_3',
    'data' => array_map(function ($item) {
        return Module::t($item);
    }, $usersEmail),
    'maintainOrder' => true,
    'toggleAllSettings' => [
        'selectLabel' => '<i class="fa fa-check-circle"></i> Tag All',
        'unselectLabel' => '<i class="fa fa-times-circle"></i> Untag All',
        'selectOptions' => ['class' => 'text-success'],
        'unselectOptions' => ['class' => 'text-danger'],
    ],
    'options' => ['placeholder' => 'Select or add a user ...', 'multiple' => true, 'style' => 'width:100%'],
    'pluginOptions' => [
        'tags' => true,
    ],
]);

echo $form->field($model, 'date_expire')->widget(DatePicker::classname(), [
    'dateFormat' => 'yyyy-MM-dd',
    'options' => ['class' => 'form-control', 'autocomplete' => 'off'],
    'clientOptions' => [
        'changeMonth' => true,
        'changeYear' => true,
        'yearRange' => Date('Y') . ':' . (Date('Y') + 10),
    ],
]);

?>

<div class="mb-3 row field-invitationform-emails required">
<label class="col-sm-2 col-form-label" for="invitationform-emails">Roles</label>
<div class="col-sm-10">
<div style="border-width: 1px; border-style: solid; border-color: #dee2e6; border-image: initial; padding-top: 13px;   padding-left: 10px;
    padding-right: 10px;
    margin-bottom: 1rem;">
    <?php
    foreach ($dynamicModuleModel as $key => $value) {
        $availableRole = [];
        foreach ($availableRoles[$key] as $role) {
            $availableRole[$role] = $role;
        }
        echo $form->field($dynamicModuleModel, $key)->dropDownList($availableRole, ['prompt' => 'Select Role', 'class' => 'form-control', 'style' => 'margin-bottom: 5px;']);
    }
    ?>
</div>
</div>
</div>