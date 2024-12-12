<?php

namespace portalium\workspace\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\theme\widgets\Nav;
use portalium\menu\models\MenuItem;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\models\Workspace as ModelsWorkspace;

class Workspace extends Widget
{
    public $options;
    public $icon;
    public $display;
    public $placement;

    public function init()
    {
        if (!$this->icon) {
            //$this->icon = Html::tag('i', '', ['class' => 'fa-building', 'style' => 'margin-right: 5px;']);
        }
     
        $this->options['class'] = 'placementWidget';
        if($this->placement == 'top-to-bottom'){
            $this->options['data-bs-placement'] = $this->placement; 
            $this->registerCss();

        }if($this->placement == 'side-by-side'){
            $this->registerCss();
        }

        parent::init();
    }

    public function run()
    {


        $query = WorkspaceUser::find();
        // if(!Yii::$app->user->can('workspaceWorkspaceFullAccess')){
        $query->where(['id_user' => Yii::$app->user->id]);
        // }
        $workspaces = $query->all();
        $orgItems = [];

        $activeWorkspace = WorkspaceUser::find()->where(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])->one();
        foreach ($workspaces as $key => $value) {
            $orgItems[] = [
                'label' => Module::t($value->workspace->title) . (isset($value->workspace->name) ? (' (' . $value->workspace->name . ')') : ''),
                'url' => ['/workspace/assignment/set-workspace', 'id' => $value->id_workspace_user],
                'active' => $activeWorkspace && $activeWorkspace->id_workspace == $value->id_workspace ? true : false,
            ];
        }

        //orgItems unique
        if ($activeWorkspace) {
            $menuItems[] = [
                'label' => $this->generateLabel("Workspace"),
                'url' => ['/workspace/assignment/set-workspace', 'id' => $activeWorkspace->id_workspace],
                'items' => $orgItems,
               
                
            ];
            $this->registerCss();
        } else {
            $menuItems[] = [
                'label' => $this->generateLabel("Workspace"),
                'url' => ['#'],
                'items' => $orgItems,
            ];
        }
        return Nav::widget([
            'options' => $this->options,
            'items' => $menuItems,
        
        ]);
    }

    private function generateLabel($text)
    {
        $label = "";
        if (isset($this->display)) {
            switch ($this->display) {
                case MenuItem::TYPE_DISPLAY['icon']:
                    $label = $this->icon;
                    break;
                case MenuItem::TYPE_DISPLAY['icon-text']:
                    $label = $this->icon . Module::t($text);
                    break;
                case MenuItem::TYPE_DISPLAY['text']:
                    $label = Module::t($text);
                    break;
                default:
                    $label = $this->icon . Module::t($text);
                    break;
            }
        } else {
            $label = $this->icon . Module::t($text);
        }

        return $label;
    }

    private function registerCss()
    {
        $css = <<<CSS
    .placementWidget[data-bs-placement="side-by-side"] {
    }
    .placementWidget[data-bs-placement="top-to-bottom"] li a i {
     display: block;
     flex-direction: column; 
     align-items: center;
    }
    CSS;
        $this->getView()->registerCss($css);
    }
}

