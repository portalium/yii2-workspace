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
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\InvitationSearch;
use portalium\workspace\models\InvitationForm;

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
        // $invitationDataProvider = new \portalium\data\ActiveDataProvider([
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
     * @param int $id Id Workspace
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
                $error = $this->invitationInputError($model->emails, $model->date_expire);
                if ($error !== null) {
                    Yii::$app->session->addFlash('error', $error);
                    return $this->redirect(['index', 'id' => $model->id_workspace]);
                }
                $invitationModel = new Invitation();
                $invitationModel->id_workspace = $model->id_workspace;
                $invitationModel->date_create = date('Y-m-d H:i:s');
                $invitationModel->date_expire = $this->expiryOf($model->date_expire);
                $invitationModel->id_user = Yii::$app->user->id;
                $invitationModel->invitation_token = Yii::$app->security->generateRandomString();

                if ($invitationModel->validate() && $invitationModel->save()) {
                    $this->createInvitation($model, $invitationModel);
                }
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->redirect(['index', 'id' => $id]);
    }

    /**
     * Creates a new Invitation model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @param \portalium\workspace\models\InvitationForm $model
     * @param \portalium\workspace\models\Invitation $invitationModel
     * @return void
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function createInvitation($model, $invitationModel)
    {
        $created = 0;
        foreach ($model->emails as $email) {
            foreach ($this->requestedRoles() as $key => $value) {
                $invitationRoleModel = new InvitationRole();
                $invitationRoleModel->id_workspace = $invitationModel->id_workspace;
                $invitationRoleModel->id_invitation = $invitationModel->id_invitation;
                $invitationRoleModel->email = $email;
                $invitationRoleModel->module = $key;
                $invitationRoleModel->role = $value;
                $invitationRoleModel->status = InvitationRole::STATUS_PENDING;

                if ($invitationRoleModel->validate() && $invitationRoleModel->save()) {
                    $invitationRoleModel->sendInvitation();
                    $created++;
                }
            }
        }
        if ($created === 0) {
            // A fresh invitation without any role cannot be accepted; do not leave it behind.
            if (!InvitationRole::find()->where(['id_invitation' => $invitationModel->id_invitation])->exists()) {
                $invitationModel->delete();
            }
            Yii::$app->session->addFlash('error', Module::t('Invitation not sent.'));
            return;
        }
        Yii::$app->session->addFlash('success', Module::t('Invitation sent successfully.'));
    }

    /**
     * Module => role pairs chosen in the form, limited to the roles the module offers.
     *
     * @return array
     */
    private function requestedRoles()
    {
        $modules = Yii::$app->request->post('DynamicModel');
        $roles = [];
        if (!is_array($modules)) {
            return $roles;
        }
        foreach ($modules as $key => $value) {
            if (is_string($value) && $value !== '' && $value !== 'none' && Yii::$app->workspace->isAvailableRole($key, $value)) {
                $roles[$key] = $value;
            }
        }
        return $roles;
    }

    /**
     * Why the invitation form cannot be accepted, or null when it can.
     *
     * @param array $emails
     * @param string|null $dateExpire
     * @return string|null
     */
    private function invitationInputError($emails, $dateExpire)
    {
        if ($this->requestedRoles() === []) {
            return Module::t('Select a role for at least one module.');
        }
        $validator = new \yii\validators\EmailValidator();
        foreach ((array) $emails as $email) {
            if (!is_string($email) || !$validator->validate($email)) {
                return Module::t('{email} is not a valid e-mail address.', ['email' => is_string($email) ? $email : '']);
            }
        }
        return $this->expiryError($dateExpire);
    }

    /**
     * @param string|null $dateExpire
     * @return string|null
     */
    private function expiryError($dateExpire)
    {
        if ($this->expiryOf($dateExpire) === null) {
            return Module::t('Choose a valid expiry date.');
        }
        if (substr((string) $dateExpire, 0, 10) < date('Y-m-d')) {
            return Module::t('The expiry date cannot be in the past.');
        }
        return null;
    }

    /**
     * The form sends a day; the invitation stays valid until the end of it (accept compares with the current time).
     *
     * @param string|null $dateExpire
     * @return string|null 'Y-m-d 23:59:59', or null when the value is not a date
     */
    private function expiryOf($dateExpire)
    {
        $day = substr(trim((string) $dateExpire), 0, 10);
        $date = \DateTime::createFromFormat('!Y-m-d', $day);
        if ($date === false || $date->format('Y-m-d') !== $day) {
            return null;
        }
        return $day . ' 23:59:59';
    }

    /**
     * Resend a single Invitation model.
     * @param int $id Id Invitation
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionResend($id)
    {
        $model = InvitationRole::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
        }
        if (!\Yii::$app->user->can('workspaceWebDefaultResendInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->invitation->id_workspace)])) {
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
     * @param int $id Id Invitation
     * @param bool $all Delete all invitations with the same token
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
            if (Yii::$app->request->isAjax) {
                return $this->asJson(['error' => Module::t('Invitation not deleted.')]);
            }
        }
        if (Yii::$app->request->isAjax) {
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
        $invitation = Invitation::find()->where(['invitation_token' => $token])->one();

        if (!$invitation) {
            Yii::$app->session->addFlash('error', Module::t('Invitation not accepted.'));
            return $this->redirect(['/']);
        }
        $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation, 
        'email' => Yii::$app->user->identity->email])->all();

        $processed = false;
        foreach ($invitationRoles as $invitationRole) {
            if ($invitationRole && $invitationRole->status == InvitationRole::STATUS_PENDING && $invitationRole->invitation->date_expire > date('Y-m-d H:i:s'))
            {
                $workspaceUser = WorkspaceUser::findOne([
                    'id_workspace' => $invitationRole->id_workspace,
                    'id_user' => Yii::$app->user->id,
                    'id_module' => $invitationRole->module,
                    'role' => $invitationRole->role
                ]);
                if ($workspaceUser) {
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                    $workspaceUser->save();
                    $invitationRole->accept();
                    $processed = true;
                } 
                else 
                {
                    if (!Yii::$app->workspace->isAvailableRole($invitationRole->module, $invitationRole->role))
                    {
                        $hasError = true;
                        continue;
                    }

                    $workspaceUser = new WorkspaceUser();
                    $workspaceUser->id_workspace = $invitationRole->id_workspace;
                    $workspaceUser->id_user = Yii::$app->user->id;
                    $workspaceUser->role = $invitationRole->role;
                    $workspaceUser->id_module = $invitationRole->module;
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                    // A refused row (e.g. the member limit veto) must leave the invitation pending.
                    if ($workspaceUser->save()) {
                        $invitationRole->accept();
                        $processed = true;
                    } else {
                        $hasError = true;
                    }
                }
            } else {
                $hasError = true;
            }
        }
        if (isset($hasError) || !$processed) {
            Yii::$app->session->addFlash('error', Module::t('Invitation not accepted.'));
        } else {
            Yii::$app->session->addFlash('success', Module::t('Invitation accepted successfully.'));
        }
        return $this->redirect(['/']);
    }

    /**
     * Rejects an invitation.
     *
     * This function rejects an invitation. It retrieves the invitation token from the request and uses it to retrieve
     * the corresponding invitation from the database. If the invitation is found, all pending invitation roles
     * assigned to the current user are rejected.
     *
     * @param string $token The invitation token.
     * @return \yii\web\Response The response object.
     */
    public function actionReject($token)
    {
        $invitation = Invitation::find()->where(['invitation_token' => $token])->one();

        if (!$invitation) {
            Yii::$app->session->addFlash('error', Module::t('Invitation not rejected.'));
            return $this->redirect(['/']);
        }

        $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
        'email' => Yii::$app->user->identity->email])->all();

        if (empty($invitationRoles)) {
            Yii::$app->session->addFlash('error', Module::t('No invitation roles found for your email.'));
            return $this->redirect(['/']);
        }

        foreach ($invitationRoles as $invitationRole) {
            if ($invitationRole && $invitationRole->status == InvitationRole::STATUS_PENDING && $invitationRole->invitation->date_expire > date('Y-m-d H:i:s')) {
                $invitationRole->reject();
            }
        }

        Yii::$app->session->addFlash('success', Module::t('Invitation rejected successfully.'));
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
        if (!\Yii::$app->user->can('workspaceWebDefaultView', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        // $searchModel = new InvitationSearch();
        // $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        // $dataProvider->query->andWhere(['invitation_token' => $model->invitation_token]);
        $dataProvider = new \portalium\data\ActiveDataProvider([
            'query' => InvitationRole::find()->where(['id_invitation' => $model->id_invitation]),
        ]);

        return $this->render('detail', [
            'model' => $model,
            'dataProvider' => $dataProvider
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
        $modelInvitation = Invitation::findOne($id);
        if ($modelInvitation === null) {
            throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
        }
        if (!\Yii::$app->user->can('workspaceWebDefaultCreateInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($modelInvitation->id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = new InvitationForm();
        if ($modelInvitation) {
            if ($model->load($this->request->post())) {
                // Yii::$app->session->addFlash('success', Module::t('Invitation updated successfully.'));
                // return $this->redirect(['index', 'id' => $model->id_workspace]);
                $error = empty($model->emails)
                    ? $this->expiryError($model->date_expire)
                    : $this->invitationInputError($model->emails, $model->date_expire);
                if ($error !== null) {
                    Yii::$app->session->addFlash('error', $error);
                    return $this->redirect(['update', 'id' => $modelInvitation->id_invitation]);
                }
                if (empty($model->emails)) {
                    $modelInvitation->date_expire = $this->expiryOf($model->date_expire);
                    if ($modelInvitation->save()) {
                        Yii::$app->session->addFlash('success', Module::t('Invitation updated successfully.'));
                        return $this->redirect(['index', 'id' => $modelInvitation->id_workspace]);
                    }
                } else {
                    $this->createInvitation($model, $modelInvitation);
                    $modelInvitation->date_expire = $this->expiryOf($model->date_expire);
                    if ($modelInvitation->save()) {
                        Yii::$app->session->addFlash('success', Module::t('Invitation updated successfully.'));
                        return $this->redirect(['index', 'id' => $modelInvitation->id_workspace]);
                    }
                }
            }

            $dynamicModuleModel = new DynamicModel();
            $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
            foreach ($availableRoles as $key => $value) {
                $dynamicModuleModel->defineAttribute($key);
                $dynamicModuleModel->addRule($key, 'in', ['range' => $value]);
            }
            $model->date_expire = $modelInvitation->date_expire;
            $users = User::find()->all();
            $usersEmail = [];
            foreach ($users as $key => $value) {
                $usersEmail[$value->email] = $value->username;
            }
            return $this->render('update', [
                'model' => $model,
                'dynamicModuleModel' => $dynamicModuleModel,
                'availableRoles' => $availableRoles,
                'modelInvitation' => $modelInvitation,
                'usersEmail' => $usersEmail
            ]);
        }
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
