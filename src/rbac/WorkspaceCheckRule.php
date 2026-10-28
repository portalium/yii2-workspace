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
        if(Yii::$app->user->can('workspaceWorkspaceFullAccess'))
            return true;

        $activeWorkspaceId = Yii::$app->workspace->id;
        if (!$activeWorkspaceId)
            return false;

        $permission = $item->name;
        $id_user = Yii::$app->user->id;
        $module = $params['id_module'] ?? null;
        $hasPermission = false;
        if ($module == 'storage')
            $hasPermission = $this->checkAccess($activeWorkspaceId, $permission, $id_user, $module);
        else if($module == 'workspace')
        {
            $hasPermission = $this->checkAccessWorkspace($activeWorkspaceId, $permission, $id_user, $module, $params);
        }
        return $hasPermission; // true/false depending on whether the user has the given permission in the workspace
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
    
    protected function checkAccessWorkspace($activeWorkspaceId, $permission, $id_user, $module, $params)
    {
        $auth = Yii::$app->authManager;
        if (!isset($params['model'])){
            return false;
        }
        $model = $params['model'];
        if ($model->id_user == $id_user)
        {
            return true;
        }
        if(Yii::$app->workspace->can('workspace',$permission) && $activeWorkspaceId == $model->id)
        {
            return true;
        }

        return false;
    }
}
