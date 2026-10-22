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
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
        ];
    }

    public function extraFields()
    {
        return ['workspace', 'user'];
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

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if (!$this->isNewRecord && $this->status == self::STATUS_ACTIVE) {
                $previousStatus = $this->getOldAttribute('status');
                if ($previousStatus == self::STATUS_INACTIVE) {
                    self::updateAll(['status' => self::STATUS_INACTIVE], ['id_user' => $this->id_user]);
                    $this->status = self::STATUS_ACTIVE;
                    parent::beforeSave($insert);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Önceden her sorguya gizli groupBy('id_workspace') ekleniyordu; bu, aynı çalışma alanındaki
     * satırları sessizce yutuyordu. Çalışma alanı başına tek satır isteyen sorgu groupBy'ı açıkça verir.
     */
    public static function find()
    {
        return parent::find();
    }

    public static function findNoGroupBy()
    {
        return parent::find();
    }

    /**
     * Users who have a row in any module of the workspace, id_user => username.
     */
    public static function memberUsernames($idWorkspace): array
    {
        return User::find()
            ->select(['username', 'id_user'])
            ->where(['id_user' => self::findNoGroupBy()->select('id_user')->where(['id_workspace' => (int) $idWorkspace])])
            ->orderBy(['username' => SORT_ASC])
            ->indexBy('id_user')
            ->column();
    }

    /**
     * Of the given users, the ids of those who are not members of the workspace.
     */
    public static function nonMemberIds($idWorkspace, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }
        $memberIds = self::findNoGroupBy()
            ->select('id_user')
            ->distinct()
            ->where(['id_workspace' => (int) $idWorkspace, 'id_user' => $userIds])
            ->column();

        return array_values(array_diff($userIds, array_map('intval', $memberIds)));
    }

    /**
     * Switches the user's active workspace: activates their rows in that workspace, deactivates
     * their rows in every other one. Does nothing if the user isn't a member of that workspace.
     */
    public static function activateWorkspace(int $idUser, int $idWorkspace): bool
    {
        $isMember = self::findNoGroupBy()->where(['id_user' => $idUser, 'id_workspace' => $idWorkspace])->exists();
        if (!$isMember) {
            return false;
        }
        self::getDb()->transaction(static function () use ($idUser, $idWorkspace) {
            self::updateAll(['status' => self::STATUS_INACTIVE], ['and', ['id_user' => $idUser], ['<>', 'id_workspace', $idWorkspace]]);
            self::updateAll(['status' => self::STATUS_ACTIVE], ['id_user' => $idUser, 'id_workspace' => $idWorkspace]);
        });

        return true;
    }
}
