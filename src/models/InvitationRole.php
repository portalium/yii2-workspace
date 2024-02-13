<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use Yii;
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

    public function sendInvitation()
    {
        if ($this->invitation->invitation_token == null) {
            $this->invitation->invitation_token = Yii::$app->security->generateRandomString();
            $this->invitation->date_expire = date('Y-m-d H:i:s', strtotime('+1 month'));
            $this->invitation->save();
        }
        $this->sendEmail();
    }

    public function sendEmail()
    {
        $workspace = Workspace::findOne($this->id_workspace);

        if ($workspace === null) {
            return false;
        }

        Yii::$app->site->mailer->setViewPath(Yii::getAlias('@portalium/workspace/mail'));
        $user = User::findOne(['email' => $this->email]);
        
        if ($user){
            $verifyLink = Yii::$app->urlManager->createAbsoluteUrl(['workspace/invitation/accept', 'token' => $this->invitation->invitation_token]);
            Yii::$app->notification->addNotification($user->id_user, Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspace->name]), '<a href="'.$verifyLink.'">Please click if accept invitation</a>');
        }

        return Yii::$app
            ->site
            ->mailer
            ->compose(
                ['html' => 'invitation-html', 'text' => 'invitation-text'],
                ['workspace' => $workspace, 'invitation' => $this]
            )

            ->setFrom([Yii::$app->setting->getValue('email::address') => Yii::$app->setting->getValue('email::displayname')])
            ->setTo($this->email)
            ->setSubject(Module::t('You have been invited to join {workspace_name} workspace.', ['workspace_name' => $workspace->name]))
            ->send();
    }

    public function accept()
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->save();
    }

    public static function getStatusList()
    {
        return [
            self::STATUS_PENDING => Module::t('Pending'),
            self::STATUS_ACCEPTED => Module::t('Accepted'),
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
}
