<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Invitation $invitation */
/** @var portalium\workspace\models\Workspace $workspace */

$verifyLink = Yii::$app->urlManager->createAbsoluteUrl(['workspace/invitation/accept', 'token' => $invitation->invitation->invitation_token]);
?>
<div class="invitation-html">
    <p>Hello <?= Html::encode($invitation->email) ?>,</p>

    <p>You have been invited to join <?= Html::encode($workspace->name) ?> workspace.</p>

    <p>Follow the link below to accept invitation:</p>

    <p><?= Html::a(Html::encode($verifyLink), $verifyLink) ?></p>

    <p>If you cannot click the link, please try pasting the text into your browser.</p>

    <p>Regards,</p>

    <p><?= Html::encode($workspace->name) ?> workspace</p>

    <p>Do not reply to this email. This mailbox is not monitored and you will not receive a response.</p>
</div>