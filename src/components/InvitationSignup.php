<?php

namespace portalium\workspace\components;

use portalium\workspace\models\Invitation;
use portalium\workspace\models\InvitationRole;

/**
 * Davet bağlantısıyla gelen kaydın o davete ait olup olmadığı (karar 28, 2026-10-18; imza: karar 35/49, 2026-10-19).
 *
 * Eşleşirse yii2-auth hesabı auth::user_status ayarından bağımsız olarak aktif açar ve e-postayı doğrulanmış sayar.
 * Bunun için adresin sahibi olunduğu kanıtlanmalıdır. Token tek başına kanıt değildir: davet eden onu "Bağlantıyı
 * kopyala"dan ve API'den görür ve başkasının adresine davet açıp o adres adına kaydolabilirdi (son denetim K1).
 * Kanıt, yalnız davet edilen adrese giden e-postadaki bağlantının imzasıdır:
 *
 *     sig = hash_hmac('sha256', token . '|' . adres, davetin sırrı)
 *
 * Sır davet satırında saklanır (workspace_invitation.signup_secret), her e-posta gönderiminde yenilenir ve hiçbir
 * yanıtta gösterilmez. Bağlantıya sırrın kendisi değil HMAC'i konur: bağlantı (tarayıcı geçmişi, sunucu günlüğü)
 * sızsa da sır sızmaz; imza token'a ve adrese bağlı olduğu için token ya da adres değişirse kendiliğinden geçersizleşir.
 * Sırrı olmayan (göçten önceki) davet, imzasız token, yanlış imza → eşleşmez → normal onaylı akış.
 * Birden çok adrese ortak gönderilmiş eski bağlantı da eşleşmez (bkz. InvitationAcceptance).
 *
 * Yalnızca okur; daveti kabul etmez. Kullanıcı kayıttan sonra davet sayfasında katılmayı ayrıca onaylar.
 */
class InvitationSignup
{
    /** E-postadaki kabul ve kayıt bağlantılarında imzanın sorgu parametresi (API kaydında gövde alanı). */
    public const SIGNATURE_PARAM = 'sig';

    /**
     * Davet e-postasındaki bağlantıların imzası. Sır yoksa, bağlantı (token) yoksa ya da davetin tek bir adresi yoksa
     * null (bağlantı imzasız gider).
     */
    public static function signature(Invitation $invitation): ?string
    {
        $secret = $invitation->signupSecret();
        $token = (string) $invitation->invitation_token;
        $roles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation])->all();
        $email = self::singleAddress($roles);
        if ($secret === null || $token === '' || $email === null) {
            return null;
        }

        return self::hmac($token, $email, $secret);
    }

    /**
     * İmza geçerliyse davetin adresi (trim + küçük harf), değilse null. Davet bekliyor olmalı (kabul edilmemiş rolü
     * var, süresi dolmamış, tek adresli) ve sırrı olmalı. Karşılaştırma sabit sürelidir (hash_equals).
     */
    public static function signedAddress(string $token, string $sig): ?string
    {
        $sig = trim($sig);
        if ($sig === '') {
            return null;
        }
        $pending = self::pending($token);
        if ($pending === null) {
            return null;
        }
        $secret = $pending['invitation']->signupSecret();
        if ($secret === null) {
            return null;
        }
        $expected = self::hmac((string) $pending['invitation']->invitation_token, $pending['email'], $secret);

        return hash_equals($expected, $sig) ? $pending['email'] : null;
    }

    /**
     * İmza geçerli ve davetin adresi (trim + küçük harf) kayıttaki e-postayla aynı mı. İmzasız token (kopyalanan
     * bağlantı, API'deki token) eşleşmez.
     */
    public static function matches(string $token, string $email, string $sig = ''): bool
    {
        $email = self::normalize($email);
        if ($email === '') {
            return false;
        }

        return self::signedAddress($token, $sig) === $email;
    }

    /**
     * Kayıt formunda ve misafir davet sayfasında gösterilecek bilgi (son denetim U5): davetin çalışma alanı başlığı ve
     * gönderildiği adresin maskeli hâli (m•••@firma.com). Adresin tamamı verilmez; bağlantı başkasının elinde de
     * olabilir. Token geçerli bekleyen tek adresli bir davete ait değilse null. Yalnızca okur.
     *
     * @return array{workspace:string, masked_email:string}|null
     */
    public static function describe(string $token): ?array
    {
        $pending = self::pending($token);
        if ($pending === null) {
            return null;
        }
        $workspace = $pending['invitation']->workspace;
        if ($workspace === null) {
            return null;
        }

        return ['workspace' => $workspace->displayTitle(), 'masked_email' => self::mask($pending['email'])];
    }

    /**
     * "ali.veli@firma.com" → "a•••@firma.com". Yerel kısım boşsa ya da @ yoksa yalnız "•••".
     */
    public static function mask(string $email): string
    {
        $at = mb_strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '•••';
        }

        return mb_substr($email, 0, 1) . '•••' . mb_substr($email, $at);
    }

    /**
     * Token'ın ait olduğu, kabul edilmemiş rolü olan, süresi dolmamış, tek adresli davet ve adresi.
     *
     * @return array{invitation:Invitation, email:string}|null
     */
    private static function pending(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $invitation = Invitation::findOne(['invitation_token' => $token]);
        if ($invitation === null || strtotime((string) $invitation->date_expire) <= time()) {
            return null;
        }

        $roles = InvitationRole::find()->where(['id_invitation' => $invitation->id_invitation])->all();
        $email = self::singleAddress($roles);
        if ($email === null) {
            return null;
        }

        foreach ($roles as $role) {
            if ((int) $role->status !== InvitationRole::STATUS_ACCEPTED) {
                return ['invitation' => $invitation, 'email' => $email];
            }
        }

        return null;
    }

    /**
     * Rol satırlarının tek ortak adresi (trim + küçük harf); hiç satır yoksa, adres boşsa ya da birden çok adres
     * varsa null.
     *
     * @param InvitationRole[] $roles
     */
    private static function singleAddress(array $roles): ?string
    {
        $addresses = array_unique(array_map(static fn (InvitationRole $role) => self::normalize($role->email), $roles));
        if (count($addresses) !== 1 || reset($addresses) === '') {
            return null;
        }

        return (string) reset($addresses);
    }

    private static function hmac(string $token, string $email, string $secret): string
    {
        return hash_hmac('sha256', $token . '|' . $email, $secret);
    }

    private static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
