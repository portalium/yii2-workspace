<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use Yii;
use portalium\workspace\Module;

/**
 * This is the model class for table "workspace_invitation".
 *
 * @property int $id_invitation
 * @property int $id_workspace
 * @property string $invitation_token
 * @property int $id_user
 * @property string $date_create
 * @property string $date_expire
 */
class Invitation extends \yii\db\ActiveRecord
{
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
            [['id_workspace', 'invitation_token'], 'required'],
            [['id_workspace', 'id_user'], 'integer'],
            [['date_create', 'date_expire'], 'safe'],
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
            'invitation_token' => Module::t('Invitation Token'),
            'id_user' => Module::t('Id User'),
            'date_create' => Module::t('Date Create'),
            'date_expire' => Module::t('Date Expire'),
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
