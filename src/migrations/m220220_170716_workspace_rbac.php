<?php


use yii\db\Migration;


class m220220_170716_workspace_rbac extends Migration
{
    public function up()
    {
        $auth = \Yii::$app->authManager;
        $role = \Yii::$app->setting->getValue('site::admin_role');
        $admin = (isset($role) && $role != '') ? $auth->getRole($role) : $auth->getRole('admin');

        $permissions = [
            'workspaceWebDefaultIndex' => 'View workspace page',
            'workspaceWebDefaultManage' => 'Manage workspace',
            'workspaceWebDefaultView' => 'View workspace information',
            'workspaceWebDefaultCreate' => 'Create workspace',
            'workspaceWebDefaultUpdate' => 'Update workspace information',
            'workspaceWebDefaultDelete' => 'Delete workspace',
            'workspaceWebDefaultAssignment' => 'View workspace assignment',
            'workspaceWebDefaultAssign' => 'Assign to workspace',
            'workspaceWebDefaultAssignUpdate' => 'Update workspace assignment',
            'workspaceWebDefaultRemove' => 'Remove workspace assignment',
            'workspaceWebDefaultSetWorkspace' => 'Workspace settings',
            'workspaceWorkspaceFindAll' => 'Find all workspaces',
            'workspaceWorkspaceFullAccess' => 'Full access to workspaces',
            'workspaceWebDefaultCreateInvitation' => 'Create invitation',
            'workspaceWebDefaultResendInvitation' => 'Resend invitation',
            'workspaceWebDefaultDeleteInvitation' => 'Delete invitation'
        ];
        
        
        foreach ($permissions as $permissionKey => $permissionDescription) {
            $permissionObject = $auth->createPermission($permissionKey);
            $permissionObject->description = $permissionDescription;
            $auth->add($permissionObject);
            $auth->addChild($admin, $permissionObject);
        }

        $workspaceWebDefaultSetWorkspace = $auth->getPermission('workspaceWebDefaultSetWorkspace');
        $auth->addChild($auth->getRole('user'), $workspaceWebDefaultSetWorkspace);
        


        
    }

    public function down()
    {
        $auth = \Yii::$app->authManager;
        
        $permissions = [
            'workspaceWebDefaultIndex',
            'workspaceWebDefaultView',
            'workspaceWebDefaultCreate',
            'workspaceWebDefaultUpdate',
            'workspaceWebDefaultDelete',
            'workspaceWebDefaultAssignment',
            'workspaceWebDefaultAssign',
            'workspaceWebDefaultAssignUpdate',
            'workspaceWebDefaultRemove',
            'workspaceWebDefaultSetWorkspace',
            'workspaceWorkspaceFindAll',
            'workspaceWorkspaceFullAccess',
        ];

        foreach ($permissions as $permission) {
            $auth->remove($auth->getPermission($permission));
        }
    }
}
