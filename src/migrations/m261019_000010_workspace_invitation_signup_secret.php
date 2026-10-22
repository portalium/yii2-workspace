<?php

use portalium\workspace\Module;
use yii\db\Migration;

class m261019_000010_workspace_invitation_signup_secret extends Migration
{
    private const COLUMN = 'signup_secret';

    public function up()
    {
        $table = Module::$tablePrefix . 'invitation';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null || $schema->getColumn(self::COLUMN) !== null) {
            return true;
        }
        $this->addColumn($table, self::COLUMN, $this->string(64)->null()->defaultValue(null)->after('invitation_token'));
        $this->db->getSchema()->refreshTableSchema($table);

        return true;
    }

    public function down()
    {
        $table = Module::$tablePrefix . 'invitation';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null || $schema->getColumn(self::COLUMN) === null) {
            return true;
        }
        $this->dropColumn($table, self::COLUMN);
        $this->db->getSchema()->refreshTableSchema($table);

        return true;
    }
}
