<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use Yii;
use portalium\workspace\Module;

/**
 * This is the model class for table "workspace_invitation".
 *
 * Her satır tek bir adrese aittir ve o adresin kendi bağlantısı (token) vardır; adresin rolleri
 * workspace_invitation_role satırlarındadır. Boş token, ortak bağlantılı eski bir davetin adres adres
 * ayrıldığını ve yeniden gönderilene kadar bağlantısı olmadığını gösterir.
 *
 * signup_secret yalnız e-postadaki bağlantıları imzalamak içindir (karar 35/49, bkz. InvitationSignup); her e-posta
 * gönderiminde yenilenir, hiçbir yanıtta (fields) ve ekranda gösterilmez, toplu atanamaz (rules'ta yok).
 *
 * @property int $id_invitation
 * @property int $id_workspace
 * @property string $invitation_token
 * @property string|null $signup_secret
 * @property int $id_user
 * @property string $date_create
 * @property string $date_expire
 * @property InvitationRole[] $roles
 */
class Invitation extends \yii\db\ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';

    /** E-postadaki bağlantıları imzalayan sırrın kolonu (m261019_000010). */
    public const SIGNUP_SECRET = 'signup_secret';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return Module::$tablePrefix . 'invitation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_workspace', 'invitation_token'], 'required'],
            [['id_workspace', 'id_user'], 'integer'],
            [['date_create', 'date_expire'], 'safe'],
            [['invitation_token'], 'string', 'max' => 255],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['id_user' => 'id_user']]
        ];
    }

    public function extraFields()
    {
        return ['invitationRole'];
    }

    public function getInvitationRole()
    {
        return $this->hasMany(InvitationRole::class, ['id_invitation' => 'id_invitation']);
    }

    /**
     * REST yanıtı: imza sırrı hiçbir yanıtta yer almaz (karar 49).
     */
    public function fields()
    {
        $fields = parent::fields();
        unset($fields[self::SIGNUP_SECRET]);

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_invitation' => Module::t('Id Invitation'),
            'id_workspace' => Module::t('Id Workspace'),
            'invitation_token' => Module::t('Invitation Token'),
            'id_user' => Module::t('Id User'),
            'date_create' => Module::t('Date Create'),
            'date_expire' => Module::t('Date Expire'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkspace()
    {
        return $this->hasOne(Workspace::class, ['id_workspace' => 'id_workspace']);
    }

    /**
     * {@inheritdoc}
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id_user' => 'id_user']);
    }

    public function getRoles()
    {
        return $this->hasMany(InvitationRole::class, ['id_invitation' => 'id_invitation'])
            ->orderBy(['id_invitation_role' => SORT_ASC]);
    }

    /**
     * Bir adres için kendi bağlantısı olan davet ve rol satırlarını açar. Rol satırı açılamazsa davet
     * satırı da bırakılmaz.
     *
     * @param array<int,array{0:string,1:string}> $roles [modül, rol] çiftleri
     */
    public static function createForEmail(int $idWorkspace, ?int $idInviter, string $email, array $roles, string $dateExpire): ?self
    {
        $invitation = new self();
        $invitation->id_workspace = $idWorkspace;
        $invitation->id_user = $idInviter;
        $invitation->date_create = date('Y-m-d H:i:s');
        $invitation->date_expire = $dateExpire;
        $invitation->invitation_token = Yii::$app->security->generateRandomString();
        $invitation->renewSignupSecret();
        if (!$invitation->save()) {
            Yii::error(['email' => $email, 'errors' => $invitation->getErrors()], __METHOD__);
            return null;
        }
        if ($invitation->addRoles($email, $roles) === []) {
            $invitation->delete();
            return null;
        }

        return $invitation;
    }

    /**
     * Davete rol ekler. Bağlantı tek adrese ait olduğu için başka bir adres eklenmez; davette zaten
     * bulunan modüller atlanır.
     *
     * @param array<int,array{0:string,1:string}> $roles [modül, rol] çiftleri
     * @return InvitationRole[] eklenen satırlar
     */
    public function addRoles(string $email, array $roles): array
    {
        $email = self::normalizeEmail($email);
        $existing = InvitationRole::find()->where(['id_invitation' => $this->id_invitation])->all();
        foreach ($existing as $row) {
            if (self::normalizeEmail($row->email) !== $email) {
                return [];
            }
        }
        $skipModules = array_flip(array_map(static fn (InvitationRole $row) => (string) $row->module, $existing));

        $added = [];
        $seen = [];
        foreach ($roles as [$module, $role]) {
            $key = $module . "\0" . $role;
            if (isset($skipModules[$module]) || isset($seen[$key])) {
                continue;
            }
            $row = new InvitationRole();
            $row->id_workspace = $this->id_workspace;
            $row->id_invitation = $this->id_invitation;
            $row->email = $email;
            $row->module = $module;
            $row->role = $role;
            $row->status = InvitationRole::STATUS_PENDING;
            if (!$row->save()) {
                Yii::error(['email' => $email, 'module' => $module, 'errors' => $row->getErrors()], __METHOD__);
                continue;
            }
            $seen[$key] = true;
            $added[] = $row;
        }
        unset($this->roles);

        return $added;
    }

    /**
     * Davetin gönderildiği adres. Bazı veritabanlarında bu tabloda eski bir `email` kolonu kaldığı için
     * adres rol satırlarından okunur.
     */
    public function inviteeEmail(): ?string
    {
        $roles = $this->roles;
        return $roles === [] ? null : (string) $roles[0]->email;
    }

    /**
     * Rollerin hepsi kabul edildiyse kabul edildi; değilse son tarihe göre bekliyor ya da süresi doldu.
     */
    public function addressStatus(): string
    {
        $roles = $this->roles;
        $pending = array_filter($roles, static fn (InvitationRole $role) => (int) $role->status !== InvitationRole::STATUS_ACCEPTED);
        if ($roles !== [] && $pending === []) {
            return self::STATUS_ACCEPTED;
        }
        return $this->isExpired() ? self::STATUS_EXPIRED : self::STATUS_PENDING;
    }

    /**
     * @return array<string,string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => Module::t('Pending'),
            self::STATUS_ACCEPTED => Module::t('Accepted'),
            self::STATUS_EXPIRED => Module::t('Expired'),
        ];
    }

    public function isExpired(): bool
    {
        return strtotime((string) $this->date_expire) <= time();
    }

    /**
     * Rollerin hepsi kabul edildi. Kabul edilmiş davet salt okunurdur ve silinmez (kararlar 29, 31).
     */
    public function isAccepted(): bool
    {
        return $this->addressStatus() === self::STATUS_ACCEPTED;
    }

    public function hasLink(): bool
    {
        return (string) $this->invitation_token !== '';
    }

    /**
     * Bağlantısı boş, kabul edilmemiş ve süresi geçmemiş davet. Yeni davetler her zaman bağlantıyla açılır; boş
     * bağlantı yalnızca m261018_000040 göçünün ortak bağlantıyı boşalttığı davetlerde olur. Yeniden gönderim
     * (prepareForSending) yeni bağlantı üretir (karar 11).
     */
    public function needsNewLink(): bool
    {
        return !$this->hasLink() && $this->addressStatus() === self::STATUS_PENDING;
    }

    /**
     * Bekleyen davetin rollerini seçilenlere göre düzenler (karar 29): seçilen modülde bekleyen satır varsa rolü
     * değişir (aynı modülde birden çok bekleyen satır varsa ilki kalır), yoksa bekleyen satır eklenir; seçilmeyen
     * modüllerin bekleyen satırları silinir. Kabul edilmiş rolü olan modüle dokunulmaz. Bağlantı ve son tarih değişmez,
     * e-posta gönderilmez. Kabul edilmiş davette ya da sonunda hiç rol kalmayacaksa hiçbir şey değiştirmeden
     * false döner.
     *
     * @param array<string,string> $roles modül => rol (atanabilirliği çağıran denetler)
     */
    public function updateRoles(array $roles): bool
    {
        if ($this->isAccepted()) {
            return false;
        }
        $email = self::normalizeEmail($this->inviteeEmail());
        $rows = InvitationRole::find()->where(['id_invitation' => $this->id_invitation])->orderBy(['id_invitation_role' => SORT_ASC])->all();
        $acceptedModules = [];
        foreach ($rows as $row) {
            if ((int) $row->status === InvitationRole::STATUS_ACCEPTED) {
                $acceptedModules[(string) $row->module] = true;
            }
        }
        $wanted = array_diff_key(array_filter($roles, static fn ($role) => is_string($role) && $role !== ''), $acceptedModules);
        if ($email === '' || ($wanted === [] && $acceptedModules === [])) {
            return false;
        }

        $transaction = self::getDb()->beginTransaction();
        try {
            $kept = [];
            foreach ($rows as $row) {
                $module = (string) $row->module;
                if (isset($acceptedModules[$module])) {
                    continue;
                }
                if (!isset($wanted[$module]) || isset($kept[$module])) {
                    $row->delete();
                    continue;
                }
                $kept[$module] = true;
                if ($row->role !== $wanted[$module] && !$this->saveRole($row, $wanted[$module])) {
                    $transaction->rollBack();
                    return false;
                }
            }
            foreach ($wanted as $module => $role) {
                if (isset($kept[$module])) {
                    continue;
                }
                $row = new InvitationRole();
                $row->id_workspace = $this->id_workspace;
                $row->id_invitation = $this->id_invitation;
                $row->email = $email;
                $row->module = (string) $module;
                $row->status = InvitationRole::STATUS_PENDING;
                if (!$this->saveRole($row, $role)) {
                    $transaction->rollBack();
                    return false;
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        unset($this->roles);

        return true;
    }

    private function saveRole(InvitationRole $row, string $role): bool
    {
        $row->role = $role;
        if ($row->save()) {
            return true;
        }
        Yii::error(['id_invitation' => $this->id_invitation, 'module' => $row->module, 'errors' => $row->getErrors()], __METHOD__);

        return false;
    }

    /**
     * Göndermeden önce: bağlantısı yoksa yeni token üretir, süresi geçmişse süreyi uzatır ve imza sırrını yeniler
     * (karar 49). Her gönderim yeni sır alır; yalnız son e-postadaki bağlantılar imzalı sayılır, önceki e-postaların
     * imzası geçersizleşir (o bağlantılar normal onaylı akışa düşer). Token (kopyalanan bağlantı) değişmez.
     */
    public function prepareForSending(): bool
    {
        $changed = [];
        if (!$this->hasLink()) {
            $this->invitation_token = Yii::$app->security->generateRandomString();
            $changed[] = 'invitation_token';
        }
        if ($this->renewSignupSecret()) {
            $changed[] = self::SIGNUP_SECRET;
        }
        if ($this->isExpired()) {
            $this->date_expire = date('Y-m-d H:i:s', strtotime(InvitationForm::DEFAULT_EXPIRY));
            $changed[] = 'date_expire';
        }

        return $changed === [] || $this->save(false, $changed);
    }

    /**
     * E-postadaki bağlantıların imza sırrı; kolon yoksa (migration çalışmamış) ya da davet göçten önceyse null.
     */
    public function signupSecret(): ?string
    {
        if (!$this->hasAttribute(self::SIGNUP_SECRET)) {
            return null;
        }
        $secret = (string) $this->getAttribute(self::SIGNUP_SECRET);

        return $secret === '' ? null : $secret;
    }

    /**
     * Yeni rastgele imza sırrı atar (kaydetmez). Kolon yoksa hiçbir şey yapmaz ve false döner; o kurulumda
     * bağlantılar imzasız gider ve davetle kayıt normal onaylı akışa düşer.
     */
    public function renewSignupSecret(): bool
    {
        if (!$this->hasAttribute(self::SIGNUP_SECRET)) {
            return false;
        }
        $this->setAttribute(self::SIGNUP_SECRET, Yii::$app->security->generateRandomString());

        return true;
    }

    /**
     * Adresin e-postasını (tek e-posta) gönderir.
     */
    public function sendMail(): bool
    {
        $role = InvitationRole::find()->where(['id_invitation' => $this->id_invitation])->orderBy(['id_invitation_role' => SORT_ASC])->one();
        if ($role === null) {
            return false;
        }
        $role->populateRelation('invitation', $this);

        return $role->sendInvitation();
    }

    /**
     * E-postada gösterilecek davet eden: ad soyad, yoksa kullanıcı adı.
     */
    public function inviterName(): ?string
    {
        $user = $this->user;
        if ($user === null) {
            return null;
        }
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        return $fullName !== '' ? $fullName : (string) $user->username;
    }

    /**
     * Davet edilecek adreslerden bu çalışma alanının üyesi olanlar ve bekleyen (kabul edilmemiş, süresi geçmemiş)
     * daveti olanlar. Bu adreslere davet açılmaz (karar 13): üyeye davet gitmez, bekleyen davet ✈ ile yeniden
     * gönderilir. Bağlantısı boşalmış bekleyen davet de bekleyen sayılır. Üye olan adres "bekleyen" listesinde
     * tekrar edilmez.
     *
     * @param string[] $emails
     * @return array{members: string[], pending: string[]}
     */
    public static function existingAudience(int $idWorkspace, array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map([self::class, 'normalizeEmail'], $emails))));
        if ($emails === []) {
            return ['members' => [], 'pending' => []];
        }
        $memberEmails = User::find()
            ->select('email')
            ->where(['email' => $emails])
            ->andWhere(['id_user' => WorkspaceUser::findNoGroupBy()->select('id_user')->where(['id_workspace' => $idWorkspace])])
            ->column();
        $pendingEmails = InvitationRole::find()->alias('r')
            ->select('r.email')
            ->distinct()
            ->innerJoin(['i' => self::tableName()], 'i.id_invitation = r.id_invitation')
            ->where(['i.id_workspace' => $idWorkspace, 'r.email' => $emails])
            ->andWhere(['or', ['r.status' => null], ['<>', 'r.status', InvitationRole::STATUS_ACCEPTED]])
            ->andWhere(['>', 'i.date_expire', date('Y-m-d H:i:s')])
            ->column();
        $members = array_values(array_intersect($emails, array_map([self::class, 'normalizeEmail'], $memberEmails)));
        $pending = array_values(array_diff(array_intersect($emails, array_map([self::class, 'normalizeEmail'], $pendingEmails)), $members));

        return ['members' => $members, 'pending' => $pending];
    }

    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
