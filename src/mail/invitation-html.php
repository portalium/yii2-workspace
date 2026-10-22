<?php

use portalium\workspace\Module;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\InvitationRole $invitation */
/** @var portalium\workspace\models\Workspace $workspace */
/** @var bool $hasAccount */
/** @var string|null $acceptUrl e-postadaki imzalı kabul bağlantısı (InvitationRole::sendEmail) */
/** @var string|null $signupUrl */
/** @var string|null $inviterName */

$verifyLink = $acceptUrl ?? Yii::$app->urlManager->createAbsoluteUrl(['workspace/invitation/accept', 'token' => $invitation->invitation->invitation_token]);
$hasAccount = $hasAccount ?? true;
$signupUrl = $signupUrl ?? null;
$inviterName = $inviterName ?? null;
$workspaceTitle = $workspace->displayTitle();
$validUntil = substr((string) $invitation->invitation->date_expire, 0, 16);
?>
<div class="invitation-html">
    <p><?= Html::encode(Module::t('Hello {email},', ['email' => $invitation->email])) ?></p>

    <?php if ($inviterName !== null && $inviterName !== '') : ?>
        <p><?= Html::encode(Module::t(
            '{inviter} invited you to join the «{workspace_name}» workspace.',
            ['inviter' => $inviterName, 'workspace_name' => $workspaceTitle]
        )) ?></p>
    <?php else : ?>
        <p><?= Html::encode(Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspaceTitle])) ?></p>
    <?php endif; ?>

    <?php if (!$hasAccount && $signupUrl !== null) : ?>
        <p><?= Html::encode(Module::t(
            'There is no account for {email} yet. First sign up with this address using the link below, then open the invitation link.',
            ['email' => $invitation->email]
        )) ?></p>

        <p><?= Html::a(Html::encode($signupUrl), $signupUrl) ?></p>

        <p><?= Html::encode(Module::t(
            'If you sign up with this address using the link above, your account is active right away. '
            . 'After you sign up, the invitation page opens by itself; click «Join» there.'
        )) ?></p>
    <?php elseif (!$hasAccount) : ?>
        <p><?= Html::encode(Module::t(
            'There is no account for {email} yet. Ask your administrator to create one, then open the invitation link.',
            ['email' => $invitation->email]
        )) ?></p>
    <?php endif; ?>

    <p><?= Html::encode(Module::t('Follow the link below to accept the invitation:')) ?></p>

    <p><?= Html::a(Html::encode($verifyLink), $verifyLink) ?></p>

    <p><?= Html::encode(Module::t('This link only works for the account with the address {email}.', ['email' => $invitation->email])) ?></p>

    <?php if ($validUntil !== '') : ?>
        <p><?= Html::encode(Module::t('This invitation is valid until {date}.', ['date' => $validUntil])) ?></p>
    <?php endif; ?>

    <p><?= Html::encode(Module::t('If you cannot click the link, copy it into your browser.')) ?></p>

    <p><?= Html::encode(Module::t('Regards,')) ?></p>

    <p><?= Html::encode($workspaceTitle) ?></p>

    <p><?= Html::encode(Module::t('Do not reply to this email. This mailbox is not monitored.')) ?></p>
</div>
