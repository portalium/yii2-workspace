<?php

namespace portalium\workspace\controllers\api;

use portalium\rest\ActiveController as RestActiveController;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use Yii;

class DefaultController extends RestActiveController
{
    public $modelClass = 'portalium\workspace\models\Workspace';

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
        unset($actions['update']);
        return $actions;
    }

    
    
    public function actionGetJoinedWorkspaces()
    {
        $workspaces = \Yii::$app->workspace->getJoinedWorkspaces();
        return $workspaces;
    }

    public function actionSetWorkspace(){

        $id = Yii::$app->request->post('id');
        $workspaceUserModel = WorkspaceUser::findOne(['id_workspace_user' => $id, 'id_user' => Yii::$app->user->id]);
        if ($id == 0 || !$workspaceUserModel) {
            Yii::$app->session->addFlash('error', Module::t('You are not allowed to set this workspace.'));
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if (!\Yii::$app->user->can('workspaceWebDefaultSetWorkspace', ['id_module' => 'workspace', 'model' => Workspace::findOne(['id_workspace' => $workspaceUserModel->id_workspace])])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $workspaceUsers = WorkspaceUser::find(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])->groupBy('id_workspace_user')->all();
        if ($workspaceUsers) {
            foreach ($workspaceUsers as $workspaceUser) {
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
                $workspaceUser->save();
            }
        }
        $workspaceUser = WorkspaceUser::findOne(['id_workspace_user' => $id]);
        if ($workspaceUser) {
            $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
            $workspaceUser->save();
        }
        return true;
    }
}
