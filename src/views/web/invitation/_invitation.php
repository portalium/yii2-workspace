<?php

use portalium\workspace\Module;
use portalium\workspace\models\InvitationForm;
use yii\jui\DatePicker;

echo $form->field($model, 'emails')->textarea([
    'rows' => 3,
    'value' => is_array($model->emails) ? implode(', ', $model->emails) : $model->emails,
    'placeholder' => Module::t('name@company.com, second@company.com'),
])->hint(Module::t('Type the email addresses to invite, separated by commas or new lines.'));

echo $form->field($model, 'date_expire')->widget(DatePicker::classname(), [
    'dateFormat' => 'yyyy-MM-dd',
    'options' => ['class' => 'form-control', 'autocomplete' => 'off'],
    'clientOptions' => [
        'changeMonth' => true,
        'changeYear' => true,
        'minDate' => 0,
        'yearRange' => Date('Y') . ':' . (Date('Y') + 10),
    ],
])->hint(Module::t('Leave empty for {days} days.', ['days' => (int) filter_var(InvitationForm::DEFAULT_EXPIRY, FILTER_SANITIZE_NUMBER_INT)]));

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