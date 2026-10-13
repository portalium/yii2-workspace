<?php

use yii\db\Migration;

class m261008_000001_workspace_leave_rbac extends Migration
{
    private const PERMISSIONS = [
        'workspaceWebAssignmentLeave' => 'Leave a workspace the user belongs to',
        'workspaceApiAssignmentLeave' => 'Leave a workspace the user belongs to via API',
    ];

    public function safeUp()
    {
        $auth = \Yii::$app->authManager;
        $adminRole = \Yii::$app->setting->getValue('site::admin_role');
        $admin = (isset($adminRole) && $adminRole != '') ? $auth->getRole($adminRole) : $auth->getRole('admin');
        $user = $auth->getRole('user');
        foreach (self::PERMISSIONS as $permissionName => $description) {
            $permission = $auth->getPermission($permissionName);
            if ($permission === null) {
                $permission = $auth->createPermission($permissionName);
                $permission->description = $description;
                $auth->add($permission);
            }
            foreach ([$admin, $user] as $role) {
                if ($role !== null && !$auth->hasChild($role, $permission)) {
                    $auth->addChild($role, $permission);
                }
            }
        }
    }

    public function safeDown()
    {
        $auth = \Yii::$app->authManager;
        foreach (array_keys(self::PERMISSIONS) as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission !== null) {
                $auth->remove($permission);
            }
        }
    }
}
