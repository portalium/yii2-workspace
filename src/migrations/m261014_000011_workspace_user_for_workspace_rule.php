<?php

use yii\db\Migration;

class m261014_000011_workspace_user_for_workspace_rule extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;
        $user = $auth->getRole('user');
        if ($user === null) {
            return true;
        }
        $permissionsName = [
            'workspaceWebDefaultViewForWorkspace',
            'workspaceWebDefaultCreateForWorkspace',
            'workspaceWebDefaultUpdateForWorkspace',
            'workspaceWebDefaultDeleteForWorkspace',
            'workspaceWebDefaultAssignmentForWorkspace',
            'workspaceWebDefaultAssignForWorkspace',
            'workspaceWebDefaultAssignUpdateForWorkspace',
            'workspaceWebDefaultRemoveForWorkspace',
            'workspaceWebDefaultCreateInvitationForWorkspace',
            'workspaceWebDefaultResendInvitationForWorkspace',
            'workspaceWebDefaultDeleteInvitationForWorkspace',
        ];
        foreach ($permissionsName as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission !== null && $permission->ruleName !== null && !$auth->hasChild($user, $permission)) {
                $auth->addChild($user, $permission);
            }
        }
        return true;
    }

    public function safeDown()
    {
        return true;
    }
}
