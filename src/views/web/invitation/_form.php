<?php

use portalium\theme\helpers\Html;
use portalium\theme\widgets\ActiveForm;
use portalium\theme\widgets\Panel;
use portalium\workspace\Module;
use portalium\workspace\models\InvitationRole;
use yii\jui\DatePicker;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\InvitationForm $model */
/** @var yii\base\DynamicModel $dynamicModuleModel */
/** @var array $availableRoles modül => roller */
/** @var portalium\workspace\models\Invitation $modelInvitation */
/** @var array<string,string> $acceptedModules kabul edilmiş rolü olan modüller (modül => rol) */
/** @var string|null $roleError */

$email = $modelInvitation->inviteeEmail();

if ($modelInvitation->isAccepted()) {
    Panel::begin(['title' => Html::encode(Module::t('Update Invitation'))]);
    if ($email !== null) {
        echo Html::tag('p', Html::encode(Module::t('Invited address: {email}', ['email' => $email])));
    }
    echo Html::tag('p', Html::encode(Module::t(
        'This invitation has been accepted; its roles cannot be changed. To change the member\'s roles, use the Members and roles page.'
    )), ['id' => 'ws-invitation-readonly']);
    echo Html::beginTag('ul', ['class' => 'mb-0']);
    foreach ($modelInvitation->roles as $role) {
        echo Html::tag('li', Html::encode($role->module . ': ' . $role->role));
    }
    echo Html::endTag('ul');
    Panel::end();
    return;
}

$form = ActiveForm::begin();
Panel::begin([
    'title' => Html::encode(Module::t('Update Invitation')),
    'actions' => [
        'footer' => [
            Html::submitButton(Module::t('Save'), ['class' => 'btn btn-success']),
        ],
    ],
]);
if ($email !== null) {
    echo Html::tag('p', Html::encode(Module::t('Invited address: {email}', ['email' => $email])));
}
echo Html::tag('p', Html::encode(Module::t('Saving does not send an email; the invitation link stays the same.')), ['class' => 'text-muted small']);

echo $form->field($model, 'date_expire')->widget(DatePicker::classname(), [
    'dateFormat' => 'yyyy-MM-dd',
    'options' => ['class' => 'form-control', 'autocomplete' => 'off'],
    'clientOptions' => [
        'changeMonth' => true,
        'changeYear' => true,
        'minDate' => 0,
        'yearRange' => Date('Y') . ':' . (Date('Y') + 10),
    ],
])->hint($modelInvitation->isExpired()
    ? Module::t('Expired on {date}: pick a new date, or resend it from the list.', ['date' => substr((string) $modelInvitation->date_expire, 0, 16)])
    : Module::t('Current date: {date}. Leave empty to keep it.', ['date' => substr((string) $modelInvitation->date_expire, 0, 16)]));
?>

<div class="mb-3 row">
    <span class="col-sm-2 col-form-label"><?= Html::encode(Module::t('Roles')) ?></span>
    <div class="col-sm-10">
        <div class="ws-invitation-roles">
            <?php
            if ($roleError !== null) {
                echo Html::tag('div', Html::encode($roleError), ['class' => 'invalid-feedback d-block mb-2', 'id' => 'ws-invitation-role-error']);
            }
            foreach ($dynamicModuleModel as $key => $value) {
                $availableRole = [];
                foreach ((array) ($availableRoles[$key] ?? []) as $role) {
                    $availableRole[$role] = $role;
                }
                if (isset($acceptedModules[$key])) {
                    $availableRole[$acceptedModules[$key]] = $acceptedModules[$key];
                    $dynamicModuleModel->$key = $acceptedModules[$key];
                    echo $form->field($dynamicModuleModel, $key)
                        ->dropDownList($availableRole, ['class' => 'form-control', 'disabled' => true])
                        ->hint(Module::t('Accepted; cannot be changed here.'));
                    continue;
                }
                echo $form->field($dynamicModuleModel, $key)->dropDownList($availableRole, ['prompt' => Module::t('No role'), 'class' => 'form-control']);
            }
            ?>
        </div>
    </div>
</div>
<?php
Panel::end();
ActiveForm::end();
