<?php

use portalium\workspace\Module;
use yii\db\Migration;

class m180323_174347_workspace extends Migration
{
    public function up()
    {
        $this->addColumn(Module::$tablePrefix . 'workspace_user', 'id_module', $this->string(255));
    }

    public function down()
    {

    }
}
