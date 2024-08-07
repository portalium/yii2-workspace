<?php

use portalium\workspace\Module;
use portalium\user\Module as UserModule;
use yii\db\Migration;

class m180323_174343_workspace_permission extends Migration
{
    public function up()
    {
        $this->createTable(Module::$tablePrefix . 'workspace_permission', [
            'id_workspace_permission' => $this->primaryKey(11)->notNull(),
            'id_workspace' => $this->integer(11)->notNull(),
            'permission' => $this->string(255)->notNull(),
            'id_user' => $this->integer(11)->notNull()
        ]);

        $this->createIndex(
            'id_workspace_workspace_permission',
            Module::$tablePrefix . 'workspace_permission',
            'id_workspace'
        );

        $this->createIndex(
            'id_user_workspace_permission',
            Module::$tablePrefix . 'workspace_permission',
            'id_user'
        );

        $this->addForeignKey(
            'fk_id_workspace_workspace_permission',
            Module::$tablePrefix . 'workspace_permission',
            'id_workspace',
            Module::$tablePrefix . 'workspace',
            'id_workspace',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_id_user_workspace_permission',
            Module::$tablePrefix . 'workspace_permission',
            'id_user',
            UserModule::$tablePrefix . 'user',
            'id_user',
            'CASCADE'
        );
    }

    public function down()
    {
        $this->dropTable(Module::$tablePrefix . 'workspace_permission');
    }
}
