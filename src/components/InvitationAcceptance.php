<?php

namespace portalium\workspace\components;

use portalium\user\models\User;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationRole;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use Yii;

/**
 * Davet bağlantısının kabulü. Web ve API aynı kuralı kullanır; sonuç, kullanıcıya nedeni
 * ayrı ayrı söyleyebilmek için bir kodla döner.
 *
 * Her adresin kendi bağlantısı vardır ve bağlantı yalnızca o adrese ait hesapla kabul edilir.
 * Eski biçimde birden çok adrese ortak gönderilmiş bağlantı, açanın hangi adresin sahibi olduğunu
 * kanıtlamadığı için reddedilir. Web'de kabulden sonra kullanıcı o çalışma alanına geçer; API'de geçiş
 * yapılmaz (karar 12).
 *
 * inspect() kabul etmeden aynı denetimleri yapar: web'de bağlantı önce onay sayfası açar, kabul yalnız
 * POST ile yapılır (karar 5).
 *
 * Kabul tek transaction'da ve davet satırı kilitlenerek (FOR UPDATE) yapılır: [Katıl]'a çift tıklamadaki iki istekten
 * ikincisi ilki bitene kadar bekler ve daveti kabul edilmiş görür (son denetim O2). Sayfa, gösterdiği bekleyen rollerin
 * parmak izini taşır; kabul anında roller değişmişse kabul edilmez (CHANGED, son denetim D1). Rol satırının "kabul
 * edildi" işareti tam bir satırı etkilemezse (satır silinmiş ya da başka istek işaretlemiş) o rol kabul sayılmaz.
 */
class InvitationAcceptance
{
    public const READY = 'ready';
    public const NOT_FOUND = 'not_found';
    public const SHARED_LINK = 'shared_link';
    public const WRONG_ACCOUNT = 'wrong_account';
    public const ALREADY_ACCEPTED = 'already_accepted';
    public const EXPIRED = 'expired';
    public const ROLE_UNAVAILABLE = 'role_unavailable';
    public const FAILED = 'failed';
    public const PARTIAL = 'partial';
    public const ACCEPTED = 'accepted';
    public const CHANGED = 'changed';

    /**
     * Daveti kabul etmeden durumunu söyler (onay sayfası için). Misafirde ($user null) hesap eşleşmesi
     * denetlenmez. Sonuç READY ise davet bu hesapla kabul edilebilir; `invitation` sayfada gösterilecek davettir.
     *
     * `fingerprint` sayfada gösterilen bekleyen rollerin parmak izidir; [Katıl] formu bunu accept()'e geri taşır.
     *
     * @return array{result:string, id_workspace:?int, accepted:int, date_expire:?string, switched:bool, invitation:?Invitation, fingerprint:string}
     */
    public function inspect(string $token, ?User $user): array
    {
        $check = $this->check($token, $user);
        $outcome = $check['outcome'];
        if ($outcome === null) {
            $available = array_filter(
                $check['pending'],
                static fn (InvitationRole $role) => Yii::$app->workspace->isAvailableRole($role->module, $role->role)
            );
            $outcome = $this->outcome($available === [] ? self::ROLE_UNAVAILABLE : self::READY, (int) $check['invitation']->id_workspace);
        }
        $outcome['invitation'] = $check['invitation'];
        $outcome['fingerprint'] = self::fingerprint($check['pending']);

        return $outcome;
    }

    /**
     * @param bool $switch kabulden sonra o çalışma alanına geçilsin mi (web: evet; API: hayır, karar 12)
     * @param string|null $expected sayfada gösterilen rollerin parmak izi (inspect()); null ise denetlenmez (API)
     * @return array{result:string, id_workspace:?int, accepted:int, date_expire:?string, switched:bool}
     */
    public function accept(string $token, User $user, bool $switch = true, ?string $expected = null): array
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->lockInvitation($token);
            $check = $this->check($token, $user);
            if ($check['outcome'] !== null) {
                $transaction->commit();
                return $check['outcome'];
            }
            $idWorkspace = (int) $check['invitation']->id_workspace;
            if ($expected !== null && !hash_equals(self::fingerprint($check['pending']), $expected)) {
                $transaction->commit();
                return $this->outcome(self::CHANGED, $idWorkspace);
            }
            $status = $switch || !$this->hasActiveWorkspaceOtherThan($user, $idWorkspace)
                ? WorkspaceUser::STATUS_ACTIVE
                : WorkspaceUser::STATUS_INACTIVE;
            $outcome = $this->acceptRoles($check['pending'], $user, $idWorkspace, $status);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        if ($switch && self::isAccepted($outcome['result'])) {
            $outcome['switched'] = $this->switchTo($user, $idWorkspace);
        }

        return $outcome;
    }

    /**
     * Bekleyen rol satırlarının parmak izi: kimlik, modül ve rol. Sahibi rolü yerinde değiştirse de (aynı satır
     * kimliği) parmak izi değişir.
     *
     * @param InvitationRole[] $pending
     */
    public static function fingerprint(array $pending): string
    {
        $rows = array_map(
            static fn (InvitationRole $role) => [(int) $role->id_invitation_role, (string) $role->module, (string) $role->role],
            $pending
        );
        sort($rows);

        return hash('sha256', (string) json_encode($rows));
    }

    /**
     * Davet satırını transaction sonuna kadar kilitler; aynı davetin eşzamanlı ikinci kabulü burada bekler.
     */
    private function lockInvitation(string $token): void
    {
        if ($token === '') {
            return;
        }
        $db = Yii::$app->db;
        $db->createCommand(
            'SELECT ' . $db->quoteColumnName('id_invitation') . ' FROM ' . $db->quoteTableName(Invitation::tableName())
            . ' WHERE ' . $db->quoteColumnName('invitation_token') . ' = :token FOR UPDATE',
            [':token' => $token]
        )->queryColumn();
    }

    /**
     * Kabulden önceki denetimler. `outcome` doluysa davet kabul edilemez; boşsa `pending` kabul edilecek
     * (henüz kabul edilmemiş) rol satırlarıdır.
     *
     * @return array{outcome:?array, invitation:?Invitation, pending:InvitationRole[]}
     */
    private function check(string $token, ?User $user): array
    {
        $result = ['outcome' => null, 'invitation' => null, 'pending' => []];
        $invitation = $token === '' ? null : Invitation::findOne(['invitation_token' => $token]);
        if ($invitation === null) {
            $result['outcome'] = $this->outcome(self::NOT_FOUND);
            return $result;
        }
        $idWorkspace = (int) $invitation->id_workspace;
        $roles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation])->all();
        if ($roles === []) {
            $result['outcome'] = $this->outcome(self::NOT_FOUND);
            return $result;
        }
        $result['invitation'] = $invitation;

        $addresses = array_values(array_unique(array_map(static fn (InvitationRole $role) => self::normalize($role->email), $roles)));
        if (count($addresses) > 1) {
            $result['outcome'] = $this->outcome(self::SHARED_LINK, $idWorkspace);
        } elseif ($addresses[0] === '') {
            $result['invitation'] = null;
            $result['outcome'] = $this->outcome(self::NOT_FOUND);
        } elseif ($user !== null && $addresses[0] !== self::normalize($user->email)) {
            $result['outcome'] = $this->outcome(self::WRONG_ACCOUNT, $idWorkspace);
        }
        if ($result['outcome'] !== null) {
            return $result;
        }

        $result['pending'] = array_values(array_filter($roles, static fn (InvitationRole $role) => (int) $role->status !== InvitationRole::STATUS_ACCEPTED));
        if ($result['pending'] === []) {
            $result['outcome'] = $this->outcome(self::ALREADY_ACCEPTED, $idWorkspace);
        } elseif (strtotime((string) $invitation->date_expire) <= time()) {
            $result['outcome'] = $this->outcome(self::EXPIRED, $idWorkspace, 0, (string) $invitation->date_expire);
        }

        return $result;
    }

    private function hasActiveWorkspaceOtherThan(User $user, int $idWorkspace): bool
    {
        return WorkspaceUser::findNoGroupBy()
            ->where(['id_user' => $user->id_user, 'status' => WorkspaceUser::STATUS_ACTIVE])
            ->andWhere(['<>', 'id_workspace', $idWorkspace])
            ->exists();
    }

    /**
     * Kabul edilen çalışma alanını kullanıcının etkin çalışma alanı yapar; oturum kullanan istekte
     * (web) oturumdaki seçim de güncellenir.
     */
    private function switchTo(User $user, int $idWorkspace): bool
    {
        if (!WorkspaceUser::activateWorkspace((int) $user->id_user, $idWorkspace)) {
            return false;
        }
        $webUser = Yii::$app->has('user') ? Yii::$app->user : null;
        if ($webUser !== null && $webUser->enableSession && !$webUser->isGuest && (int) $webUser->id === (int) $user->id_user) {
            Yii::$app->session->set('active_workspace_id', $idWorkspace);
        }

        return true;
    }

    /**
     * @param InvitationRole[] $pending
     * @param int $status kabul edilen üyelik satırlarının durumu (etkin ya da değil)
     */
    private function acceptRoles(array $pending, User $user, int $idWorkspace, int $status): array
    {
        $accepted = 0;
        $unavailable = 0;
        $failed = 0;
        foreach ($pending as $role) {
            if (!Yii::$app->workspace->isAvailableRole($role->module, $role->role)) {
                $unavailable++;
                continue;
            }
            if ($this->acceptRole($role, $user, $status)) {
                $accepted++;
            } else {
                $failed++;
            }
        }

        if ($accepted === 0) {
            return $this->outcome($failed > 0 ? self::FAILED : self::ROLE_UNAVAILABLE, $idWorkspace);
        }
        $result = ($unavailable + $failed) > 0 ? self::PARTIAL : self::ACCEPTED;

        return $this->outcome($result, $idWorkspace, $accepted);
    }

    /**
     * Davet satırı kabul edilmiş işaretlenir (tam bir satır etkilenmeli), üyelik satırı yoksa açılır, varsa durumu
     * verilir. Biri olmazsa bu rolün yazdıkları geri alınır.
     */
    private function acceptRole(InvitationRole $role, User $user, int $status): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $member = WorkspaceUser::findOne([
                'id_workspace' => $role->id_workspace,
                'id_user' => $user->id_user,
                'id_module' => $role->module,
                'role' => $role->role,
            ]);
            if ($member === null) {
                $member = new WorkspaceUser();
                $member->id_workspace = $role->id_workspace;
                $member->id_user = $user->id_user;
                $member->id_module = $role->module;
                $member->role = $role->role;
            }
            $member->status = $status;
            $this->beforeWrite($role, $member);
            if (!$this->markAccepted($role) || !$member->save()) {
                $transaction->rollBack();
                Yii::error(['invitation_role' => $role->id_invitation_role, 'errors' => $member->getErrors()], __METHOD__);
                return false;
            }
            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Rol satırını yalnız hâlâ bekliyorsa kabul edilmiş yapar; tam bir satır etkilenmezse false.
     */
    private function markAccepted(InvitationRole $role): bool
    {
        $changed = InvitationRole::updateAll(
            ['status' => InvitationRole::STATUS_ACCEPTED],
            [
                'and',
                ['id_invitation_role' => $role->id_invitation_role],
                ['or', ['status' => null], ['<>', 'status', InvitationRole::STATUS_ACCEPTED]],
            ]
        );
        if ($changed !== 1) {
            return false;
        }
        $role->status = InvitationRole::STATUS_ACCEPTED;

        return true;
    }

    /**
     * Rol satırı ve üyelik yazılmadan hemen önce çağrılır; hiçbir şey yapmaz. Eşzamanlı iki kabulü sırayla
     * taklit eden test (InvitationAcceptRaceTest) iki süreci burada buluşturur.
     */
    protected function beforeWrite(InvitationRole $role, WorkspaceUser $member): void
    {
    }

    /**
     * Kullanıcıya gösterilecek, nedene özgü mesaj: ne oldu ve ne yapmalı. Misafirde ($user null) yanlış hesap
     * sonucu oluşmaz.
     */
    public function message(array $outcome, ?User $user): string
    {
        switch ($outcome['result']) {
            case self::ACCEPTED:
                $workspace = $this->workspaceOf($outcome);
                if ($workspace === null) {
                    return Module::t('Invitation accepted successfully.');
                }
                if (!empty($outcome['switched'])) {
                    return Module::t('You joined the «{workspace}» workspace and switched to it.', ['workspace' => $workspace->displayTitle()]);
                }
                return Module::t(
                    'You joined the «{workspace}» workspace. To work in it, select it from the workspace menu.',
                    ['workspace' => $workspace->displayTitle()]
                );
            case self::PARTIAL:
                $text = Module::t('Invitation accepted, but some roles are no longer available and were not added.');
                $workspace = $this->workspaceOf($outcome);
                if ($workspace !== null && !empty($outcome['switched'])) {
                    $text .= ' ' . Module::t('You switched to the «{workspace}» workspace.', ['workspace' => $workspace->displayTitle()]);
                }
                return $text;
            case self::SHARED_LINK:
                return Module::t(
                    'This invitation link was sent to several addresses and is no longer valid. '
                    . 'Ask the person who invited you to send the invitation again.'
                );
            case self::ALREADY_ACCEPTED:
                return Module::t('This invitation has already been accepted.');
            case self::EXPIRED:
                return Module::t('This invitation expired on {date}. Ask the person who invited you for a new invitation.', [
                    'date' => substr((string) $outcome['date_expire'], 0, 16),
                ]);
            case self::WRONG_ACCOUNT:
                return Module::t(
                    'This invitation was sent to a different email address. '
                    . 'Sign in with the account of the address that received it (you are signed in as {email}).',
                    ['email' => $user === null ? '' : $user->email]
                );
            case self::ROLE_UNAVAILABLE:
                return Module::t('The roles in this invitation are no longer available. Ask the person who invited you for a new invitation.');
            case self::FAILED:
                return Module::t('The invitation could not be accepted. Please try again.');
            case self::CHANGED:
                return Module::t('The invitation was changed after you opened this page. Check the current roles below and click «Join» again.');
            default:
                return Module::t('Invitation not found. The link may be wrong or the invitation may have been deleted.');
        }
    }

    /**
     * Web'de gösterilecek flash türü.
     */
    public static function flashType(string $result): string
    {
        switch ($result) {
            case self::ACCEPTED:
                return 'success';
            case self::PARTIAL:
                return 'warning';
            case self::ALREADY_ACCEPTED:
                return 'info';
            default:
                return 'error';
        }
    }

    public static function isAccepted(string $result): bool
    {
        return $result === self::ACCEPTED || $result === self::PARTIAL;
    }

    private static function normalize(?string $email): string
    {
        return Invitation::normalizeEmail($email);
    }

    private function workspaceOf(array $outcome): ?Workspace
    {
        return $outcome['id_workspace'] === null ? null : Workspace::findOne($outcome['id_workspace']);
    }

    private function outcome(string $result, ?int $idWorkspace = null, int $accepted = 0, ?string $dateExpire = null): array
    {
        return [
            'result' => $result,
            'id_workspace' => $idWorkspace,
            'accepted' => $accepted,
            'date_expire' => $dateExpire,
            'switched' => false,
        ];
    }
}
