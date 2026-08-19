<?php

use yii\db\Migration;

class m260819_160455_adding_cron_user_role_permissons extends Migration
{
    private const USERNAME = 'workspace_cron_agent';
    private const ROLE = 'workspace_cron_agent';
    private const PERMISSION = 'workspaceApiInvitationExpireCron';

    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $permission = $auth->getPermission(self::PERMISSION);
        if ($permission === null) {
            $permission = $auth->createPermission(self::PERMISSION);
            $permission->description = 'Reject expired workspace invitations';
            $auth->add($permission);
        }

        $role = $auth->getRole(self::ROLE);
        if ($role === null) {
            $role = $auth->createRole(self::ROLE);
            $role->description = 'Workspace invitation expiration cron agent';
            $auth->add($role);
        }

        if (!$auth->hasChild($role, $permission)) {
            $auth->addChild($role, $permission);
        }

        $userId = (new \yii\db\Query())
            ->select('id_user')
            ->from('user_user')
            ->where(['username' => self::USERNAME])
            ->scalar();

        if ($userId === false) {
            $userId = $this->db->createCommand()->insert('user_user', [
                'username' => self::USERNAME,
                'first_name' => NULL,
                'last_name' => NULL,
                'auth_key' => 'pBPbfkmlGctDvfVl88sX4OqPz1SXudab',
                'password_hash' => '$2y$13$4llODIdRQ1eBbWsF2bKJ3.wJ98AhXzlezHil/FI.qKSdV.N17JyhS',
                'password_reset_token' => NULL,
                'email' => self::USERNAME . '@localhost.com',
                'access_token' => Yii::$app->security->generateRandomString(),
                'status' => 10,
            ])->execute();

            $userId = $this->db->getLastInsertID();
        }

        if (!$auth->getAssignment(self::ROLE, $userId)) {
            $auth->assign($role, $userId);
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;
        $userId = (new \yii\db\Query())
            ->select('id_user')
            ->from('user_user')
            ->where(['username' => self::USERNAME])
            ->scalar();

        if ($userId !== false) {
            $auth->revoke($auth->getRole(self::ROLE), $userId);
            $this->delete('user_user', ['id_user' => $userId]);
        }

        $role = $auth->getRole(self::ROLE);
        if ($role !== null) {
            $auth->remove($role);
        }

        $permission = $auth->getPermission(self::PERMISSION);
        if ($permission !== null) {
            $auth->remove($permission);
        }
    }
}
