<?php

namespace portalium\workspace\components;

use portalium\base\Exception;
use portalium\workspace\models\Workspace as ModelsWorkspace;
use Yii;
use yii\base\Component;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\models\Workspace as WorkspaceModel;
use portalium\workspace\Module;

class Workspace extends Component
{
    public function checkOwner($id_workspace)
    {
        $activeWorkspaceId = Yii::$app->workspace->id;
        if (Yii::$app->user->can('workspaceWorkspaceFullAccess', ['id_module' => 'workspace'])) {
            return true;
        }

        if ($activeWorkspaceId) {
            if ($id_workspace == $activeWorkspaceId) {
                return true;
            }
        }
        return false;
    }

    public static function getAvailableRoles($params = [])
    {
        $module = isset($params['module']) ? $params['module'] : null;
        if (!$module) {
            return [];
        }
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        if (isset($availableRoles[$module])) {
            $availableRoles = $availableRoles[$module];
        } else {
            $availableRoles = [];
        }
        $roles = [];
        foreach (Yii::$app->authManager->getRoles() as $role) {
            if (in_array($role->name, $availableRoles)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    public function getSupportModules()
    {
        $allModulesId = Yii::$app->getModules();
        $supportWorkspaceModules = [];

        foreach ($allModulesId as $key => $value) {
            if (isset(Yii::$app->getModule($key)->className()::$supportWorkspace) && Yii::$app->getModule($key)->className()::$supportWorkspace) {
                $supportWorkspaceModules[$key] = Yii::$app->getModule($key)->className()::$supportWorkspace;
            }
        }

        return $supportWorkspaceModules;
    }

    public function getId()
    {
        $workspace = WorkspaceUser::find()
            ->where(['id_user' => Yii::$app->user->id])
            ->andWhere(['status' => WorkspaceUser::STATUS_ACTIVE])
            ->one();
        if ($workspace) {
            if (Yii::$app->session->get('active_workspace_id', null) != null) {
                $activeWorkspaceId = Yii::$app->session->get('active_workspace_id', null);
                $sessionWorkspace = WorkspaceUser::find()
                    ->where(['id_user' => Yii::$app->user->id])
                    ->andWhere(['id_workspace' => $activeWorkspaceId])
                    ->one();
                if ($sessionWorkspace) {
                    return $sessionWorkspace->id_workspace;
                }
            }
            Yii::$app->session->set('active_workspace_id', $workspace->id_workspace);
            return $workspace->id_workspace;
        }
        $workspace = WorkspaceUser::find()
            ->where(['id_user' => Yii::$app->user->id])
            ->one();
        if ($workspace) {
            $workspace->status = WorkspaceUser::STATUS_ACTIVE;
            Yii::$app->session->set('active_workspace_id', $workspace->id_workspace);
            if ($workspace->save())
                return $workspace->id_workspace;
        }
        return null;
    }

    public function getId_user()
    {
        $id = $this->getId();
        if ($id) {
            $workspace = WorkspaceModel::findOne($id);
            if ($workspace) {
                return $workspace->id_user;
            }
        }
        return null;
    }

    public function checkSupportRoles()
    {
        $supportWorkspaceModules = $this->getSupportModules();

        foreach ($supportWorkspaceModules as $key => $value) {
            try {
                $role = Yii::$app->setting->getValue($key . '::workspace::admin_role');
                $defaultRole = Yii::$app->setting->getValue($key . '::workspace::default_role');
                if (!$role || !$defaultRole) {

                    return false;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return true;
    }

    public function can($module, $permission, $params = [])
    {
        $workspaceRoles = WorkspaceUser::find()
            ->where(['id_workspace' => Yii::$app->workspace->id, 'id_user' => Yii::$app->user->id, 'id_module' => $module])->groupBy('role')->all();
        if (!$workspaceRoles) {
            return false;
        }

        if (isset($params['model']) && $params['model']->id_workspace != Yii::$app->workspace->id) {
            return false;
        }

        foreach ($workspaceRoles as $workspaceRole) {
            $auth = Yii::$app->authManager;
            $role = $auth->getRole($workspaceRole->role);
            if (!$role) {
                continue;
            }

            $permissions = $auth->getPermissionsByRole($role->name);

            $workspacePermissions = $workspaceRole->workspace->permissions;

            if (!empty($workspacePermissions)) {
                foreach ($workspacePermissions as $workspacePermission) {
                    $permissionModel = $auth->getPermission($workspacePermission);
                    if ($permissionModel) {
                        $permissions[$permissionModel->name] = $permissionModel;
                    }
                }
            }
            if (isset($permissions[$permission])) {
                return true;
            }

            $childRoles = $auth->getChildRoles($role->name);
            foreach ($childRoles as $childRole) {
                $permissions = $auth->getPermissionsByRole($childRole->name);
                if (isset($permissions[$permission])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isAvailableRole($module, $role)
    {
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        if (isset($availableRoles[$module])) {
            $availableRoles = $availableRoles[$module];
        } else {
            $availableRoles = [];
        }
        if (in_array($role, $availableRoles)) {
            return true;
        }
        return false;
    }

    public function getJoinedWorkspaces()
    {
        $subQuery = (new \yii\db\Query())
            ->select('MAX(wu2.status)')
            ->from(WorkspaceUser::tableName() . ' wu2')
            ->where('wu2.id_user = wu.id_user AND wu2.id_workspace = wu.id_workspace');
        $workspaces = WorkspaceUser::find()
            ->alias('wu')
            ->where(['wu.id_user' => Yii::$app->user->id])
            ->andWhere(['wu.status' => $subQuery])
            ->groupBy('wu.id_workspace')
            ->with('workspace')
            ->all();
        return $workspaces;
        
    }

    public function isMember($id_workspace, $id_user = null)
    {
        if ($id_user === null) {
            $id_user = Yii::$app->user->id;
        }

        $workspaceUser = WorkspaceUser::find()
            ->where(['id_user' => $id_user, 'id_workspace' => $id_workspace])
            ->one();
        if ($workspaceUser) {
            return true;
        }
        return false;
    }

    public function set($id_workspace)
    {
        $workspaceUserModel = WorkspaceUser::findOne(['id_workspace_user' => $id_workspace, 'id_user' => Yii::$app->user->id]);
        if ($id_workspace == 0 || !$workspaceUserModel) {
            Yii::$app->session->addFlash('error', Module::t('You are not allowed to set this workspace.'));
            // throw new \yii\web\ForbiddenHttpException(Module::t('You are not allossswed to access this page.'));
            return false;
        }
        if (!\Yii::$app->user->can('workspaceWebDefaultSetWorkspace', ['id_module' => 'workspace', 'model' => ModelsWorkspace::findOne(['id_workspace' => $workspaceUserModel->id_workspace])])) {
            // throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
            return false;
        }

        $workspaceUsers = WorkspaceUser::find(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])->groupBy('id_workspace_user')->all();
        if ($workspaceUsers) {
            foreach ($workspaceUsers as $workspaceUser) {
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
                $workspaceUser->save();
            }
        }
        $workspaceUser = WorkspaceUser::findOne(['id_workspace_user' => $id_workspace]);
        if ($workspaceUser) {
            $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
            $workspaceUser->save();
            return true;
        }
        return false;
    }
}
