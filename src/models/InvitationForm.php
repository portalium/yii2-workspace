<?php

namespace portalium\workspace\models;

use portalium\user\models\User;
use Yii;
use portalium\workspace\Module;
use yii\base\Model;

/**
 * This is the model class for table "workspace_invitation".
 *
 * @property int $id_workspace
 * @property [] $emails
 * @property string $date_expire
 */
class InvitationForm extends Model
{
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
            [['emails'], 'safe'],
            [['date_expire', 'id_workspace'], 'safe'],
            [['id_workspace'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['id_workspace' => 'id_workspace']],
        ];
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
}
