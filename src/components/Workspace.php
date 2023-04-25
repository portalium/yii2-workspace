<?php

namespace portalium\workspace\components;

use Yii;
use yii\base\Component;
use portalium\workspace\models\WorkspaceUser;

class Workspace extends Component
{
    
    public function checkOwner($id_workspace)
    {
        $activeWorkspaceId = WorkspaceUser::getActiveWorkspaceId();
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

    public static function getAvailableRoles()
    {
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        if (isset($availableRoles['mhsb'])) {
            $availableRoles = $availableRoles['mhsb'];
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

    public function getSupportModules(){
        $allModulesId = Yii::$app->getModules();
        $supportWorkspaceModules = [];
        
        foreach ($allModulesId as $key => $value) {
            if (isset(Yii::$app->getModule($key)->className()::$supportWorkspace) && Yii::$app->getModule($key)->className()::$supportWorkspace) {
                $supportWorkspaceModules[$key] = Yii::$app->getModule($key)->className()::$supportWorkspace;
            }
        }

        return $supportWorkspaceModules;
    }

}
