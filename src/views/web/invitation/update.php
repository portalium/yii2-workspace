<?php

use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\workspace\bundles\InvitationAsset;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Invitation $modelInvitation */

$this->title = Module::t('Update Invite: {name}', [
    'name' => $modelInvitation->workspace->displayTitle(),
]);
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/index']];
$this->params['breadcrumbs'][] = ['label' => $modelInvitation->workspace->displayTitle(), 'url' => ['/workspace/default/view', 'id' => $modelInvitation->id_workspace]];
$this->params['breadcrumbs'][] = ['label' => Module::t('Invitations'), 'url' => ['index', 'id' => $modelInvitation->id_workspace]];
$this->params['breadcrumbs'][] = Module::t('Update');
InvitationAsset::register($this);
?>
<div class="workspace-update">
    <?= $this->render('_form', [
        'model' => $model,
        'dynamicModuleModel' => $dynamicModuleModel,
        'availableRoles' => $availableRoles,
        'modelInvitation' => $modelInvitation,
        'acceptedModules' => $acceptedModules,
        'roleError' => $roleError,
    ]) ?>
</div>
