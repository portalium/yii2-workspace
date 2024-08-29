<?php

namespace portalium\workspace\models;

use Yii;
use portalium\user\models\User;
use portalium\workspace\models\Workspace;
use portalium\workspace\Module;
use yii\rbac\Item;

/**
 * This is the model class for table "workspace_workspace_user".
 *
 * @property int $id_workspace_user
 * @property int $id_workspace
 * @property int $id_user
 * @property string $permission
 *
 * @property Workspace $workspace
 * @property User $user
 */
class WorkspacePermission extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'workspace_permission';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_workspace', 'permission'], 'required'],
            [['id_user', 'id_workspace'], 'integer'],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['id_user' => 'id_user']],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_workspace_permission' => Module::t('Id Workspace Permission'),
            'id_user' => Module::t('Id User'),
            'id_workspace' => Module::t('Id Workspace'),
            'permission' => Module::t('Permission')
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
}
