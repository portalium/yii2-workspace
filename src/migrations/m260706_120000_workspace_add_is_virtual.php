<?php

use portalium\workspace\Module;
use yii\db\Migration;

class m260706_120000_workspace_add_is_virtual extends Migration
{
    public function up()
    {
        $this->addColumn(Module::$tablePrefix . 'workspace', 'is_virtual', $this->tinyInteger(1)->notNull()->defaultValue(0));
    }

    public function down()
    {
        $this->dropColumn(Module::$tablePrefix . 'workspace', 'is_virtual');
    }
}
