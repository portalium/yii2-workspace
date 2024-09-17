<?php

namespace portalium\workspace\widgets;

use Yii;

use portalium\theme\widgets\Html;
use portalium\theme\widgets\InputWidget;
use portalium\theme\widgets\Tabs;

class AvailableRoles extends InputWidget
{
    public $settingIndex = 0;
    public function init()
    {
        parent::init();
    }

    public function run()
    {
        if ($this->hasModel()) {
            $input = 'activeHiddenInput';
            echo Html::$input($this->model, $this->attribute, $this->options);
        }
        $supportWorkspaceModules = Yii::$app->workspace->getSupportModules();
        
        $checkListGroup = [];
        foreach ($supportWorkspaceModules as $key => $value) {
            $checkList = [];
            $module = Yii::$app->getModule($key);
            $supportWorkspaceModules[$key] = $module->className()::t($module->className()::$name);
            $roles = Yii::$app->authManager->getRoles();
            foreach ($roles as $role) {
                $checkList[$role->name] = $role->name;
            }
            
            $checkListGroup[$key] = $checkList;
        }
        $values = $this->model->value;
        try {
            $values = json_decode($values, true);
        } catch (\Exception $e) {
            $values = [];
        }

        $tabs = [];

        foreach ($checkListGroup as $key => $value) {
            $tabs[] = [
                'label' => $supportWorkspaceModules[$key],
                'content' => Html::checkboxList('Setting[workspace-'. $this->settingIndex .'][value]['. $key .'][]', 
                    $values[$key] ?? []
                    , $checkListGroup[$key], 
                    ['class' => 'form-control']),
            ];
        }

        echo Tabs::widget([
            'items' => $tabs,
            'id' => 'workspace-'. $this->settingIndex .'-tabs',
            
        ]);

        Yii::$app->view->registerJs(
            '
                $("[name=\"Setting[workspace-'. $this->settingIndex .'][value]\"]").val("");            
            '
        );
    
    }

}
