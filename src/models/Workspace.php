<?php

namespace portalium\workspace\models;

use Yii;
use portalium\workspace\Module;
use portalium\workspace\models\WorkspaceUser;

/**
 * This is the model class for table "Workspace_workspace".
 *
 * @property int $id_workspace
 * @property string $name
 * @property string $date_create
 * @property string $date_update
 *
 * @property WorkspaceWorkspaceUser[] $workspaceWorkspaceUsers
 */
class Workspace extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'workspace';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['date_create', 'date_update'], 'safe'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_workspace' => Module::t('Id Workspace'),
            'name' => Module::t('Name'),
            'date_create' => Module::t('Date Create'),
            'date_update' => Module::t('Date Update'),
        ];
    }

    /**
     * Gets query for [[WorkspaceWorkspaceUsers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getWorkspaceUsers()
    {
        return $this->hasMany(WorkspaceUser::class, ['id_workspace' => 'id_workspace'])->groupBy('');
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $workspaceUser = new WorkspaceUser();
            $workspaceUser->id_workspace = $this->id_workspace;
            $workspaceUser->id_user = Yii::$app->user->id;
            $workspaceUser->role = 'admin';
            $activeWorkspaceId = WorkspaceUser::getActiveWorkspaceId();
            if ($activeWorkspaceId) {
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
            } else {
                $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
            }
            if (!$workspaceUser->save()) {

            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    public static function find()
    {
        $query = parent::find();

        if (!Yii::$app->user->can('workspaceWorkspaceFindAll', ['id_module' => 'workspace'])) {
            $query->innerJoinWith('workspaceUsers');
            $query->andWhere([Module::$tablePrefix . 'workspace_user.id_user' => Yii::$app->user->id]);
        }

        return $query;
    }
    
}
