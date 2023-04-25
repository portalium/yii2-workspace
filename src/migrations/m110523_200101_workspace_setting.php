<?php

use Yii;
use yii\db\Migration;
use portalium\site\Module;
use yii\helpers\ArrayHelper;
use portalium\site\models\Form;

class m110523_200101_workspace_setting extends Migration
{
    public function up()
    {

        $this->insert(Module::$tablePrefix . 'setting', [
            'module' => 'workspace',
            'name' => 'workspace::available_roles',
            'label' => 'Available Roles',
            'type' => Form::TYPE_WIDGET,
            'config' => json_encode([
                'widget' => '\portalium\workspace\widgets\AvailableRoles',
                'options' => [
                ]
            ])
        ]);
    }

    public function down()
    {
        $this->delete(Module::$tablePrefix . 'setting', ['module' => 'workspace']);
    }
}
