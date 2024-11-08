<?php

use portalium\workspace\models\Workspace;
use yii\db\Migration;
use portalium\site\Module;
use portalium\site\models\Form;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module as WorkspaceModule;

class m230523_200101_workspace_setting extends Migration
{
    public function up()
    {

        $siteUserRole = Yii::$app->setting->getValue('site::user_role');
        $siteAdminRole = Yii::$app->setting->getValue('site::admin_role');
        $this->insert(Module::$tablePrefix . 'setting', [
            'module' => 'workspace',
            'name' => 'workspace::available_roles',
            'label' => 'Available Roles',
            'type' => Form::TYPE_WIDGET,
            'value' => $siteUserRole ? '{"storage":["' . $siteAdminRole . '", "' . $siteUserRole . '"]}' : '',
            'config' => json_encode([
                'widget' => '\portalium\workspace\widgets\AvailableRoles',
                'options' => [
                ]
            ]),
            'is_preference' => 0
        ]);
        if ($siteUserRole) {
            $this->insert(WorkspaceModule::$tablePrefix . 'workspace', [
                'name' => 'SystemWorkspace',
                'title'=> 'System Workspace',
                'id_user' => 1,
                'date_create' => date('Y-m-d H:i:s'),
                'date_update' => date('Y-m-d H:i:s'),
            ]);
        }
        
    }

    public function down()
    {
        $this->delete(Module::$tablePrefix . 'setting', ['module' => 'workspace']);
    }
}
