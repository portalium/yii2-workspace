<?php

namespace portalium\workspace\models;

use Yii;
use portalium\workspace\Module;
use portalium\workspace\models\WorkspaceUser;
use portalium\base\Event;

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

    public function init()
    {
        $this->on(self::EVENT_AFTER_INSERT, function($event) {
            \Yii::$app->trigger(Module::EVENT_WORKSPACE_CREATE_AFTER, new Event(['payload' => $event->data]));
            Event::trigger(Yii::$app->getModules(), Module::EVENT_WORKSPACE_CREATE_AFTER, new Event(['payload' => $event->data]));
        }, $this);
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
            $supportModules = Yii::$app->workspace->getSupportModules();
            foreach ($supportModules as $key => $module) {
                $workspaceUser = new WorkspaceUser();
                $workspaceUser->id_workspace = $this->id_workspace;
                $workspaceUser->id_user = Yii::$app->user->id;
                $workspaceUser->role = Yii::$app->setting->getValue($key . '::workspace::admin_role');
                $workspaceUser->id_module = $key;
                $activeWorkspaceId = Yii::$app->workspace->id;
                if ($activeWorkspaceId) {
                    $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
                } else {
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                }
                if (!$workspaceUser->save()) {
                    
                }
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
