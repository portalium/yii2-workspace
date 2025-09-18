<?php

namespace portalium\workspace\controllers\api;

use portalium\rest\ActiveController as RestActiveController;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use Yii;

class UserController extends RestActiveController
{
    public $modelClass = 'portalium\workspace\models\WorkspaceUser';

    public function actions()
    {
        $actions = parent::actions();
        $actions['index'] = [
            'class' => 'yii\rest\IndexAction',
            'modelClass' => $this->modelClass,
            'dataFilter' => [
                'class' => \yii\data\ActiveDataFilter::class,
                'searchModel' => $this->modelClass,
            ],
        ];
        $actions['index']['prepareSearchQuery'] = function ($action) {
            $query = $action->andWhere(['id_user' => \Yii::$app->user->id]);
            return $query;
        };
        return $actions;
    }
}