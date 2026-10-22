<?php

use portalium\theme\widgets\Alert;
use portalium\theme\widgets\Panel;
use portalium\workspace\bundles\InvitationAsset;
use portalium\workspace\components\InvitationAcceptance;
use portalium\workspace\components\InvitationSignup;
use portalium\workspace\Module;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $token */
/** @var string $sig e-postadaki bağlantının geçerli imzası; imzasız (kopyalanan) bağlantıda '' */
/** @var array $outcome InvitationAcceptance sonucu (inspect ya da reddedilen accept) */
/** @var portalium\user\models\User|null $user oturum yoksa null */
/** @var string|null $message kabul edilemiyorsa ne olduğu ve ne yapılacağı */
/** @var string|null $changedNotice [Katıl]'dan önce roller değiştiyse uyarı (sayfa güncel rollerle) */
/** @var string $fingerprint gösterilen bekleyen rollerin parmak izi; [Katıl] formuyla geri gelir */
/** @var array{workspace:string, masked_email:string}|null $signupHint misafire kayıt ipucu (maskeli adres) */
/** @var portalium\workspace\models\Invitation|null $invitation */
/** @var portalium\workspace\models\Workspace|null $workspace */
/** @var string[] $roles henüz kabul edilmemiş roller ("Modül: rol") */
/** @var bool $canSignup */

$sig = $sig ?? '';
$signed = $sig === '' ? [] : [InvitationSignup::SIGNATURE_PARAM => $sig];
$result = $outcome['result'];
$ready = $result === InvitationAcceptance::READY;
$workspaceTitle = $workspace === null ? null : $workspace->displayTitle();
$this->title = Module::t('Workspace invitation');
InvitationAsset::register($this);

$alertTypes = [
    InvitationAcceptance::ALREADY_ACCEPTED => 'info',
    InvitationAcceptance::NOT_FOUND => 'danger',
    InvitationAcceptance::FAILED => 'danger',
];

$buttons = [];
$form = null;
if ($user === null) {
    $buttons[] = Html::a(Html::encode(Module::t('Sign in')), ['/auth/default/login'], ['class' => 'btn btn-success', 'id' => 'ws-invitation-login']);
    if ($ready && $canSignup) {
        $buttons[] = Html::a(
            Html::encode(Module::t('Sign up')),
            array_merge(['/auth/default/signup', 'invitation' => $token], $signed),
            ['class' => 'btn btn-success', 'id' => 'ws-invitation-signup']
        );
    }
} elseif ($ready) {
    $form = ['action' => ['accept', 'token' => $token], 'id' => 'ws-invitation-join'];
    $buttons[] = Html::submitButton(Html::encode(Module::t('Join')), ['class' => 'btn btn-success']);
    $buttons[] = Html::a(Html::encode(Module::t('Cancel')), ['/'], ['class' => 'btn btn-warning', 'id' => 'ws-invitation-cancel']);
} else {
    if ($result === InvitationAcceptance::WRONG_ACCOUNT) {
        $form = ['action' => array_merge(['switch-account', 'token' => $token], $signed), 'id' => 'ws-invitation-switch-account'];
        $buttons[] = Html::submitButton(Html::encode(Module::t('Sign out and continue with the right account')), ['class' => 'btn btn-warning']);
    }
    $buttons[] = Html::a(Html::encode(Module::t('Go to my workspaces')), ['/workspace/default/index'], ['class' => 'btn btn-info', 'id' => 'ws-invitation-home']);
}
?>
<div class="workspace-invitation-accept ws-invitation-accept" data-result="<?= Html::encode($result) ?>">
    <?php
    if ($form !== null) {
        echo Html::beginForm($form['action'], 'post', ['id' => $form['id']]);
    }
    Panel::begin([
        'title' => Html::encode($this->title),
        'actions' => ['footer' => $buttons],
    ]);
    ?>

    <?php if ($form !== null && $ready) : ?>
        <?= Html::hiddenInput('shown_roles', $fingerprint) ?>
    <?php endif; ?>
    <?php if ($ready && $changedNotice !== null) : ?>
        <?= Alert::widget([
            'body' => Html::encode($changedNotice),
            'closeButton' => false,
            'options' => ['id' => 'ws-invitation-changed', 'class' => 'alert-warning', 'role' => 'alert'],
        ]) ?>
    <?php endif; ?>
    <?php if ($ready && $workspaceTitle !== null) : ?>
        <p class="lead"><?= Html::encode(Module::t('Do you want to join the «{workspace}» workspace?', ['workspace' => $workspaceTitle])) ?></p>
        <?php if ($user !== null) : ?>
            <p id="ws-invitation-switch-note"><?= Html::encode(Module::t('When you join, you switch to this workspace.')) ?></p>
        <?php endif; ?>
        <dl class="row mb-0" id="ws-invitation-details">
            <?php if ($invitation !== null && $invitation->inviterName() !== null) : ?>
                <dt class="col-sm-3"><?= Html::encode(Module::t('Invited by')) ?></dt>
                <dd class="col-sm-9"><?= Html::encode($invitation->inviterName()) ?></dd>
            <?php endif; ?>
            <dt class="col-sm-3"><?= Html::encode(Module::t('Roles')) ?></dt>
            <dd class="col-sm-9">
                <ul class="list-unstyled mb-0" id="ws-invitation-roles">
                    <?php foreach ($roles as $role) : ?>
                        <li><?= Html::encode($role) ?></li>
                    <?php endforeach; ?>
                </ul>
            </dd>
            <?php if ($invitation !== null) : ?>
                <dt class="col-sm-3"><?= Html::encode(Module::t('Valid until')) ?></dt>
                <dd class="col-sm-9"><?= Html::encode(substr((string) $invitation->date_expire, 0, 16)) ?></dd>
            <?php endif; ?>
        </dl>
        <?php if ($user === null) : ?>
            <p class="mt-3 mb-0" id="ws-invitation-guest-hint">
                <?php
                if ($canSignup && $signupHint !== null && $sig !== '') {
                    echo Html::encode(Module::t(
                        'To join, sign in. If you do not have an account yet, sign up with the address the invitation was sent to ({email}); '
                        . 'your account will be active right away.',
                        ['email' => $signupHint['masked_email']]
                    ));
                } elseif ($canSignup && $signupHint !== null) {
                    echo Html::encode(Module::t(
                        'To join, sign in. If you do not have an account yet, sign up with the address the invitation was sent to ({email}) '
                        . 'using the link in the invitation email; then your account will be active right away.',
                        ['email' => $signupHint['masked_email']]
                    ));
                } else {
                    echo Html::encode($canSignup
                        ? Module::t('To join, sign in. If you do not have an account yet, sign up with the email address the invitation was sent to.')
                        : Module::t('To join, sign in. If you do not have an account yet, ask your administrator to create one.'));
                }
                ?>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <?php if ($workspaceTitle !== null) : ?>
            <p><?= Html::encode(Module::t('Workspace: {workspace}', ['workspace' => $workspaceTitle])) ?></p>
        <?php endif; ?>
        <?= Alert::widget([
            'body' => Html::encode((string) $message),
            'closeButton' => false,
            'options' => [
                'id' => 'ws-invitation-result',
                'class' => 'alert-' . ($alertTypes[$result] ?? 'warning') . ' mb-0',
                'role' => 'alert',
            ],
        ]) ?>
    <?php endif; ?>

    <?php
    Panel::end();
    if ($form !== null) {
        echo Html::endForm();
    }
    ?>
</div>
