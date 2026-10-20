<?php

use yii\db\Migration;

class m260928_000001_workspace_remove_direct_update_from_user extends Migration
{
    public function up()
    {
        $auth = \Yii::$app->authManager;
        $user = $auth->getRole('user');
        $permission = $auth->getPermission('workspaceWebDefaultUpdate');
        if ($user !== null && $permission !== null && $auth->hasChild($user, $permission)) {
            $auth->removeChild($user, $permission);
        }
    }

    public function down()
    {
        $auth = \Yii::$app->authManager;
        $user = $auth->getRole('user');
        $permission = $auth->getPermission('workspaceWebDefaultUpdate');
        if ($user !== null && $permission !== null && !$auth->hasChild($user, $permission)) {
            $auth->addChild($user, $permission);
        }
    }
}
