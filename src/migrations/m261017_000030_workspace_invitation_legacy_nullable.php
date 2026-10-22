<?php

use portalium\workspace\Module;
use yii\db\Migration;

class m261017_000030_workspace_invitation_legacy_nullable extends Migration
{
    private const LEGACY_COLUMNS = ['email', 'module', 'role'];

    public function up()
    {
        $table = Module::$tablePrefix . 'invitation';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return true;
        }
        foreach (self::LEGACY_COLUMNS as $name) {
            $column = $schema->getColumn($name);
            if ($column === null || $column->allowNull || $column->defaultValue !== null) {
                continue;
            }
            $this->alterColumn($table, $name, $column->dbType . ' NULL DEFAULT NULL');
        }
        $this->db->getSchema()->refreshTableSchema($table);
        return true;
    }

    public function down()
    {
        return true;
    }
}
