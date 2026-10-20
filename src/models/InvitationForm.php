<?php

namespace portalium\workspace\models;

use portalium\workspace\Module;
use yii\base\Model;
use yii\validators\EmailValidator;

/**
 * This is the model class for table "workspace_invitation".
 *
 * @property int $id_workspace
 * @property [] $emails
 * @property string $date_expire
 */
class InvitationForm extends Model
{
    public const SCENARIO_CREATE = 'create';
    public const MAX_EMAILS = 20;
    public const DEFAULT_EXPIRY = '+7 days';

    public $id_workspace;
    public $emails;
    public $date_expire;
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_workspace'], 'required'],
            [['id_workspace'], 'integer'],
            [['emails'], 'filter', 'filter' => [self::class, 'normalizeEmails']],
            [['emails'], 'required', 'on' => self::SCENARIO_CREATE, 'message' => Module::t('Enter at least one email address.')],
            [['emails'], 'validateEmails'],
            [['date_expire', 'id_workspace'], 'safe'],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
            [['date_expire'], 'default', 'value' => static fn () => date('Y-m-d H:i:s', strtotime(self::DEFAULT_EXPIRY)), 'on' => self::SCENARIO_CREATE],
            [['date_expire'], 'validateExpiry'],
        ];
    }

    /**
     * Serbest metinden (virgül, noktalı virgül, boşluk ya da satır sonuyla ayrılmış) ya da diziden
     * küçük harfli, tekrarsız bir adres listesi üretir.
     *
     * @param mixed $value
     * @return string[]
     */
    public static function normalizeEmails($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $emails = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            foreach (preg_split('/[,;\s]+/u', (string) $item) as $part) {
                $part = mb_strtolower(trim($part));
                if ($part !== '' && !in_array($part, $emails, true)) {
                    $emails[] = $part;
                }
            }
        }
        return $emails;
    }

    /**
     * Her adres tek tek denetlenir; hatalı olanlar adıyla bildirilir.
     */
    public function validateEmails($attribute)
    {
        $emails = (array) $this->$attribute;
        if (count($emails) > self::MAX_EMAILS) {
            $this->addError($attribute, Module::t('You can invite at most {max} addresses at once.', ['max' => self::MAX_EMAILS]));
            return;
        }
        $validator = new EmailValidator();
        $invalid = array_values(array_filter($emails, static fn ($email) => !$validator->validate($email)));
        if ($invalid !== []) {
            $this->addError($attribute, Module::t('Invalid email address: {emails}', ['emails' => implode(', ', $invalid)]));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_workspace' => Module::t('Workspace'),
            'emails' => Module::t('Emails'),
            'date_expire' => Module::t('Date Expire'),
        ];
    }

    /**
     * Son geçerlilik tarihi Y-m-d ya da Y-m-d H:i[:s] biçiminde ve gelecekte olmalı.
     * Yalnızca gün seçildiyse davet o günün sonuna kadar geçerlidir (bugün seçilebilir).
     */
    public function validateExpiry($attribute)
    {
        $value = $this->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) || strtotime($value) === false) {
            $this->addError($attribute, Module::t('Invalid date.'));
            return;
        }
        if (strlen($value) === 10) {
            $value = $value . ' 23:59:59';
            $this->$attribute = $value;
        }
        if (strtotime($value) <= time()) {
            $this->addError($attribute, Module::t('The expiry date must be in the future.'));
        }
    }
}
