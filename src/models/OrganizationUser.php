<?php

namespace portalium\workspace\models;

use Yii;
use portalium\user\models\User;
use portalium\workspace\models\Workspace;
use portalium\workspace\Module;

/**
 * This is the model class for table "workspace_workspace_user".
 *
 * @property int $id_workspace_user
 * @property int $id_user
 * @property int $id_workspace
 * @property string $role
 * @property int $status
 * @property string $id_module
 *
 * @property Workspace $workspace
 * @property User $user
 */
class WorkspaceUser extends \yii\db\ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'workspace_user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_workspace', 'role', 'status'], 'required'],
            [['id_user', 'id_workspace', 'status'], 'integer'],
            [['role', 'id_module'], 'string', 'max' => 32],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['id_user' => 'id_user']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_workspace_user' => Module::t('Id Workspace User'),
            'id_user' => Module::t('Id User'),
            'id_workspace' => Module::t('Id Workspace'),
            'role' => Module::t('Role'),
            'status' => Module::t('Status'),
            'id_module' => Module::t('Id Module'),
        ];
    }

    /**
     * Gets query for [[Workspace]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getWorkspace()
    {
        return $this->hasOne(Workspace::class, ['id_workspace' => 'id_workspace']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id_user' => 'id_user']);
    }

    /**
     * {@inheritdoc}
     * getActiveWorkspaceId
     */
    public static function getActiveWorkspaceId()
    {
        $workspace = WorkspaceUser::find()
            ->where(['id_user' => Yii::$app->user->id])
            ->andWhere(['status' => WorkspaceUser::STATUS_ACTIVE])
            ->one();
        if ($workspace) {
            return $workspace->id_workspace;
        }
        return null;
    }

    public static function find()
    {
        $query = parent::find();

        $query->groupBy('id_workspace');
        return $query;
    }
}
