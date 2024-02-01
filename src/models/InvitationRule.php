<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use Yii;
use portalium\workspace\Module;
/* 
'id_invitation' => $this->primaryKey(11)->notNull(),
'id_workspace' => $this->integer(11)->notNull(),
'email' => $this->string(255)->notNull(),
'module' => $this->string(32)->notNull(),
'role' => $this->string(32)->notNull(),
'invitation_token' => $this->string(255)->notNull(),
'date_create' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP"),
'date_expire' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP")
*/
/**
 * This is the model class for table "workspace_invitation".
 *
 * @property int $id_invitation
 * @property int $id_workspace
 * @property string $email
 * @property string $module
 * @property string $role
 * @property string $invitation_token
 * @property int $status
 * @property int $id_user
 * @property string $date_create
 * @property string $date_expire
 */
class Invitation extends \yii\db\ActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_ACCEPTED = 1;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'invitation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_workspace', 'email', 'role', 'invitation_token'], 'required'],
            [['id_workspace', 'status', 'id_user'], 'integer'],
            [['date_create', 'date_expire'], 'safe'],
            [['role', 'module'], 'string'],
            [['email'], 'email'],
            [['invitation_token'], 'string', 'max' => 255],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['id_user' => 'id_user']]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_invitation' => Module::t('Id Invitation'),
            'id_workspace' => Module::t('Id Workspace'),
            'email' => Module::t('Email'),
            'module' => Module::t('Module'),
            'role' => Module::t('Role'),
            'invitation_token' => Module::t('Invitation Token'),
            'status' => Module::t('Status'),
            'id_user' => Module::t('Id User'),
            'date_create' => Module::t('Date Create'),
            'date_expire' => Module::t('Date Expire'),
        ];
    }

    public function sendInvitation()
    {
        if ($this->invitation_token == null) {
            $this->invitation_token = Yii::$app->security->generateRandomString();
            $this->date_expire = date('Y-m-d H:i:s', strtotime('+1 month'));
            $this->save();
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
            $verifyLink = Yii::$app->urlManager->createAbsoluteUrl(['workspace/invitation/accept', 'token' => $this->invitation_token]);
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
    public function getUser()
    {
        return $this->hasOne(User::class, ['id_user' => 'id_user']);
    }
}
