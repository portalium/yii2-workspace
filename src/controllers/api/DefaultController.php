<?php

namespace portalium\workspace\controllers\api;

use Yii;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceSearch;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use portalium\rest\ActiveController as RestActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;
use yii\web\ServerErrorHttpException;

class DefaultController extends RestActiveController
{
    public $modelClass = Workspace::class;

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $actions = parent::actions();

        unset($actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        $workspaceSearch = new WorkspaceSearch();

        $actions['index']['dataFilter'] = [
            'class' => \yii\data\ActiveDataFilter::class,
            'searchModel' => $workspaceSearch,
        ];

        $actions['index']['prepareDataProvider'] = function ($action) use ($workspaceSearch) {
            if (!Yii::$app->user->can('workspaceApiDefaultIndex', ['id_module' => 'workspace']) &&
                !Yii::$app->user->can('workspaceApiDefaultIndexOwn', ['id_module' => 'workspace'])) {
                throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
            }

            $dataProvider = $workspaceSearch->search(Yii::$app->request->queryParams);

            if ($action->dataFilter !== null) {
                $filter = $action->dataFilter->build();
                if ($filter !== false && !empty($filter)) {
                    $dataProvider->query->andWhere($filter);
                }
            }

            $dataProvider->query->andWhere([Module::$tablePrefix . 'workspace.id_user' => Yii::$app->user->id]);

            return $dataProvider;
        };

        return $actions;
    }

    /**
     * GET /workspace/default/joined-workspaces
     *
     * Returns all workspaces the current user is a member of (active or inactive).
     */
    public function actionGetJoinedWorkspaces()
    {
        return Yii::$app->workspace->getJoinedWorkspaces();
    }

    /**
     * GET /workspace/default/manage
     *
     * Admin-only listing of all workspaces (no ownership filter).
     */
    public function actionManage()
    {
        if (!Yii::$app->user->can('workspaceWorkspaceFullAccess')) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $searchModel = new WorkspaceSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return [
            'items' => $dataProvider->getModels(),
            'totalCount' => $dataProvider->getTotalCount(),
            'pagination' => [
                'page' => $dataProvider->pagination->page + 1,
                'pageSize' => $dataProvider->pagination->pageSize,
                'totalPages' => $dataProvider->pagination->pageCount,
            ],
        ];
    }

    /**
     * GET /workspace/default/:id
     *
     * View a single workspace with permission checks.
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        if (!(Yii::$app->user->can('workspaceApiDefaultView', ['id_module' => 'workspace', 'model' => $model]) ||
              (Yii::$app->user->can('workspaceApiDefaultViewOwn', ['id_module' => 'workspace', 'model' => $model]) && $model->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to view this workspace.'));
        }

        return $model;
    }

    /**
     * POST /workspace/default
     *
     * Create a new workspace owned by the current user.
     */
    public function actionCreate()
    {
        if (!Yii::$app->user->can('workspaceApiDefaultCreateOwn', ['id_module' => 'workspace'])) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to create workspaces.'));
        }

        if (!Yii::$app->workspace->checkSupportRoles()) {
            throw new BadRequestHttpException(Module::t('Please set default role for workspace module.'));
        }

        $model = new Workspace();

        if ($model->load(Yii::$app->request->getBodyParams(), '')) {
            $model->id_user = Yii::$app->user->id;

            if ($model->save()) {
                return $model;
            }

            throw new UnprocessableEntityHttpException(json_encode($model->getFirstErrors()));
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * PUT/PATCH /workspace/default/:id
     *
     * Update an existing workspace.
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (!(Yii::$app->user->can('workspaceApiDefaultUpdate', ['id_module' => 'workspace', 'model' => $model]) ||
              (Yii::$app->user->can('workspaceApiDefaultUpdateOwn', ['id_module' => 'workspace', 'model' => $model]) && $model->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to update this workspace.'));
        }

        if (!Yii::$app->workspace->checkSupportRoles()) {
            throw new BadRequestHttpException(Module::t('Please set default role for workspace module.'));
        }

        if ($model->load(Yii::$app->request->getBodyParams(), '')) {
            if ($model->save())
            {
                return $model;
            }

            throw new UnprocessableEntityHttpException(json_encode($model->getFirstErrors()));
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * DELETE /workspace/default/:id
     *
     * Delete a workspace.
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if (!(Yii::$app->user->can('workspaceApiDefaultDelete', ['id_module' => 'workspace', 'model' => $model]) ||
              (Yii::$app->user->can('workspaceApiDefaultDeleteOwn', ['id_module' => 'workspace', 'model' => $model]) && $model->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to delete this workspace.'));
        }

        if ($model->delete() === false) {
            throw new ServerErrorHttpException(Module::t('Failed to delete the workspace.'));
        }

        Yii::$app->getResponse()->setStatusCode(204);
        return null;
    }

    /**
     * POST /workspace/default/set-workspace
     *
     * Activate a workspace for the current user (sets status=ACTIVE for that membership,
     * deactivates all other memberships).
     *
     * Expected body: { "id": <workspace_user_id> }
     */
    public function actionSetWorkspace()
    {
        $id = Yii::$app->request->post('id');
        if (!$id) {
            throw new BadRequestHttpException(Module::t('Missing workspace user ID.'));
        }

        $workspaceUserModel = WorkspaceUser::findOne(['id_workspace_user' => $id, 'id_user' => Yii::$app->user->id]);
        if (!$workspaceUserModel) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to set this workspace.'));
        }

        $workspace = Workspace::findOne(['id_workspace' => $workspaceUserModel->id_workspace]);
        if (!$workspace) {
            throw new NotFoundHttpException(Module::t('Workspace not found.'));
        }

        if (!(Yii::$app->user->can('workspaceApiDefaultSetWorkspace', ['id_module' => 'workspace', 'model' => $workspace]) ||
              (Yii::$app->user->can('workspaceApiDefaultSetWorkspaceOwn', ['id_module' => 'workspace', 'model' => $workspace]) && Yii::$app->workspace->isMember($workspace->id_workspace))))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to set this workspace.'));
        }

        $activeMemberships = WorkspaceUser::findNoGroupBy()
            ->where(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])
            ->all();

        foreach ($activeMemberships as $membership) {
            $membership->status = WorkspaceUser::STATUS_INACTIVE;
            if (!$membership->save()) {
                throw new ServerErrorHttpException(Module::t('Failed to update workspace membership.'));
            }
        }

        $workspaceUserModel->status = WorkspaceUser::STATUS_ACTIVE;
        if (!$workspaceUserModel->save()) {
            throw new ServerErrorHttpException(Module::t('Failed to activate workspace.'));
        }

        if($workspace->id_user == Yii::$app->user->id) {
            Yii::$app->session->set('active_workspace_id', $workspaceUserModel->id_workspace);
        }

        return ['success' => true, 'active_workspace_id' => $workspaceUserModel->id_workspace];
    }

    /**
     * Finds the Workspace model based on its primary key value.
     * @param int $id_workspace
     * @return Workspace
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_workspace)
    {
        if (($model = Workspace::findOne([Module::$tablePrefix . 'workspace.id_workspace' => $id_workspace])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
    }
}