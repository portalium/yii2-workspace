<?php

use portalium\user\Module as UserModule;
use portalium\workspace\Module;
use yii\db\Migration;

class m180323_184343_workspace_invitation_role extends Migration
{
    public function up()
    {
        
        $this->createTable(Module::$tablePrefix . 'invitation_role', [
            'id_invitation_role' => $this->primaryKey(11)->notNull(),
            'id_invitation' => $this->integer(11)->notNull(),
            'id_workspace' => $this->integer(11)->notNull(),
            'email' => $this->string(255)->notNull(),
            'module' => $this->string(32)->notNull(),
            'role' => $this->string(32)->notNull(),
            'status' => $this->smallInteger(1)->notNull()->defaultValue(0), // 0: pending, 1: accepted
        ]);

        /* $this->createIndex(
            '{{%idx-' . Module::$tablePrefix . 'invitation-id_workspace_workspace}}',
            Module::$tablePrefix . 'invitation_role',
            'id_workspace'
        ); */

        $this->addForeignKey(
            'fk_id_workspace_invitation_role_invitation',
            Module::$tablePrefix . 'invitation_role',
            'id_workspace',
            Module::$tablePrefix . 'workspace',
            'id_workspace',
            'CASCADE'
        );

        /*  $this->createIndex(
            '{{%idx-' . Module::$tablePrefix . 'invitation-id_invitation_workspace}}',
            Module::$tablePrefix . 'invitation_role',
            'id_invitation'
        ); */

        $this->addForeignKey(
            'fk_id_invitation_invitation_role_invitation',
            Module::$tablePrefix . 'invitation_role',
            'id_invitation',
            Module::$tablePrefix . 'invitation',
            'id_invitation',
            'CASCADE'
        );
    }

    public function down()
    {
        
        $this->dropTable(Module::$tablePrefix . 'invitation_role');
    }
}
