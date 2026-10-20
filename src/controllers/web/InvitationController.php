<?php

namespace portalium\workspace\controllers\web;

use Yii;
use yii\base\DynamicModel;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use portalium\workspace\Module;
use yii\web\NotFoundHttpException;
use yii\web\UnprocessableEntityHttpException;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\Invitation;
use portalium\web\Controller as WebController;
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\InvitationSearch;
use portalium\workspace\models\InvitationForm;
use portalium\workspace\components\InvitationAcceptance;
use portalium\workspace\components\InvitationSignup;

class InvitationController extends WebController
{
    private const DRAFT_KEY = 'workspace.invitationDraft.';

    /** Liste sayfasının üstünde kapatılana kadar duran uyarılar (oturumda; çalışma alanı kimliği eklenir, bir kez okunur). */
    public const NOTICE_KEY = 'workspace.invitationNotices.';

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        array_unshift($behaviors['access']['rules'], [
            'actions' => ['accept'],
            'allow' => true,
            'roles' => ['?'],
            'verbs' => ['GET'],
        ]);
        $behaviors['verbs'] = [
            'class' => VerbFilter::className(),
            'actions' => [
                'delete' => ['POST'],
                'resend' => ['POST'],
                'switch-account' => ['POST'],
            ],
        ];

        return $behaviors;
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
        $draft = $this->takeDraft($id);
        $notices = $this->takeNotices($id);
        $invitationSearchModel = new \portalium\workspace\models\InvitationSearch();
        // $invitationDataProvider = Invitation::find()->where(['id_workspace' => $id])->groupBy(['invitation_token']);
        // $invitationDataProvider = new \portalium\data\ActiveDataProvider([
        //     'query' => $invitationDataProvider,
        // ]);
        $invitationDataProvider = $invitationSearchModel->search(Yii::$app->request->queryParams);
        $invitationDataProvider->query
            ->andWhere([Invitation::tableName() . '.id_workspace' => $id])
            ->andWhere(['exists', InvitationRole::find()->alias('ir')->where('ir.id_invitation = ' . Invitation::tableName() . '.id_invitation')])
            ->with('roles');
        $invitationDataProvider->sort->defaultOrder = ['id_invitation' => SORT_DESC];
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
        if ($draft !== null) {
            $this->applyDraft($draft, $invitationModel, $dynamicModuleModel);
        }
        return $this->render('index', [
            'model' => $this->findWorkspace($id),
            'invitationModel' => $invitationModel,
            'invitationDataProvider' => $invitationDataProvider,
            'moduleArray' => $moduleArray,
            'dynamicModuleModel' => $dynamicModuleModel,
            'availableRoles' => $availableRoles,
            'invitationSearchModel' => $invitationSearchModel,
            'openInvitationModal' => $draft !== null || Yii::$app->request->get('open') === '1',
            'notices' => $notices,
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
        $model = new InvitationForm(['scenario' => InvitationForm::SCENARIO_CREATE]);
        $model->id_workspace = $id;
        if (!$this->request->isPost || !$model->load($this->request->post())) {
            return $this->redirect(['index', 'id' => $id]);
        }
        $model->id_workspace = $id;
        if (!$model->validate()) {
            Yii::$app->session->addFlash('error', implode(' ', $model->getFirstErrors()));
            $this->keepDraft($id, $model);
            return $this->redirect(['index', 'id' => $id]);
        }
        $roles = $this->selectedRoles();
        if ($roles === []) {
            Yii::$app->session->addFlash('error', Module::t('Select a role for at least one module.'));
            $this->keepDraft($id, $model);
            return $this->redirect(['index', 'id' => $id]);
        }

        $audience = Invitation::existingAudience((int) $model->id_workspace, $model->emails);
        $emails = array_values(array_diff($model->emails, $audience['members'], $audience['pending']));
        if ($emails === []) {
            $this->noticeAudience((int) $id, $audience);
            return $this->redirect(['index', 'id' => $id]);
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $created = $this->inviteEmails((int) $model->id_workspace, $emails, $roles, (string) $model->date_expire);
            if ($created === []) {
                $transaction->rollBack();
                Yii::$app->session->addFlash('error', Module::t('Invitation not sent.'));
                return $this->redirect(['index', 'id' => $id]);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->flashSendResult((int) $id, count($created), $this->sendInvitationMails($created));
        $this->noticeAudience((int) $id, $audience);
        return $this->redirect(['index', 'id' => $id]);
    }

    /**
     * Davet açılmayan adresler (karar 13): zaten üye olanlar ve bekleyen daveti olanlar (satırdaki «Daveti yeniden
     * gönder» düğmesiyle yeniden gönderilir). Liste sayfasında kalıcı uyarı olarak gösterilir (son denetim U6).
     *
     * @param array{members: string[], pending: string[]} $audience
     */
    private function noticeAudience(int $id, array $audience): void
    {
        if ($audience['members'] !== []) {
            $this->addNotice($id, Module::t(
                'Already a member of this workspace, not invited: {emails}',
                ['emails' => implode(', ', $audience['members'])]
            ));
        }
        if ($audience['pending'] !== []) {
            $this->addNotice($id, Module::t(
                'Already has a pending invitation, not invited again: {emails}. Resend it with the «{button}» button in its row.',
                ['emails' => implode(', ', $audience['pending']), 'button' => Module::t('Resend invitation')]
            ));
        }
    }

    /**
     * Liste sayfasının üstünde, kullanıcı kapatana kadar duracak uyarı. Birkaç saniyelik bildirimde kaybolmaması
     * gereken, adres listesi içeren sonuçlar için (son denetim U6).
     */
    private function addNotice(int $id, string $text): void
    {
        $key = self::NOTICE_KEY . $id;
        $notices = (array) Yii::$app->session->get($key, []);
        $notices[] = $text;
        Yii::$app->session->set($key, $notices);
    }

    /**
     * Oturumdaki uyarıları bir kez okur ve siler.
     *
     * @return string[]
     */
    private function takeNotices($id): array
    {
        $key = self::NOTICE_KEY . (int) $id;
        $notices = Yii::$app->session->get($key);
        Yii::$app->session->remove($key);

        return is_array($notices) ? array_values(array_filter($notices, 'is_string')) : [];
    }

    /**
     * Doğrulama hatasında yazılanlar kaybolmasın: adresler, tarih, rol seçimleri ve alan hataları
     * bir sonraki liste sayfasında formu yeniden doldurmak için oturumda tutulur.
     */
    private function keepDraft($id, InvitationForm $model): void
    {
        Yii::$app->session->set(self::DRAFT_KEY . (int) $id, [
            'emails' => implode(', ', (array) $model->emails),
            'date_expire' => is_string($model->date_expire) ? $model->date_expire : '',
            'roles' => $this->postedRoles(),
            'errors' => $model->getErrors(),
        ]);
    }

    /**
     * Formdan gelen rol seçimleri (modül => rol), formu yeniden doldurmak için; atanabilirlik denetlenmez.
     *
     * @return array<string,string>
     */
    private function postedRoles(): array
    {
        $roles = [];
        foreach ((array) Yii::$app->request->post('DynamicModel', []) as $module => $role) {
            if (is_string($module) && is_string($role)) {
                $roles[$module] = $role;
            }
        }
        return $roles;
    }

    /**
     * Oturumdaki taslağı bir kez okur ve siler.
     */
    private function takeDraft($id): ?array
    {
        $key = self::DRAFT_KEY . (int) $id;
        $draft = Yii::$app->session->get($key);
        Yii::$app->session->remove($key);
        return is_array($draft) ? $draft : null;
    }

    private function applyDraft(array $draft, InvitationForm $form, DynamicModel $roles): void
    {
        $form->emails = (string) ($draft['emails'] ?? '');
        $form->date_expire = (string) ($draft['date_expire'] ?? '');
        $form->addErrors((array) ($draft['errors'] ?? []));
        foreach ((array) ($draft['roles'] ?? []) as $module => $role) {
            if ($roles->hasAttribute($module)) {
                $roles->$module = $role;
            }
        }
    }

    /**
     * Formda seçilen ve çalışma alanında atanabilir olan roller (modül => rol).
     *
     * @return array<string,string>
     */
    private function selectedRoles(): array
    {
        $roles = [];
        foreach ((array) Yii::$app->request->post('DynamicModel', []) as $module => $role) {
            if (!is_string($role) || $role === '' || $role === 'none' || !Yii::$app->workspace->isAvailableRole($module, $role)) {
                continue;
            }
            $roles[$module] = $role;
        }
        return $roles;
    }

    /**
     * Her adres için kendi bağlantısı olan ayrı bir davet açar (karar 5: bağlantı yalnızca o adres için geçerli).
     *
     * @param string[] $emails
     * @param array<string,string> $roles modül => rol
     * @return Invitation[]
     */
    private function inviteEmails(int $idWorkspace, array $emails, array $roles, string $dateExpire): array
    {
        $pairs = self::rolePairs($roles);
        $created = [];
        foreach ($emails as $email) {
            $invitation = Invitation::createForEmail($idWorkspace, (int) Yii::$app->user->id, $email, $pairs, $dateExpire);
            if ($invitation !== null) {
                $created[] = $invitation;
            }
        }
        return $created;
    }

    /**
     * @param array<string,string> $roles modül => rol
     * @return array<int,array{0:string,1:string}>
     */
    private static function rolePairs(array $roles): array
    {
        $pairs = [];
        foreach ($roles as $module => $role) {
            $pairs[] = [$module, $role];
        }
        return $pairs;
    }

    /**
     * @param Invitation[] $invitations
     * @return string[] e-postası gönderilemeyen adresler
     */
    private function sendInvitationMails(array $invitations): array
    {
        $failed = [];
        foreach ($invitations as $invitation) {
            if (!$invitation->sendMail()) {
                $failed[] = (string) $invitation->inviteeEmail();
            }
        }
        return $failed;
    }

    /**
     * Başarı kısa bildirimle; e-postası gönderilemeyen adresler liste sayfasında kalıcı uyarıyla (son denetim U6).
     *
     * @param string[] $failed e-postası gönderilemeyen adresler
     */
    private function flashSendResult(int $id, int $invited, array $failed): void
    {
        if ($failed === []) {
            Yii::$app->session->addFlash('success', Module::t('Invitation sent to {count} address(es).', ['count' => $invited]));
            return;
        }
        $this->addNotice($id, Module::t(
            'Invitation saved for {count} address(es), but the email could not be sent to: {emails}. Resend it with the «{button}» button in its row.',
            ['count' => $invited, 'emails' => implode(', ', $failed), 'button' => Module::t('Resend invitation')]
        ));
    }

    /**
     * Bir adresin davetini yeniden gönderir. Bağlantısı yoksa üretilir, süresi geçmişse uzatılır.
     * @param int $id Id Invitation (adres)
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionResend($id)
    {
        $model = $this->findModel($id);
        $workspace = $this->findWorkspace($model->id_workspace);
        if (!\Yii::$app->user->can('workspaceWebDefaultResendInvitation', ['id_module' => 'workspace', 'model' => $workspace])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if ($model->addressStatus() === Invitation::STATUS_ACCEPTED) {
            Yii::$app->session->addFlash('info', Module::t('This invitation has already been accepted.'));
        } elseif ($model->sendMail()) {
            Yii::$app->session->addFlash('success', Module::t(
                'Invitation sent. It is valid until {date}.',
                ['date' => substr((string) $model->date_expire, 0, 16)]
            ));
        } else {
            Yii::$app->session->addFlash('error', Module::t('Invitation not sent.'));
        }
        return $this->redirect(['index', 'id' => $model->id_workspace]);
    }

    /**
     * Deletes an existing Invitation model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * Kabul edilmiş davet silinmez (karar 31): tekil silme 422 ile reddedilir, toplu silme onları atlar.
     * @param int $id Id Invitation
     * @param bool $all Aynı bağlantıyı taşıyan (yalnızca bu çalışma alanındaki) bütün davetleri siler
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     * @throws UnprocessableEntityHttpException kabul edilmiş davet
     */
    public function actionDelete($id, $all = false)
    {
        $model = Invitation::findOne($id);
        if (!$model || (!\Yii::$app->user->can('workspaceWebDefaultDeleteInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($model->id_workspace)]))) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
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
        Yii::$app->session->addFlash('success', Module::t('Invitation deleted successfully.'));
        if (Yii::$app->request->isAjax) {
            return $this->asJson(['success' => Module::t('Invitation deleted successfully.')]);
        }
        return $this->redirect(['index', 'id' => $model->id_workspace]);
    }

    /**
     * Davet bağlantısı (karar 5). GET kabul etmez: "«X» çalışma alanına katılmak istiyor musunuz?" sayfasını ya da
     * davetin neden kabul edilemeyeceğini gösterir. [Katıl] POST eder; kabulden sonra o alana geçilir ve ana sayfaya
     * dönülür. Kabul edilemezse sonuç aynı sayfada kalır. Misafir giriş/kayıt düğmelerini görür; giriş bu sayfaya
     * döner (returnUrl), kayıt bağlantısı davetin token'ını taşır.
     *
     * [Katıl] formu sayfada gösterilen rollerin parmak izini (shown_roles) taşır; sayfa açıldıktan sonra roller
     * değiştiyse kabul edilmez, sayfa güncel rollerle ve bunu söyleyen uyarıyla yeniden gösterilir (son denetim D1).
     *
     * E-postadaki bağlantı imza (sig) taşır (karar 35/49). İmza geçerliyse kayıt bağlantısına, hesap değiştirme
     * formuna ve girişten dönüş adresine taşınır; [Kayıt ol] auth::web_signup kapalıyken de gösterilir (karar 40).
     * Geçersiz imza taşınmaz; sayfa imzasız (kopyalanan) bağlantı gibi davranır.
     *
     * @param string $token The invitation token.
     * @param string|null $sig e-postadaki bağlantının imzası
     * @return string|\yii\web\Response
     */
    public function actionAccept($token, $sig = null)
    {
        $token = (string) $token;
        $sig = $this->validSignature($token, $sig);
        $user = Yii::$app->user->isGuest ? null : Yii::$app->user->identity;
        $acceptance = new InvitationAcceptance();
        $changedNotice = null;
        if ($user !== null && $this->request->isPost) {
            $shown = $this->request->post('shown_roles');
            $outcome = $acceptance->accept($token, $user, true, is_string($shown) ? $shown : null);
            if (InvitationAcceptance::isAccepted($outcome['result'])) {
                Yii::$app->session->addFlash(InvitationAcceptance::flashType($outcome['result']), $acceptance->message($outcome, $user));
                return $this->redirect(['/']);
            }
            $fresh = $acceptance->inspect($token, $user);
            if ($outcome['result'] === InvitationAcceptance::CHANGED) {
                $changedNotice = $fresh['result'] === InvitationAcceptance::READY ? $acceptance->message($outcome, $user) : null;
                $outcome = $fresh;
            } else {
                $outcome['invitation'] = $fresh['invitation'];
                $outcome['fingerprint'] = $fresh['fingerprint'];
            }
        } else {
            $outcome = $acceptance->inspect($token, $user);
        }

        if ($user === null) {
            Yii::$app->user->setReturnUrl(Url::to(self::acceptRoute($token, $sig)));
            $this->layout = '/login';
        }
        if ($outcome['invitation'] !== null && $outcome['invitation']->workspace === null) {
            $outcome['result'] = InvitationAcceptance::NOT_FOUND;
            $outcome['invitation'] = null;
        }
        if ($outcome['result'] === InvitationAcceptance::NOT_FOUND) {
            Yii::$app->response->statusCode = 404;
        }
        $invitation = $outcome['invitation'];

        $canSignup = $sig !== '' || (bool) Yii::$app->setting->getValue('auth::web_signup');

        return $this->render('accept', [
            'token' => $token,
            'sig' => $sig,
            'outcome' => $outcome,
            'user' => $user,
            'message' => $outcome['result'] === InvitationAcceptance::READY ? null : $acceptance->message($outcome, $user),
            'changedNotice' => $changedNotice,
            'fingerprint' => (string) ($outcome['fingerprint'] ?? ''),
            'invitation' => $invitation,
            'workspace' => $invitation === null ? null : $invitation->workspace,
            'roles' => $invitation === null ? [] : $this->pendingRoleLabels($invitation),
            'canSignup' => $canSignup,
            'signupHint' => ($user === null && $canSignup && $outcome['result'] === InvitationAcceptance::READY)
                ? InvitationSignup::describe($token)
                : null,
        ]);
    }

    /**
     * Yanlış hesapla açılan davet için: çıkış yapar ve aynı davet sayfasına döner (son denetim U2). Misafir hâlinde
     * sayfada [Giriş yap] (girişten sonra davete döner) ve davetin token'ını taşıyan [Kayıt ol] vardır; eskiden giriş
     * sayfasına gidiliyordu ve oradaki kayıt bağlantısı token'ı kaybediyordu.
     *
     * E-postadaki imzalı bağlantıdan gelindiyse imza dönüş adresinde korunur (karar 35/49); yoksa misafir kaydı hemen
     * etkin olmazdı.
     *
     * @param string $token The invitation token.
     * @param string|null $sig e-postadaki bağlantının imzası
     * @return \yii\web\Response
     */
    public function actionSwitchAccount($token, $sig = null)
    {
        $returnUrl = Url::to(self::acceptRoute((string) $token, $this->validSignature((string) $token, $sig)));
        Yii::$app->user->logout();
        Yii::$app->user->setReturnUrl($returnUrl);

        return $this->redirect($returnUrl);
    }

    /**
     * İmza bu davet için geçerliyse kendisi, değilse '' (taşınmaz).
     *
     * @param mixed $sig
     */
    private function validSignature(string $token, $sig): string
    {
        if (!is_string($sig) || $sig === '') {
            return '';
        }

        return InvitationSignup::signedAddress($token, $sig) === null ? '' : $sig;
    }

    /**
     * Davet sayfasının rotası; imza varsa onu da taşır.
     */
    public static function acceptRoute(string $token, string $sig): array
    {
        $route = ['/workspace/invitation/accept', 'token' => $token];
        if ($sig !== '') {
            $route[InvitationSignup::SIGNATURE_PARAM] = $sig;
        }

        return $route;
    }

    /**
     * Kabul sayfasında gösterilecek, henüz kabul edilmemiş roller ("Modül: rol").
     *
     * @return string[]
     */
    private function pendingRoleLabels(Invitation $invitation): array
    {
        $labels = [];
        foreach ($invitation->roles as $role) {
            if ((int) $role->status === InvitationRole::STATUS_ACCEPTED) {
                continue;
            }
            $module = Yii::$app->getModule((string) $role->module);
            $name = ($module !== null && isset($module::$name)) ? $module::$name : (string) $role->module;
            $labels[] = $name . ': ' . $role->role;
        }

        return $labels;
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
        $model = $this->findModel($id);
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
     * Updates an existing Invitation model (karar 29): bekleyen davetin rolleri ve son tarihi düzenlenir; kaydetmek
     * e-posta göndermez, bağlantı aynı kalır. Yeni adres buradan eklenmez (davet penceresinden gönderilir). Kabul
     * edilmiş davet salt okunurdur.
     * @param int $id_invitation Id Invitation
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        $modelInvitation = $this->findModel($id);
        if (!\Yii::$app->user->can('workspaceWebDefaultCreateInvitation', ['id_module' => 'workspace', 'model' => $this->findWorkspace($modelInvitation->id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = new InvitationForm();
        $model->id_workspace = $modelInvitation->id_workspace;
        if ($this->request->isPost) {
            if ($modelInvitation->isAccepted()) {
                Yii::$app->session->addFlash('error', Module::t(
                    'This invitation has been accepted; its roles cannot be changed. To change the member\'s roles, use the Members and roles page.'
                ));
                return $this->redirect(['update', 'id' => $modelInvitation->id_invitation]);
            }
            $model->load($this->request->post());
            $model->emails = null;
            $model->id_workspace = $modelInvitation->id_workspace;
            return $this->saveUpdate($model, $modelInvitation);
        }
        return $this->renderUpdate($model, $modelInvitation, $this->currentRoles($modelInvitation));
    }

    /**
     * Davetin modül başına (ilk) rolü; güncelleme formunda seçili gelir.
     *
     * @return array<string,string>
     */
    private function currentRoles(Invitation $invitation): array
    {
        $roles = [];
        foreach ($invitation->roles as $role) {
            $roles[(string) $role->module] ??= (string) $role->role;
        }

        return $roles;
    }

    /**
     * Güncelleme sayfası. Hata olursa yazılanlar (tarih, rol seçimleri) ve hatalarla yeniden gösterilir.
     *
     * @param array<string,string> $selectedRoles modül => rol
     */
    private function renderUpdate(InvitationForm $model, Invitation $modelInvitation, array $selectedRoles = [], ?string $roleError = null): string
    {
        $dynamicModuleModel = new DynamicModel();
        $availableRoles = (array) Yii::$app->setting->getValue('workspace::available_roles');
        foreach ($availableRoles as $key => $value) {
            $dynamicModuleModel->defineAttribute($key);
            $dynamicModuleModel->addRule($key, 'in', ['range' => $value]);
        }
        foreach ($selectedRoles as $module => $role) {
            if ($dynamicModuleModel->hasAttribute($module)) {
                $dynamicModuleModel->$module = $role;
            }
        }
        $acceptedModules = [];
        foreach ($modelInvitation->roles as $role) {
            if ((int) $role->status === InvitationRole::STATUS_ACCEPTED) {
                $acceptedModules[(string) $role->module] = (string) $role->role;
            }
        }

        return $this->render('update', [
            'model' => $model,
            'dynamicModuleModel' => $dynamicModuleModel,
            'availableRoles' => $availableRoles,
            'modelInvitation' => $modelInvitation,
            'acceptedModules' => $acceptedModules,
            'roleError' => $roleError,
        ]);
    }

    /**
     * Güncellemeyi kaydeder: son tarih (boşsa korunur) ve roller (Invitation::updateRoles). E-posta gitmez.
     *
     * @return string|\yii\web\Response
     */
    private function saveUpdate(InvitationForm $model, Invitation $modelInvitation)
    {
        $posted = $this->postedRoles();
        if (!$model->validate()) {
            return $this->renderUpdate($model, $modelInvitation, $posted);
        }
        if ($model->date_expire !== null && $model->date_expire !== '') {
            $modelInvitation->date_expire = $model->date_expire;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$modelInvitation->updateRoles($this->selectedRoles())) {
                $transaction->rollBack();
                return $this->renderUpdate($model, $modelInvitation, $posted, Module::t('Select a role for at least one module.'));
            }
            if ($modelInvitation->getDirtyAttributes(['date_expire']) !== []) {
                $modelInvitation->save(false, ['date_expire']);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->session->addFlash('success', Module::t('Invitation updated. No email was sent; the invitation link stays the same.'));
        return $this->redirect(['index', 'id' => $modelInvitation->id_workspace]);
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
