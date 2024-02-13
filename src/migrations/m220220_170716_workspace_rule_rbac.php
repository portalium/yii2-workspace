<?php

use yii\db\Migration;

class m220220_170716_workspace_rule_rbac extends Migration
{
    public function up()
    {
        $auth = \Yii::$app->authManager;

        $rule = new portalium\workspace\rbac\WorkspaceCheckRule();
        $auth->add($rule);

        $role = \Yii::$app->setting->getValue('site::admin_role');
        $admin = (isset($role) && $role != '') ? $auth->getRole($role) : $auth->getRole('admin');
        $endPrefix = 'ForWorkspace';
        $permissions = [
            'workspaceWebDefaultIndex' => 'View workspace page',
            'workspaceWebDefaultView' => 'View workspace information',
            'workspaceWebDefaultCreate' => 'Create workspace',
            'workspaceWebDefaultUpdate' => 'Update workspace information',
            'workspaceWebDefaultDelete' => 'Delete workspace',
            'workspaceWebDefaultAssignment' => 'View workspace assignment',
            'workspaceWebDefaultAssign' => 'Assign to workspace',
            'workspaceWebDefaultAssignUpdate' => 'Update workspace assignment',
            'workspaceWebDefaultRemove' => 'Remove workspace assignment',
            'workspaceWebDefaultCreateInvitation' => 'Create invitation',
            'workspaceWebDefaultResendInvitation' => 'Resend invitation',
            'workspaceWebDefaultDeleteInvitation' => 'Delete invitation'
        ];
        

        foreach ($permissions as $permissionKey => $permissionDescription) {
            $permissionForWorkspace = $auth->createPermission($permissionKey . $endPrefix);
            $permissionForWorkspace->description = ' (' . $endPrefix . ')' . $permissionDescription;
            $permissionForWorkspace->ruleName = $rule->name;
            $auth->add($permissionForWorkspace);
            $auth->addChild($admin, $permissionForWorkspace);
            $permission = $auth->getPermission($permissionKey);
            $auth->addChild($permissionForWorkspace, $permission);

        }
    }

    public function down()
    {
        $auth = \Yii::$app->authManager;
        $endPrefix = 'ForWorkspace';
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
        ];

        foreach ($permissions as $permission) {
            $permissionForWorkspace = $auth->getPermission($permission . $endPrefix);
            $auth->remove($permissionForWorkspace);
        }
    }
}
