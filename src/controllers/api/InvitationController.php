<?php

namespace portalium\workspace\controllers\api;

use Yii;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationSearch;
use portalium\workspace\models\InvitationForm;
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use portalium\user\Module as UserModule;
use portalium\user\models\User as UserModel;
use portalium\rest\ActiveController as RestActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;
use yii\web\ServerErrorHttpException;


class InvitationController extends RestActiveController
{
    public $modelClass = Invitation::class;
    public function actions()
    {
        $actions = parent::actions();

        unset($actions['create'], $actions['update'], $actions['delete']);

        $invitationSearch = new InvitationSearch();

        $actions['index']['dataFilter'] = [
            'class' => \yii\data\ActiveDataFilter::class,
            'searchModel' => $invitationSearch,
        ];

        $actions['index']['prepareDataProvider'] = function ($action) use ($invitationSearch)
        {   
            //This added silence the warning when using ->with
            /** @var \yii\db\ActiveQuery $dataProvider->query */
            $dataProvider = $invitationSearch->search(Yii::$app->request->queryParams);

            $idWorkspace = Yii::$app->request->getQueryParam('id_workspace');
            $workspace = null;
            if($idWorkspace)
            {
                $workspace = $this->findWorkspace($idWorkspace);
            }
            if ($workspace)
            {
                if (!(Yii::$app->user->can('workspaceApiInvitationView') ||
                (Yii::$app->user->can('workspaceApiInvitationViewOwn') && $workspace->id_user == Yii::$app->user->id))) 
                {
                    throw new ForbiddenHttpException(Module::t('You are not allowed to access this workspace.'));
                }
                $filter = [Module::$tablePrefix . 'invitation_role.status' => InvitationRole::STATUS_PENDING];

                $dataProvider->query->andWhere(['id_workspace' => $idWorkspace])
                ->andWhere([
                    'id_invitation' => InvitationRole::find()
                        ->select('id_invitation')
                        ->where($filter)
                ])
                ->with(['invitationRole' => function ($query) use ($filter)
                {
                    $query->andWhere($filter)
                    ->select([Module::$tablePrefix . 'invitation_role.id_invitation',
                    UserModule::$tablePrefix . 'user.username AS username', 
                    UserModule::$tablePrefix . 'user.id_avatar AS id_avatar',
                    Module::$tablePrefix . 'invitation_role.role',
                    Module::$tablePrefix . 'invitation_role.module'])

                    ->leftJoin(UserModule::$tablePrefix . 'user',
                    UserModule::$tablePrefix . 'user.email = ' . Module::$tablePrefix . 'invitation_role.email');
                }
                ])
                ->asArray();
            }
            else // if no workspace is given, return invitations sent to the current user
            {
                $userEmail = Yii::$app->user->identity->email;

                if ($userEmail === null || $userEmail === '')
                {
                    throw new UnprocessableEntityHttpException(Module::t('Current user doesnt have a email'));
                } 
                else 
                {
                    $filter = [Module::$tablePrefix . 'invitation_role.email' => $userEmail,
                    Module::$tablePrefix . 'invitation_role.status' => InvitationRole::STATUS_PENDING];
                    
                    $dataProvider->query
                    ->andWhere([
                        'id_invitation' => InvitationRole::find()
                            ->select('id_invitation')
                            ->where($filter)
                    ])
                    ->select([Module::$tablePrefix . 'invitation.*',
                    Module::$tablePrefix . 'workspace.name AS workspace_name'])
                    
                    ->leftJoin(Module::$tablePrefix . 'workspace',
                    Module::$tablePrefix . 'workspace.id_workspace = ' . Module::$tablePrefix . 'invitation.id_workspace')

                    ->with(['invitationRole' => function ($query) use ($filter) {
                            $query->andWhere($filter);
                        }
                    ])
                    ->asArray();
                }
            }
            $dataProvider->pagination = [
                'defaultPageSize' => 20,
                'validatePage'    => false,
                'pageSizeLimit' => [1,1000000]
            ];

            return $dataProvider;
        };

        return $actions;
    }

    /**
     * POST /invitations
     * 
     * Body params:
     *   - id_workspace (int, required)
     *   - date_expire (string, required)
     *   - usernames (array, required)
     *   - modules (array, required) e.g., {"printer": {"admin","user"}}
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        $idWorkspace = $request->post('id_workspace');

        if (!$idWorkspace) {
            throw new BadRequestHttpException(Module::t('id_workspace is required.'));
        }

        $workspace = $this->findWorkspace($idWorkspace);
        if (!(Yii::$app->user->can('workspaceApiInvitationCreate') ||
        (Yii::$app->user->can('workspaceApiInvitationCreateOwn') && $workspace->id_user == Yii::$app->user->id) ||
        (Yii::$app->workspace->can('workspace','workspaceApiInvitationCreate') && Yii::$app->workspace->id == $idWorkspace)))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to create invitations for this workspace.'));
        }

        $model = new InvitationForm();
        $model->id_workspace = $idWorkspace;
        if ($model->load($request->getBodyParams(), '')) {          
            $modules = $request->post('modules', []);
            if(empty($modules)) {
                throw new UnprocessableEntityHttpException(Module::t('Modules list cannot be empty.'));
            }
            $usernames = $request->post('usernames', []);
            if (empty($usernames))
            {
                throw new UnprocessableEntityHttpException(Module::t('usernames list cannot be empty.'));
            }


            $invitationModel = new Invitation();
            $invitationModel->id_workspace = $model->id_workspace;
            $invitationModel->date_create = date('Y-m-d H:i:s');
            $invitationModel->date_expire = $model->date_expire;
            $invitationModel->id_user = Yii::$app->user->id;
            $invitationModel->invitation_token = Yii::$app->security->generateRandomString();


            if ($invitationModel->validate() && $invitationModel->save())
            {
                $this->createInvitation($model, $invitationModel, $modules, $usernames);

                $webLink = Yii::$app->request->hostInfo . '/workspace/invitation/accept?token=' . $invitationModel->invitation_token;
                
                return ['model' => $invitationModel,
                        'link' => $webLink];
            }

            throw new UnprocessableEntityHttpException(json_encode($invitationModel->getFirstErrors()));
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * PUT/PATCH /invitation/:id
     */
    public function actionUpdate($id)
    {
        $modelInvitation = $this->findModel($id);
        $workspace = $this->findWorkspace($modelInvitation->id_workspace);

        if (!(Yii::$app->user->can('workspaceApiInvitationUpdate') ||
        (Yii::$app->user->can('workspaceApiInvitationUpdateOwn') && $workspace->id_user == Yii::$app->user->id)||
        (Yii::$app->workspace->can('workspace','workspaceApiInvitationUpdate') && Yii::$app->workspace->id == $workspace->id_workspace)))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to update this invitation.'));
        }

        $request = Yii::$app->request;
        $model = new InvitationForm();

        if ($model->load($request->getBodyParams(), '')) {

            $usernames = $request->post('usernames', []);
            if (!empty($usernames))
            {   
                $modules = $request->getBodyParam('modules', []);
                $this->createInvitation($model, $modelInvitation, $modules, $usernames);
            }

            $modelInvitation->date_expire = $model->date_expire;
            
            if (!$modelInvitation->save()) {
                throw new UnprocessableEntityHttpException(json_encode($modelInvitation->getFirstErrors()));
            }

            return $modelInvitation;
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * DELETE /invitations/:id?all=true|false
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $workspace = $this->findWorkspace($model->id_workspace);

        if (!(Yii::$app->user->can('workspaceApiInvitationDelete') ||
        (Yii::$app->user->can('workspaceApiInvitationDeleteOwn') && $workspace->id_user == Yii::$app->user->id) ||
        (Yii::$app->workspace->can('workspace','workspaceApiInvitationDelete') && Yii::$app->workspace->id == $workspace->id_workspace)))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to delete invitations.'));
        }

        $all = filter_var(Yii::$app->request->getQueryParam('all', false), FILTER_VALIDATE_BOOLEAN);

        if ($all) {
            $invitations = Invitation::find()->where(['invitation_token' => $model->invitation_token])->all();
            foreach ($invitations as $invitation) {
                $invitation->delete();
            }
        } else {
            $model->delete();
        }

        Yii::$app->getResponse()->setStatusCode(204);
        return [];
    }

    /**
     * POST /invitations/resend/:id
     * Expects InvitationRole ID, not Invitation ID.
     */
    public function actionResend($id)
    {
        $model = InvitationRole::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException(Module::t('Invitation role not found.'));
        }

        $workspace = $this->findWorkspace($model->invitation->id_workspace);
        
        if (!(Yii::$app->user->can('workspaceApiInvitationResend') || 
        (Yii::$app->user->can('workspaceApiInvitationResendOwn') && $workspace->id_user == Yii::$app->user->id) ||
        (Yii::$app->workspace->can('workspace','workspaceApiInvitationResend') && Yii::$app->workspace->id == $workspace->id_workspace)))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to resend invitations.'));
        }

        if ($model->sendInvitation()) {
            return ['success' => true, 'message' => Module::t('Invitation sent successfully.')];
        }

        throw new ServerErrorHttpException(Module::t('Failed to send invitation.'));
    }

    /**
     * POST|GET /invitations/accept?token=:token
     */
    public function actionAccept($token)
    {
        if (empty($token)) {
            throw new BadRequestHttpException(Module::t('Token is required.'));
        }

        $invitation = Invitation::find()->where(['invitation_token' => $token])->one();

        if (!$invitation) {
            throw new NotFoundHttpException(Module::t('Invitation not found or invalid token.'));
        }

        $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
        'email' => Yii::$app->user->identity->email])->all();

        $hasError = false;
        $processed = false;

        foreach ($invitationRoles as $invitationRole) {
            if ($invitationRole && $invitationRole->status == InvitationRole::STATUS_PENDING && $invitationRole->invitation->date_expire > date('Y-m-d H:i:s')) {
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
                    if (!Yii::$app->workspace->isAvailableRole($invitationRole->module, $invitationRole->role)) {
                        $hasError = true;
                        continue;
                    }

                    $workspaceUser = new WorkspaceUser();
                    $workspaceUser->id_workspace = $invitationRole->id_workspace;
                    $workspaceUser->id_user = Yii::$app->user->id;
                    $workspaceUser->role = $invitationRole->role;
                    $workspaceUser->id_module = $invitationRole->module;
                    $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
                    
                    if ($workspaceUser->save()) {
                        $invitationRole->accept(); // Set accepted status[cite: 4]
                        $processed = true;
                    } else {
                        $hasError = true;
                    }
                }
            } else {
                $hasError = true;
            }
        }

        if ($hasError && !$processed) {
            throw new UnprocessableEntityHttpException(Module::t('Invitation could not be accepted. It may be expired or not assigned to your email.'));
        }

        return [
            'success' => true, 
            'message' => Module::t('Invitation accepted successfully.'),
            'partial_errors' => $hasError
        ];
    }

    /**
     * POST /invitations/reject
     */
    public function actionReject()
    {
        $token = Yii::$app->request->post('token');

        if (empty($token)) {
            throw new BadRequestHttpException(Module::t('Token is required.'));
        }

        $invitation = Invitation::find()->where(['invitation_token' => $token])->one();

        if (!$invitation) {
            throw new NotFoundHttpException(Module::t('Invitation not found or invalid token.'));
        }

        $username = Yii::$app->request->post('username');

        if($username != null) //for cancelling workspaces outgoing invitation
        {
            if(!($invitation->id_workspace == Yii::$app->workspace->id && 
            ((Yii::$app->user->can('workspaceApiInvitationUpdateOwn') && Yii::$app->workspace->id_user == Yii::$app->user->id)||
            Yii::$app->workspace->can('workspace','workspaceApiInvitationUpdate') || Yii::$app->user->can('workspaceApiInvitationUpdate'))))
            {
                throw new ForbiddenHttpException(Module::t('You are not allowed to update this invitation.'));
            }
            $user_email = UserModel::findByUsername($username)->getEmail();

            $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
            'email' => $user_email])->all();
        }
        else //for cancelling users incoming invitation
        {
            $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
            'email' => Yii::$app->user->identity->email])->all();
        }

        $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
        'email' => Yii::$app->user->identity->email])->all();

        if (empty($invitationRoles)) {
            throw new UnprocessableEntityHttpException(Module::t('No invitation roles found for your email.'));
        }

        foreach ($invitationRoles as $invitationRole) {
            if ($invitationRole && $invitationRole->status == InvitationRole::STATUS_PENDING && $invitationRole->invitation->date_expire > date('Y-m-d H:i:s')) {
                $invitationRole->reject();
            }
        }

        return [
            'success' => true,
            'message' => Module::t('Invitation rejected successfully.')
        ];
    }

    /**
     * Post //invitations/reject-expired
     * Post //invitations/reject_expired
     * Post //invitations/rejectExpired
     * 
     * 
     * @return array
     */
    public function actionRejectExpired()
    {
        Yii::error('User :' . Yii::$app->user->id . " calling for reject expire");

        if (!Yii::$app->user->can('workspaceApiInvitationExpireCron'))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to reject expired invitations.'));
        }

        $expiredInvitationIds = Invitation::find()
            ->select('id_invitation')
            ->where(['<=', 'date_expire', date('Y-m-d H:i:s')]);

        $rejectedCount = InvitationRole::updateAll(
            ['status' => InvitationRole::STATUS_REJECTED],
            [
                'and',
                ['status' => InvitationRole::STATUS_PENDING],
                ['id_invitation' => $expiredInvitationIds],
            ]
        );

        return [
            'success' => true,
            'rejected_count' => $rejectedCount,
            'message' => Module::t('{count} expired invitation roles rejected.', [
                'count' => $rejectedCount,
            ]),
        ];
    }

    /**
     * Creates a new Invitation model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @param \portalium\workspace\models\InvitationForm $model
     * @param \portalium\workspace\models\Invitation $invitationModel
     * @return void
     * @throws NotFoundHttpException if the model cannot be found
     */
    private function createInvitation($model, $invitationModel, $modules, $usernames)
    {
        foreach ($usernames as $username)
        {
            $user = \portalium\user\models\User::findOne(['username' => $username]);
            if (!$user)
            {
                Yii::error(Module::t('User not found: {username}', ['username' => $username]), __METHOD__);
                continue; // Skip this username and continue with the next one

                //throw new NotFoundHttpException(Module::t('User not found: {username}', ['username' => $username]));
            }
            foreach ($modules as $id_module => $roles)
            {
                foreach ($roles as $value)
                {
                    if ($value == 'none' || $value == null || $value == '' || Yii::$app->workspace->isAvailableRole($id_module, $value) == false) {
                        continue;
                    }
                    $invitationRoleModel = new InvitationRole();
                    $invitationRoleModel->id_workspace = $invitationModel->id_workspace;
                    $invitationRoleModel->id_invitation = $invitationModel->id_invitation;
                    $invitationRoleModel->email = $user->email;
                    $invitationRoleModel->module = $id_module;
                    $invitationRoleModel->role = $value;
                    $invitationRoleModel->status = InvitationRole::STATUS_PENDING;

                    if ($invitationRoleModel->validate() && $invitationRoleModel->save()) {
                        $invitationRoleModel->sendInvitation();
                    } else {
                    }
                }
            }
        }
        Yii::$app->session->addFlash('success', Module::t('Invitation sent successfully.'));
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