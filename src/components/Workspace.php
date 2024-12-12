<?php

namespace portalium\workspace\components;

use portalium\base\Exception;
use Yii;
use yii\base\Component;
use portalium\workspace\models\WorkspaceUser;
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
            return $workspace->id_workspace;
        }
        $workspace = WorkspaceUser::find()
            ->where(['id_user' => Yii::$app->user->id])
            ->one();
        if ($workspace) {
            $workspace->status = WorkspaceUser::STATUS_ACTIVE;
            if ($workspace->save())
                return $workspace->id_workspace;
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
}
