<?php

use yii\helpers\Url;
use yii\helpers\Html;

use portalium\workspace\Module;

use portalium\theme\widgets\Panel;
use portalium\theme\widgets\GridView;
use portalium\workspace\models\Workspace;
use portalium\theme\widgets\ActionColumn;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\WorkspaceSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Module::t('Workspaces');
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="workspace-index">

    <?php 
        $actions[] = Html::a(Module::t(''), ['create'], ['class' => 'btn btn-success fa fa-plus', 'id' => 'create-workspace']);
        Panel::begin(['title' => Module::t('Workspace'), 'icon' => 'icon-plus font-blue-hoki', 'actions' => $actions]); 
    ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'name',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Workspace $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id_workspace]);
                },
                'template' => '{view} {update} {delete} {assign}',
                'buttons' => [
                    'assign' => function ($url, $model) {
                        return Html::a(
                            Html::tag('i', '', ['class' => 'fa fa-thin fa-user']), 
                            ['assignment', 'id' => $model->id_workspace], 
                            ['title' => Module::t('Assign'), 'class' => 'btn btn-warning btn-xs', 'style' => 'padding: 2px 9px 2px 9px;'] 
                        );
                    },
                ],
            ],
        ],
    ]); ?>

    <?php Panel::end(); ?>

</div>
