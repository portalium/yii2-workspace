<?php

namespace portalium\workspace\controllers\web;

use portalium\user\models\User;
use Yii;
use yii\base\DynamicModel;
use yii\filters\VerbFilter;
use portalium\workspace\Module;
use yii\web\NotFoundHttpException;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\WorkspaceUser;
use portalium\web\Controller as WebController;
use portalium\workspace\models\InvitationSearch;

class InvitationController extends WebController
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
     * Displays a single Workspace model.
     * @param int $id_workspace Id Workspace
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionIndex($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultView', ['id_module' => 'workspace', 'model' => $this->findWorkspace($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $invitationModel = new \portalium\workspace\models\InvitationForm();
        $invitationModel->id_workspace = $id;
        $invitationSearchModel = new \portalium\workspace\models\InvitationSearch();
        // $invitationDataProvider = Invitation::find()->where(['id_workspace' => $id])->groupBy(['invitation_token']);
        // $invitationDataProvider = new \yii\data\ActiveDataProvider([
        //     'query' => $invitationDataProvider,
        // ]);
        $invitationDataProvider = $invitationSearchModel->search(Yii::$app->request->queryParams);
        $invitationDataProvider->query->andWhere(['id_workspace' => $id])->groupBy(['invitation_token']);
        $modules = Yii::$app->workspace->getSupportModules();
        $moduleArray = [];
        foreach ($modules as $key => $value) {
            $moduleArray[$key] = isset(Yii::$app->getModule($key)::$name) ? Yii::$app->getModule($key)::$name : $key;
        }
        $dynamicModuleModel = new DynamicModel();
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        foreach ($modules as $key => $value) {
            if (!isset($availableRoles[$key])) {
                continue;
            }
            $dynamicModuleModel->defineAttribute($key);
            // add rule for dynamic attributes
            $dynamicModuleModel->addRule($key, 'in', ['range' => $availableRoles[$key]]);
        }
        $users = User::find()->all();
        $usersEmail = [];
        foreach ($users as $key => $value) {
            $usersEmail[$value->email] = $value->username;
        }
        return $this->render('index', [
            'model' => $this->findWorkspace($id),
            'invitationModel' => $invitationModel,
            'invitationDataProvider' => $invitationDataProvider,
            'moduleArray' => $moduleArray,
            'dynamicModuleModel' => $dynamicModuleModel,
            'availableRoles' => $availableRoles,
            'usersEmail' => $usersEmail,
            'invitationSearchModel' => $invitationSearchModel
        ]);
    }

    /**
     * Creates a new Invitation model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @param int $id_workspace Id Workspace
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionCreate($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultCreateInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = new \portalium\workspace\models\InvitationForm();
        $model->id_workspace = $id;
        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                if (empty($model->emails)) {
                    return $this->redirect(['index', 'id' => $model->id_workspace]);
                }
                foreach ($model->emails as $email) {
                    $invitationToken = Yii::$app->security->generateRandomString();
                    $modules = Yii::$app->request->post('DynamicModel');
                    foreach ($modules as $key => $value) {
                        if ($value == 'none' || $value == null || $value == '' || !Yii::$app->workspace->isAvailableRole($key, $value) == false) {
                            continue;
                        }
                        $invitationModel = new Invitation();
                        $invitationModel->id_workspace = $model->id_workspace;
                        $invitationModel->email = $email;
                        $invitationModel->invitation_token = $invitationToken;
                        $invitationModel->date_create = date('Y-m-d H:i:s');
                        $invitationModel->module = $key;
                        $invitationModel->role = $value;
                        $invitationModel->status = Invitation::STATUS_PENDING;
                        $invitationModel->id_user = Yii::$app->user->id;
                        $invitationModel->date_expire = $model->date_expire;

                        if ($invitationModel->validate() && $invitationModel->save()) {
                            $invitationModel->sendInvitation();
                        } else {
                            Yii::$app->session->addFlash('error', Module::t('Invitation not sent.'));
                        }
                    }
                    Yii::$app->session->addFlash('success', Module::t('Invitation sent successfully.'));
                }
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->redirect(['index', 'id' => $id]);
    }

    /**
     * Resend a single Invitation model.
     * @param int $id_invitation Id Invitation
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionResend($id)
    {
        $model = Invitation::findOne($id);
        if (!\Yii::$app->user->can('workspaceWebDefaultResendInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if ($model) {
            $model->sendInvitation();
            Yii::$app->session->addFlash('success', Module::t('Invitation sent successfully.'));
        } else {
            Yii::$app->session->addFlash('error', Module::t('Invitation not sent.'));
        }
        return $this->redirect(['index', 'id' => $model->id_workspace]);
    }

    /**
     * Deletes an existing Invitation model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id_invitation Id Invitation
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id, $all = false)
    {
        $model = Invitation::findOne($id);
        if (!$model || (!\Yii::$app->user->can('workspaceWebDefaultDeleteInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->id_workspace)]))) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if ($model) {
            if ($all) {
                $invitations = Invitation::find()->where(['invitation_token' => $model->invitation_token])->all();
                foreach ($invitations as $invitation) {
                    $invitation->delete();
                }
            } else {
                $model->delete();
            }
            Yii::$app->session->addFlash('success', Module::t('Invitation deleted successfully.'));
        } else {
            Yii::$app->session->addFlash('error', Module::t('Invitation not deleted.'));
            if (Yii::$app->request->isAjax){
                return $this->asJson(['error' => Module::t('Invitation not deleted.')]);
            }
        }
        if (Yii::$app->request->isAjax){
            return $this->asJson(['success' => Module::t('Invitation deleted successfully.')]);
        }
        return $this->redirect(['index', 'id' => $model->id_workspace]);
    }

    /**
     * Accepts an invitation.
     *
     * This function accepts an invitation. It retrieves the invitation token from the request and uses it to retrieve
     * the corresponding invitation from the database. If the invitation is found, the user is assigned to the
     * workspace, and the invitation is accepted. Finally, the user is redirected to the workspace index page.
     *
     * @param string $token The invitation token.
     * @return \yii\web\Response The response object.
     */
    public function actionAccept($token)
    {
        $invitations = Invitation::find()->where(['invitation_token' => $token])->all();
        if (count($invitations) == 0) {
            Yii::$app->session->addFlash('error', Module::t('Invitation not accepted.'));
            return $this->redirect(['/']);
        }
        foreach ($invitations as $invitation) {
            if ($invitation && $invitation->date_expire > date('Y-m-d H:i:s') && $invitation->email == Yii::$app->user->identity->email) {
                $workspaceUser = WorkspaceUser::findOne([
                    'id_workspace' => $invitation->id_workspace,
                    'id_user' => Yii::$app->user->id,
                    'id_module' => $invitation->module,
                    'role' => $invitation->role
                ]);
                if ($workspaceUser) {
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                    $workspaceUser->save();
                } else {
                    if (!Yii::$app->workspace->isAvailableRole($invitation->module, $invitation->role)){
                        $hasError = true;
                        continue;
                    }

                    $workspaceUser = new WorkspaceUser();
                    $workspaceUser->id_workspace = $invitation->id_workspace;
                    $workspaceUser->id_user = Yii::$app->user->id;
                    $workspaceUser->role = $invitation->role;
                    $workspaceUser->id_module = $invitation->module;
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                    $workspaceUser->save();
                    $invitation->accept();
                }
            } else {
                $hasError = true;
            }
        }
        if (isset($hasError)) {
            Yii::$app->session->addFlash('error', Module::t('Invitation not accepted.'));
        } else {
            Yii::$app->session->addFlash('success', Module::t('Invitation accepted successfully.'));
        }
        return $this->redirect(['/']);
    }

    /**
     * Finds the Workspace model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id_invitation Id Workspace
     * @return Invitation the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_invitation)
    {
        if (($model = Invitation::findOne($id_invitation)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
    }

    /**
     * Details an existing Invitation model.
     * @param int $id_invitation Id Invitation
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDetail($id)
    {
        $model = Invitation::findOne($id);
        if (!\Yii::$app->user->can('workspaceWebDefaultView', ['id_module' => 'workspace', 'model' => $this->findWorkspace($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $searchModel = new InvitationSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere(['invitation_token' => $model->invitation_token]);
        return $this->render('detail', [
            'model' => $model,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }

    /**
     * Updates an existing Invitation model.
     * If update is successful, the browser will be redirected to the 'index' page.
     * @param int $id_invitation Id Invitation
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        $model = Invitation::findOne($id);
        if (!\Yii::$app->user->can('workspaceWebDefaultCreateInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        
        if ($model) {
            if ($model->load($this->request->post()) && $model->save()) {
                Yii::$app->session->addFlash('success', Module::t('Invitation updated successfully.'));
                return $this->redirect(['index', 'id' => $model->id_workspace]);
            }
        } else {
            Yii::$app->session->addFlash('error', Module::t('Invitation not updated.'));
        }
        $dynamicModuleModel = new DynamicModel();
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        // $availableRoles = $availableRoles[$model->module];
        $newAvailableRoles[$model->module] = $availableRoles[$model->module];
        foreach ($newAvailableRoles as $key => $value) {
            $dynamicModuleModel->defineAttribute($key);
            // add rule for dynamic attributes
            $dynamicModuleModel->addRule($key, 'in', ['range' => $value]);
            $dynamicModuleModel->$key = $model->role;
        }



        return $this->render('update', [
            'model' => $model,
            'dynamicModuleModel' => $dynamicModuleModel,
            'availableRoles' => $availableRoles,
        ]);
    }

    /**
     * Finds the Workspace model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id_workspace Id Workspace
     * @return Workspace the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findWorkspace($id_workspace)
    {
        if (($model = Workspace::findOne([Module::$tablePrefix . 'workspace.id_workspace' => $id_workspace])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
    }
}
