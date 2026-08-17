<?php

use yii\helpers\Html;
use portalium\workspace\Module;
use yii\widgets\DetailView;
use portalium\theme\widgets\Panel;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="workspace-view">



    <p>
        <?php Panel::begin([
            'title' => Html::encode($this->title),
            'actions' => [
                'header' => [
                    Html::a(Module::t(''), ['update', 'id' => $model->id_workspace], ['class' => 'fa fa-pencil btn btn-primary']),
                    Html::a(Module::t(''), ['/workspace/assignment/assignment', 'id' => $model->id_workspace], ['class' => 'fa fa-user btn btn-warning']),
                    Html::a(Module::t(''), ['/workspace/invitation/index', 'id' => $model->id_workspace], ['class' => 'fa fa-envelope btn btn-info']),
                    Html::a(Module::t(''), ['delete', 'id' => $model->id_workspace], [
                        'class' => 'fa fa-trash btn btn-danger',
                        'data' => [
                            'confirm' => Module::t('Are you sure you want to delete this item?'),
                            'method' => 'post',
                        ],
                    ]),
                ]
            ]
        ]) ?>
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'name',
                'title',
            ],
        ]) ?>
        <?php
        Panel::end()
        ?>

</div>