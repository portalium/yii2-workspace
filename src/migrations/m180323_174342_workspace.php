<?php

use portalium\workspace\Module;
use portalium\user\Module as UserModule;
use yii\db\Migration;

class m180323_174342_workspace extends Migration
{
    public function up()
    {
        $this->createTable(Module::$tablePrefix . 'workspace', [
            'id_workspace' => $this->primaryKey(11)->notNull(),
            'name' => $this->string(255)->notNull(),
            'title' => $this->string(255)->notNull(),
            'id_user' => $this->integer(11)->notNull(),
            'date_create' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP"),
            'date_update' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP")
        ]);

        $this->createIndex(
            'id_user',
            Module::$tablePrefix . 'workspace',
            'id_user'
        );

        $this->addForeignKey(
            'fk_id_user_workspace_workspace',
            Module::$tablePrefix . 'workspace',
            'id_user',
            UserModule::$tablePrefix . 'user',
            'id_user',
            'CASCADE'
        );

        $this->createTable(Module::$tablePrefix . 'workspace_user', [
            'id_workspace_user' => $this->primaryKey(11)->notNull(),
            'id_user' => $this->integer(11)->notNull(),
            'id_workspace' => $this->integer(11)->notNull(),
            'role' => $this->string(32)->notNull(),
            'status' => $this->tinyInteger(1)->notNull(),
        ]);
        
        $this->createIndex(
            'id_workspace',
            Module::$tablePrefix . 'workspace_user',
            'id_workspace'
        );

        $this->createIndex(
            'id_user',
            Module::$tablePrefix . 'workspace_user',
            'id_user'
        );

        $this->addForeignKey(
            'fk_id_workspace',
            Module::$tablePrefix . 'workspace_user',
            'id_workspace',
            Module::$tablePrefix . 'workspace',
            'id_workspace',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_id_user',
            Module::$tablePrefix . 'workspace_user',
            'id_user',
            UserModule::$tablePrefix . 'user',
            'id_user',
            'CASCADE'
        );
    }

    public function down()
    {
        $this->dropForeignKey('fk_id_user', Module::$tablePrefix . 'workspace_user');
        $this->dropForeignKey('fk_id_workspace', Module::$tablePrefix . 'workspace_user');
        $this->dropForeignKey('fk_id_user_workspace_workspace', Module::$tablePrefix . 'workspace');

        $this->dropTable(Module::$tablePrefix . 'workspace_user');
        $this->dropTable(Module::$tablePrefix . 'workspace');
    }
}
