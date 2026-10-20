<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use portalium\workspace\components\InvitationSignup;
use Yii;
use yii\helpers\Html;
use portalium\workspace\Module;

/**
 * This is the model class for table "workspace_invitation_role".
 *
 * @property int $id_invitation_role
 * @property string $id_invitation
 * @property int $id_workspace
 * @property string $email
 * @property string $module
 * @property string $role
 * @property int $status
 */
class InvitationRole extends \yii\db\ActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_REJECTED = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'invitation_role';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_workspace', 'email', 'role'], 'required'],
            [['id_workspace', 'status'], 'integer'],
            [['role', 'module'], 'string'],
            [['email'], 'email'],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_invitation_role' => Module::t('Id Invitation Rule'),
            'id_invitation' => Module::t('Id Invitation'),
            'id_workspace' => Module::t('Id Workspace'),
            'email' => Module::t('Email'),
            'module' => Module::t('Module'),
            'role' => Module::t('Role'),
            'status' => Module::t('Status'),
        ];
    }

    /**
     * Davetin bağlantısı yoksa üretir, süresi geçmişse uzatır ve adrese e-postayı gönderir.
     */
    public function sendInvitation()
    {
        $invitation = $this->invitation;
        if ($invitation === null || !$invitation->prepareForSending()) {
            return false;
        }
        return (bool) $this->sendEmail();
    }

    /**
     * Davet e-postası. Kabul ve kayıt bağlantıları davetin imzasını (sig) taşır (karar 35/49); imzalı kayıt bağlantısı
     * auth::web_signup kapalıyken de gönderilir (karar 40: imzalı davetle kayıt bu ayardan muaf). Sırrı olmayan
     * davette (kolon yok) bağlantılar imzasızdır; kayıt bağlantısı o zaman yalnız kayıt açıkken gönderilir. Bildirimdeki
     * bağlantı (yalnız hesabı olan alıcı görür) imzasızdır; giriş yapmış kullanıcı için imza gerekmez.
     */
    public function sendEmail()
    {
        $workspace = Workspace::findOne($this->id_workspace);

        if ($workspace === null) {
            return false;
        }

        Yii::$app->site->mailer->setViewPath(Yii::getAlias('@portalium/workspace/mail'));
        $user = User::findOne(['email' => $this->email]);

        if ($user && Yii::$app->has('notification')) {
            $verifyLink = Yii::$app->urlManager->createAbsoluteUrl(['workspace/invitation/accept', 'token' => $this->invitation->invitation_token]);
            Yii::$app->notification->addNotification(
                $user->id_user,
                Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspace->displayTitle()]),
                Html::a(Html::encode(Module::t('Click to accept the invitation')), $verifyLink)
            );
        }
        $signature = InvitationSignup::signature($this->invitation);
        $signed = $signature === null ? [] : [InvitationSignup::SIGNATURE_PARAM => $signature];
        $acceptUrl = Yii::$app->urlManager->createAbsoluteUrl(
            array_merge(['workspace/invitation/accept', 'token' => $this->invitation->invitation_token], $signed)
        );
        $signupUrl = ($user === null && ($signature !== null || Yii::$app->setting->getValue('auth::web_signup')))
            ? Yii::$app->urlManager->createAbsoluteUrl(
                array_merge(['/auth/default/signup', 'invitation' => $this->invitation->invitation_token], $signed)
            )
            : null;

        return Yii::$app
            ->site
            ->mailer
            ->compose(
                ['html' => 'invitation-html', 'text' => 'invitation-text'],
                [
                    'workspace' => $workspace,
                    'invitation' => $this,
                    'hasAccount' => $user !== null,
                    'acceptUrl' => $acceptUrl,
                    'signupUrl' => $signupUrl,
                    'inviterName' => $this->invitation->inviterName(),
                ]
            )

            ->setFrom([Yii::$app->setting->getValue('email::address') => Yii::$app->setting->getValue('email::displayname')])
            ->setTo($this->email)
            ->setSubject(Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspace->displayTitle()]))
            ->send();
    }

    public function accept()
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->save();
    }

    public function reject()
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }

    public static function getStatusList()
    {
        return [
            self::STATUS_PENDING => Module::t('Pending'),
            self::STATUS_ACCEPTED => Module::t('Accepted'),
            self::STATUS_REJECTED => Module::t('Rejected'),
        ];
    }

    /** 
     * {@inheritdoc}
     */
    public function getWorkspace()
    {
        return $this->hasOne(Workspace::class, ['id_workspace' => 'id_workspace']);
    }

    /** 
     * {@inheritdoc}
     */
    public function getInvitation()
    {
        return $this->hasOne(Invitation::class, ['id_invitation' => 'id_invitation']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['email' => 'email']);
    }
}
