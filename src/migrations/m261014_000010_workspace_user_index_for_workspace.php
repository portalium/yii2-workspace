<?php

use yii\db\Migration;

class m261014_000010_workspace_user_index_for_workspace extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;
        $permission = $auth->getPermission('workspaceWebDefaultIndexForWorkspace');
        if ($permission === null) {
            return true;
        }
        if ($permission->ruleName !== null) {
            $permission->ruleName = null;
            $auth->update($permission->name, $permission);
        }
        $childPermission = $auth->getPermission('workspaceWebDefaultIndex');
        if ($childPermission !== null && $auth->hasChild($permission, $childPermission)) {
            $auth->removeChild($permission, $childPermission);
        }
        $user = $auth->getRole('user');
        if ($user !== null && !$auth->hasChild($user, $permission)) {
            $auth->addChild($user, $permission);
        }
        return true;
    }

    public function safeDown()
    {
        return true;
    }
}
