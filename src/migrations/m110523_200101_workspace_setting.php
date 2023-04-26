<?php

use yii\db\Migration;
use portalium\site\Module;
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

        $this->insert(Module::$tablePrefix . 'setting', [
            'module' => 'workspace',
            'name' => 'workspace::default_role',
            'label' => 'Workspace Admin Role',
            'value' => 'admin',
            'type' => Form::TYPE_DROPDOWNLIST,
            'config' => json_encode([
                'model' => [
                    'class' => 'portalium\site\models\DbManager',
                    'map' => [
                        'key' => 'name' ,
                        'value' => 'name'
                    ],
                    'where' => [
                        'type' => 1
                    ]
                ]
            ])
        ]);
    }

    public function down()
    {
        $this->delete(Module::$tablePrefix . 'setting', ['module' => 'workspace']);
    }
}
