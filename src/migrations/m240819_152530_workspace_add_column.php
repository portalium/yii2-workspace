<?php

use portalium\workspace\Module;
use yii\db\Migration;

/**
 * Class m240819_152530_workspace_add_column
 */
class m240819_152530_workspace_add_column extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Module::$tablePrefix . 'workspace', 'title', $this->string(255));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240819_152530_workspace_add_column cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240819_152530_workspace_add_column cannot be reverted.\n";

        return false;
    }
    */
}
