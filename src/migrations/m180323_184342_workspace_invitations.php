<?php

use portalium\user\Module as UserModule;
use portalium\workspace\Module;
use yii\db\Migration;

class m180323_184342_workspace_invitations extends Migration
{
    public function up()
    {
        
        $this->createTable(Module::$tablePrefix . 'invitation', [
            'id_invitation' => $this->primaryKey(11)->notNull(),
            'invitation_token' => $this->string(255)->notNull(),
            'id_workspace' => $this->integer(11)->notNull(),
            'id_user' => $this->integer(11)->null()->defaultValue(null),
            'date_create' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP"),
            'date_expire' => $this->dateTime()->notNull()->defaultExpression("CURRENT_TIMESTAMP")
        ]);

        /* $this->createIndex(
            '{{%idx-' . Module::$tablePrefix . 'invitation-id_invitation_workspace}}',
            Module::$tablePrefix . 'invitation',
            'id_workspace'
        ); */

        $this->addForeignKey(
            'fk_id_workspace_invitation',
            Module::$tablePrefix . 'invitation',
            'id_workspace',
            Module::$tablePrefix . 'workspace',
            'id_workspace',
            'CASCADE'
        );

        /* $this->createIndex(
            '{{%idx-' . Module::$tablePrefix . 'invitation-id_user_workspace}}',
            Module::$tablePrefix . 'invitation',
            'id_user'
        ); */

        $this->addForeignKey(
            'fk_id_user_invitation',
            Module::$tablePrefix . 'invitation',
            'id_user',
            UserModule::$tablePrefix . 'user',
            'id_user',
            'CASCADE'
        );
    }

    public function down()
    {
        
        $this->dropTable(Module::$tablePrefix . 'invitation');
    }
}
