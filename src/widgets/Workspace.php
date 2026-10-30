<?php

namespace portalium\workspace\widgets;

use Yii;
use yii\base\Widget;
use yii\web\YiiAsset;
use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\theme\widgets\Nav;
use portalium\menu\models\MenuItem;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\models\Workspace as WorkspaceModel;
use portalium\workspace\bundles\WorkspaceSwitcherAsset;

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
     
        $this->options['class'] = 'placementWidget workspace-pill';
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


        $workspaces = Yii::$app->workspace->getJoinedWorkspaces(WorkspaceModel::IS_VIRTUAL_FALSE);
        $orgItems = [];

        $activeWorkspace = WorkspaceUser::find()->where(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])->one();
        if (Yii::$app->session->get('active_workspace_id', null) !== null) {
            $activeWorkspace = WorkspaceUser::find()->where(['id_workspace' => Yii::$app->session->get('active_workspace_id'), 'id_user' => Yii::$app->user->id])->one();
        }
        foreach ($workspaces as $key => $value) {
            $orgItems[] = [
                'label' => Module::t($value->workspace->title) . (isset($value->workspace->name) ? (' (' . $value->workspace->name . ')') : ''),
                'url' => ['/workspace/assignment/set-workspace', 'id' => $value->id_workspace_user],
                'linkOptions' => ['data-method' => 'post'],
                'active' => $activeWorkspace && $activeWorkspace->id_workspace == $value->id_workspace ? true : false,
            ];
        }

        //orgItems unique
        if ($activeWorkspace) {
            $label = $this->pillLabel($activeWorkspace);
            $menuItems[] = [
                'label' => $label ?? $this->generateLabel("Workspace"),
                'encode' => false,
                'url' => '#',
                'items' => $orgItems,
                // the pill look only when the name is shown; an icon-only item looks like its neighbours
                'linkOptions' => $label !== null ? ['class' => 'workspace-pill-link'] : [],
            ];
            $this->registerCss();
        } else {
            $menuItems[] = [
                'label' => $this->generateLabel("Workspace"),
                'encode' => false,
                'url' => ['#'],
                'items' => $orgItems,
            ];
        }
        YiiAsset::register($this->getView());
        WorkspaceSwitcherAsset::register($this->getView());
        return Nav::widget([
            'options' => $this->options,
            'items' => $menuItems,
        
        ]);
    }

    /**
     * Label of the top menu pill: the active workspace's initials tile and name; null when the item
     * is set to show only its icon.
     */
    private function pillLabel(WorkspaceUser $activeWorkspace): ?string
    {
        $workspace = $activeWorkspace->workspace;
        if ($workspace === null || (isset($this->display) && $this->display == MenuItem::TYPE_DISPLAY['icon'])) {
            return null;
        }
        $title = $workspace->displayTitle();

        return Html::tag('span', Html::encode(WorkspaceSwitcher::initials($title)), [
                'class' => 'ws-tile ws-tile-' . WorkspaceSwitcher::color((int) $workspace->id_workspace),
                'aria-hidden' => 'true',
            ])
            . Html::tag('span', Html::encode($title), ['class' => 'workspace-pill-name']);
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
                    $label = $this->icon . Html::encode(Module::t($text));
                    break;
                case MenuItem::TYPE_DISPLAY['text']:
                    $label = Html::encode(Module::t($text));
                    break;
                default:
                    $label = $this->icon . Html::encode(Module::t($text));
                    break;
            }
        } else {
            $label = $this->icon . Html::encode(Module::t($text));
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
    .placementWidget .dropdown-menu {
     max-height: 300px;
     overflow-y: auto;
    }
    CSS;
        $this->getView()->registerCss($css);
    }
}

