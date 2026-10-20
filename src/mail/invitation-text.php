<?php

use portalium\workspace\Module;

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

$lines = [
    Module::t('Hello {email},', ['email' => $invitation->email]),
    '',
    ($inviterName !== null && $inviterName !== '')
        ? Module::t('{inviter} invited you to join the «{workspace_name}» workspace.', ['inviter' => $inviterName, 'workspace_name' => $workspaceTitle])
        : Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspaceTitle]),
    '',
];
if (!$hasAccount && $signupUrl !== null) {
    $lines[] = Module::t(
        'There is no account for {email} yet. First sign up with this address using the link below, then open the invitation link.',
        ['email' => $invitation->email]
    );
    $lines[] = $signupUrl;
    $lines[] = Module::t(
        'If you sign up with this address using the link above, your account is active right away. '
        . 'After you sign up, the invitation page opens by itself; click «Join» there.'
    );
    $lines[] = '';
} elseif (!$hasAccount) {
    $lines[] = Module::t(
        'There is no account for {email} yet. Ask your administrator to create one, then open the invitation link.',
        ['email' => $invitation->email]
    );
    $lines[] = '';
}
array_push(
    $lines,
    Module::t('Follow the link below to accept the invitation:'),
    $verifyLink,
    Module::t('This link only works for the account with the address {email}.', ['email' => $invitation->email]),
    $validUntil !== '' ? Module::t('This invitation is valid until {date}.', ['date' => $validUntil]) : '',
    '',
    Module::t('Regards,'),
    $workspaceTitle,
    '',
    Module::t('Do not reply to this email. This mailbox is not monitored.')
);

echo implode("\n", $lines) . "\n";
