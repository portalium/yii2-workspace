<?php

namespace portalium\workspace\controllers\web;

use Yii;
use yii\filters\VerbFilter;
use portalium\workspace\Module;
use yii\web\NotFoundHttpException;
use portalium\workspace\models\Workspace;
use portalium\web\Controller as WebController;
use portalium\workspace\models\WorkspaceSearch;

class DefaultController extends WebController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all Workspace models.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultIndex', ['id_module' => 'workspace']) && !\Yii::$app->user->can('workspaceWebDefaultIndexForWorkspace', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $searchModel = new WorkspaceSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $dataProvider->query->andWhere([Module::$tablePrefix . 'workspace.id_user' => Yii::$app->user->id]);
        // $dataProvider->pagination->pageSize = 12;
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Lists all Workspace models.
     *
     * @return string
     */
    public function actionManage()
    {
        if (!\Yii::$app->user->can('workspaceWorkspaceFullAccess')) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $searchModel = new WorkspaceSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Workspace model.
     * @param int $id Id Workspace
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultView', ['id_module' => 'workspace', 'model' => $this->findModel($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Workspace model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultCreate', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = new Workspace();
        $model->id_user = Yii::$app->user->id;
        if (!Yii::$app->workspace->checkSupportRoles()) {
            Yii::$app->session->addFlash('error', Module::t('Please set default role for workspace module.'));

            return $this->redirect(['index']);
        }
        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                $model->id_user = Yii::$app->user->id;
                if ($model->save())
                    return $this->redirect(['view', 'id' => $model->id_workspace]);
            }
        } else {
            $model->loadDefaultValues();
        }
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Workspace model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id Id Workspace
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultUpdate', ['id_module' => 'workspace', 'model' => $this->findModel($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if (!Yii::$app->workspace->checkSupportRoles()) {
            Yii::$app->session->setFlash('error', Module::t('Please set default role for workspace module.'));
            return $this->redirect(['index']);
        }
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_workspace]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Workspace model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id Id Workspace
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultDelete', ['id_module' => 'workspace', 'model' => $this->findModel($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Workspace model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id_workspace Id Workspace
     * @return Workspace the loaded model
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
