<?php

namespace portalium\workspace\controllers\api;

use Yii;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationSearch;
use portalium\workspace\models\InvitationForm;
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\Workspace;
use portalium\workspace\Module;
use portalium\user\Module as UserModule;
use portalium\user\models\User as UserModel;
use portalium\workspace\components\InvitationAcceptance;
use portalium\rest\ActiveController as RestActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;
use yii\web\UnauthorizedHttpException;
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
            } elseif (!Yii::$app->user->can('workspaceApiInvitationView')) {
                $ownWorkspaceIds = Workspace::find()->select('id_workspace')->where(['id_user' => Yii::$app->user->id])->column();
                $dataProvider->query->andWhere(['id_workspace' => $ownWorkspaceIds]);
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
                    ]);

                    $workspaceIds = (clone $dataProvider->query)
                        ->select(Module::$tablePrefix . 'invitation.id_workspace')
                        ->distinct()
                        ->column();

                    $memberCounts = Yii::$app->workspace->getMemberCounts($workspaceIds);

                    $memberCountCase = 'CASE ' . Module::$tablePrefix . 'invitation.id_workspace';
                    foreach ($memberCounts as $idWorkspace => $memberCount) {
                        $memberCountCase .= ' WHEN ' . $idWorkspace . ' THEN ' . $memberCount;
                    }
                    $memberCountCase .= ' ELSE 0 END AS workspace_member_count';
                    
                    $dataProvider->query
                    ->select([Module::$tablePrefix . 'invitation.*',
                    Module::$tablePrefix . 'workspace.name AS workspace_name',
                    new \yii\db\Expression($memberCountCase)])
                    
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
     *
     * Her kullanıcıya kendi bağlantısı olan ayrı bir davet açılır. Cevap: ilk davet `model` ve `link`
     * (eski istemciler için), hepsi `invitations` içinde (id_invitation, email, link).
     * Zaten üye olan ve bekleyen daveti olan kullanıcılara davet açılmaz (karar 13); kullanıcı adları
     * `skipped.members` / `skipped.pending` altında döner. Kimseye davet açılamazsa 422.
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
            if (!$model->validate()) {
                throw new UnprocessableEntityHttpException(json_encode($model->getFirstErrors()));
            }
            if ($model->date_expire === null || $model->date_expire === '') {
                $model->date_expire = date('Y-m-d H:i:s', strtotime(InvitationForm::DEFAULT_EXPIRY));
            }
            $modules = $request->post('modules', []);
            if(empty($modules)) {
                throw new UnprocessableEntityHttpException(Module::t('Modules list cannot be empty.'));
            }
            $usernames = $request->post('usernames', []);
            if (empty($usernames))
            {
                throw new UnprocessableEntityHttpException(Module::t('usernames list cannot be empty.'));
            }

            $found = $this->emailsOf((array) $usernames);
            $skipped = Invitation::existingAudience((int) $model->id_workspace, array_keys($found));
            $recipients = array_diff_key($found, array_flip(array_merge($skipped['members'], $skipped['pending'])));
            $created = $this->inviteUsers((int) $model->id_workspace, array_keys($recipients), (array) $modules, (string) $model->date_expire);
            if ($created === []) {
                throw new UnprocessableEntityHttpException($this->nothingCreatedMessage($skipped, $recipients === []));
            }
            $this->sendMails($created);

            $invitations = array_map(fn (Invitation $invitation) => [
                'id_invitation' => $invitation->id_invitation,
                'email' => $invitation->inviteeEmail(),
                'link' => $this->acceptLink($invitation),
            ], $created);

            return [
                'model' => $created[0],
                'link' => $invitations[0]['link'],
                'invitations' => $invitations,
                'skipped' => [
                    'members' => array_values(array_map(static fn (string $email) => $found[$email], $skipped['members'])),
                    'pending' => array_values(array_map(static fn (string $email) => $found[$email], $skipped['pending'])),
                ],
            ];
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * PUT/PATCH /invitation/:id
     *
     * Karar 38 (2026-10-19, karar 29'un API karşılığı): bekleyen davette `modules` rolleri yerine koyar (eklemez;
     * modül başına ilk atanabilir rol, listelenmeyen modülün bekleyen rolü kalkar). E-posta gitmez, bağlantı aynı kalır;
     * yeniden gönderme ayrı uçtur (resend). Gövdedeki `usernames` yok sayılır: güncelleme başka adrese davet açmaz
     * (yeni davet POST ile). Atanabilir rol kalmıyorsa 422. Kabul edilmiş davet 422.
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

        if ($modelInvitation->isAccepted()) {
            throw new UnprocessableEntityHttpException(Module::t(
                'This invitation has been accepted; its roles cannot be changed. To change the member\'s roles, use the Members and roles page.'
            ));
        }

        $request = Yii::$app->request;
        $model = new InvitationForm();
        $model->id_workspace = $modelInvitation->id_workspace;

        if ($model->load($request->getBodyParams(), '')) {
            if (!$model->validate()) {
                throw new UnprocessableEntityHttpException(json_encode($model->getFirstErrors()));
            }

            if ($model->date_expire !== null && $model->date_expire !== '') {
                $modelInvitation->date_expire = $model->date_expire;
            }

            $modules = $request->getBodyParam('modules');
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($modules !== null && !$modelInvitation->updateRoles($this->moduleRoles((array) $modules))) {
                    throw new UnprocessableEntityHttpException(Module::t('Select a role for at least one module.'));
                }
                if (!$modelInvitation->save()) {
                    throw new UnprocessableEntityHttpException(json_encode($modelInvitation->getFirstErrors()));
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

            return $modelInvitation;
        }

        throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
    }

    /**
     * API gövdesindeki modules ({"modül": ["rol", ...]} ya da {"modül": "rol"}) → modül => ilk atanabilir rol.
     *
     * @return array<string,string>
     */
    private function moduleRoles(array $modules): array
    {
        $roles = [];
        foreach ($this->rolePairs($modules) as [$module, $role]) {
            $roles[$module] ??= $role;
        }

        return $roles;
    }

    /**
     * DELETE /invitations/:id?all=true|false
     *
     * Kabul edilmiş davet silinmez (karar 31): tekil silme 422; all=true kabul edilmişleri atlar.
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
        $targets = ($all && $model->hasLink())
            ? Invitation::find()->where(['invitation_token' => $model->invitation_token, 'id_workspace' => $model->id_workspace])->all()
            : [$model];
        $deletable = array_filter($targets, static fn (Invitation $invitation) => !$invitation->isAccepted());
        if ($deletable === []) {
            throw new UnprocessableEntityHttpException(
                Module::t('An accepted invitation cannot be deleted. To remove the membership, use the Members and roles page.')
            );
        }
        foreach ($deletable as $invitation) {
            $invitation->delete();
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
     *
     * No workspace switch happens after accepting via the API (decision 12); the client picks
     * the workspace itself. The response includes the workspace's id and title.
     */
    public function actionAccept()
    {
        $token = Yii::$app->request->getBodyParam('token');

        if (empty($token)) {
            throw new BadRequestHttpException(Module::t('Token is required.'));
        }

        $user = Yii::$app->user->identity;
        if ($user === null) {
            throw new UnauthorizedHttpException(Module::t('Login required.'));
        }
        $acceptance = new InvitationAcceptance();
        $outcome = $acceptance->accept((string) $token, $user, false);
        $message = $acceptance->message($outcome, $user);

        if ($outcome['result'] === InvitationAcceptance::NOT_FOUND) {
            throw new NotFoundHttpException($message);
        }
        if (!InvitationAcceptance::isAccepted($outcome['result'])) {
            throw new UnprocessableEntityHttpException($message);
        }

        $workspace = $outcome['id_workspace'] === null ? null : Workspace::findOne($outcome['id_workspace']);

        return [
            'success' => true,
            'message' => $message,
            'reason' => $outcome['result'],
            'id_workspace' => $outcome['id_workspace'],
            'title' => $workspace === null ? null : $workspace->displayTitle(),
            'partial_errors' => $outcome['result'] === InvitationAcceptance::PARTIAL,
            'switched' => $outcome['switched'],
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

        if ($username != null || !empty($username)) {
            // for cancelling a workspace's outgoing invitation
            if (!($invitation->id_workspace == Yii::$app->workspace->id &&
                ((Yii::$app->user->can('workspaceApiInvitationUpdateOwn') && Yii::$app->workspace->id_user == Yii::$app->user->id) ||
                Yii::$app->workspace->can('workspace', 'workspaceApiInvitationUpdate') || Yii::$app->user->can('workspaceApiInvitationUpdate')))) {
                throw new ForbiddenHttpException(Module::t('You are not allowed to update this invitation.'));
            }
            $user_email = UserModel::findByUsername($username)->getEmail();

            $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
            'email' => $user_email])->all();
        } else {
            // for cancelling the current user's incoming invitation
            $invitationRoles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation,
            'email' => Yii::$app->user->identity->email])->all();
        }

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
     * POST /invitations/reject-expired
     *
     * @return array
     */
    public function actionRejectExpired()
    {
        if (!Yii::$app->user->can('workspaceApiInvitationExpireCron')) {
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
     * Opens one invitation per address, each with its own link. Roles that can't be assigned are skipped.
     *
     * @param string[] $emails
     * @return Invitation[]
     */
    private function inviteUsers(int $idWorkspace, array $emails, array $modules, string $dateExpire): array
    {
        $pairs = $this->rolePairs($modules);
        if ($pairs === []) {
            return [];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $created = [];
            foreach ($emails as $email) {
                $invitation = Invitation::createForEmail($idWorkspace, (int) Yii::$app->user->id, $email, $pairs, $dateExpire);
                if ($invitation !== null) {
                    $created[] = $invitation;
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        return $created;
    }

    /**
     * @return array<string,string> kullanıcı adlarına karşılık gelen küçük harfli, tekrarsız adres => kullanıcı adı
     */
    private function emailsOf(array $usernames): array
    {
        $emails = [];
        foreach ($usernames as $username) {
            $user = is_scalar($username) ? \portalium\user\models\User::findOne(['username' => (string) $username]) : null;
            if ($user === null) {
                Yii::error(Module::t('User not found: {username}', ['username' => is_scalar($username) ? $username : '']), __METHOD__);
                continue;
            }
            $emails[Invitation::normalizeEmail($user->email)] ??= (string) $user->username;
        }
        return $emails;
    }

    /**
     * @param array{members: string[], pending: string[]} $skipped
     */
    private function nothingCreatedMessage(array $skipped, bool $allSkipped): string
    {
        if (!$allSkipped || ($skipped['members'] === [] && $skipped['pending'] === [])) {
            return Module::t('No invitation was created. Check the usernames and roles.');
        }
        return Module::t('No invitation was created: these users are already members of this workspace or already have a pending invitation.');
    }

    /**
     * @return array<int,array{0:string,1:string}> atanabilir [modül, rol] çiftleri
     */
    private function rolePairs(array $modules): array
    {
        $pairs = [];
        foreach ($modules as $module => $roles) {
            foreach ((array) $roles as $role) {
                if (!is_string($role) || $role === '' || $role === 'none' || !Yii::$app->workspace->isAvailableRole($module, $role)) {
                    continue;
                }
                $pairs[] = [(string) $module, $role];
            }
        }
        return $pairs;
    }

    /**
     * @param Invitation[] $invitations
     */
    private function sendMails(array $invitations): void
    {
        foreach ($invitations as $invitation) {
            if (!$invitation->sendMail()) {
                Yii::warning(['id_invitation' => $invitation->id_invitation, 'message' => 'invitation mail not sent'], __METHOD__);
            }
        }
    }

    private function acceptLink(Invitation $invitation): string
    {
        return Yii::$app->request->hostInfo . '/workspace/invitation/accept?token=' . $invitation->invitation_token;
    }

    /**
     * Miras alınan view action'ı için: davet yalnızca yetkili ya da çalışma alanı sahibi tarafından görülür.
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        if ($model === null) {
            return;
        }
        $workspace = $this->findWorkspace($model->id_workspace);
        $isOwner = (int) $workspace->id_user === (int) Yii::$app->user->id;
        $canView = Yii::$app->user->can('workspaceApiInvitationView')
            || ($isOwner && Yii::$app->user->can('workspaceApiInvitationViewOwn', ['model' => $workspace]));
        if (!$canView) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this workspace.'));
        }
    }

    /**
     * Finds the Invitation model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id_invitation Id Invitation
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