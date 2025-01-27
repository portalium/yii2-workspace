<?php

namespace portalium\workspace\models;

use portalium\site\models\Setting;
use Yii;
use portalium\workspace\Module;
use portalium\workspace\models\WorkspaceUser;
use portalium\base\Event;
use portalium\user\models\User;

/**
 * This is the model class for table "Workspace_workspace".
 *
 * @property int $id_workspace
 * @property string $title
 * @property string $name
 * @property string $id_user
 * @property string $date_create
 * @property string $date_update
 *
 * @property WorkspaceWorkspaceUser[] $workspaceWorkspaceUsers
 */
class Workspace extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'workspace';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['id_user'], 'integer'],
            [['date_create', 'date_update'], 'safe'],
            [['title'], 'string', 'max' => 255],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['id_user' => 'id_user']],
            [['name'], 'string', 'max' => 64],
            [['name'], 'unique'],
            [['name'], 'match', 'pattern' => '/^[a-z-0-9]+(?:-[a-z-0-9]+)*$/', 'message' => Module::t('Only word characters and dashes are allowed.')],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['date_create', 'date_update'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['date_update'],
                ],
                'value' => new \yii\db\Expression('NOW()'),
            ]
        ];
    }

    public function init()
    {
        $this->on(self::EVENT_AFTER_INSERT, function ($event) {
            \Yii::$app->trigger(Module::EVENT_WORKSPACE_CREATE_AFTER, new Event(['payload' => $event->data]));
            Event::trigger(Yii::$app->getModules(), Module::EVENT_WORKSPACE_CREATE_AFTER, new Event(['payload' => $event->data]));
        }, $this);
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'id_workspace' => Module::t('Id Workspace'),
            'title' => Module::t('Title'),
            'name' => Module::t('Name'),
            'id_user' => Module::t('Id User'),
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
     * Gets the workspace permissions.
     *
     * @return array
     */
    public function getPermissions()
    {
        return $this->hasMany(WorkspacePermission::class, ['id_workspace' => 'id_workspace'])->groupBy('permission')->select('permission')->column();
    }

    /**
     * Generates a name for the workspace.
     *
     * @return string
     */
    public function generateName()
    {
        $name = $this->title;
        $name = preg_replace('/[^A-Za-z0-9-]+/', '_', $name);
        $name = strtolower($name);
        $name = trim($name, '-');
        $name = preg_replace('/-+/', '-', $name);
        if (Workspace::find()->where(['name' => $name, 'id_user' => $this->id_user])->exists()) {
            $name = $name . '-' . Yii::$app->security->generateRandomString(5);
        }
        return $name;
    }


    /**
     * Handles actions after a workspace is saved.
     *
     * If a new workspace is created, it assigns users to support modules based on their roles.
     * Sets user status to active or inactive depending on the active workspace ID.
     *
     * @param bool $insert Indicates if the model is newly created.
     * @param array $changedAttributes The old values of the modified attributes.
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $supportModules = Yii::$app->workspace->getSupportModules();
            foreach ($supportModules as $key => $module) {
                $workspaceUser = new WorkspaceUser();
                $workspaceUser->id_workspace = $this->id_workspace;
                $workspaceUser->id_user = Yii::$app->user->id;
                if (!Setting::find()->where(['name' => $key . '::workspace::admin_role'])->exists()) {
                    continue;
                }
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

    /**
     * Deletes all invitations of a workspace.
     */
    public function deleteInvitations()
    {
        $invitations = Invitation::find()->where(['id_workspace' => $this->id_workspace])->all();
        foreach ($invitations as $invitation) {
            $invitation->delete();
        }
    }

    /**
     * Deletes all workspace users of a workspace.
     */
    public function deleteWorkspaceUsers()
    {
        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace' => $this->id_workspace])->all();
        foreach ($workspaceUsers as $workspaceUser) {
            $workspaceUser->delete();
        }
    }

    /**
     * Deletes all invitations and workspace users before deleting a workspace.
     *
     * @return bool
     */
    public function beforeDelete()
    {
        $this->deleteInvitations();
        $this->deleteWorkspaceUsers();
        return parent::beforeDelete();
    }
}
