<?php

namespace portalium\workspace\rbac;

use Yii;
use portalium\workspace\models\WorkspaceUser;
use yii\rbac\Rule;

class WorkspaceCheckRule extends Rule
{
    public $name = 'WorkspaceCheckRule';
    
    public function execute($user, $item, $params)
    {
        if(Yii::$app->user->can('admin'))
            return true;

        $activeWorkspaceId = WorkspaceUser::getActiveWorkspaceId();
        if (!$activeWorkspaceId)
            return false;

        $permission = $item->name;
        $id_user = Yii::$app->user->id;
        $module = $params['id_module'];
        
        $hasPermission = $this->checkAccess($activeWorkspaceId, $permission, $id_user, $module);

        return $hasPermission; // kullanıcının organizasyonda belirtilen izne sahip olup olmadığına göre true veya false döndür
    }

    protected function checkAccess($activeWorkspaceId, $permission, $id_user, $module)
    {
        $auth = Yii::$app->authManager;

        $checkWorkspaceUser = WorkspaceUser::find()
            ->where([
                'id_workspace' => $activeWorkspaceId,
                'id_user' => $id_user,
                'id_module' => $module,
            ])
            ->all();

        if (!$checkWorkspaceUser) {
            return false;
        }
        foreach ($checkWorkspaceUser as $workspaceUser) {
                $role = $auth->getRole($workspaceUser->role);
                $permission = $auth->getPermission($permission);
                if ($auth->hasChild($role, $permission)) {
                    return true;            
                }
        }

        return false;
    }
    
}
