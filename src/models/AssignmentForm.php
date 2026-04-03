<?php

namespace portalium\workspace\models;

use Yii;
use portalium\workspace\Module;

/**
 *
 * @property int $id
 * @property string $role
 * @property [] $selected_values
 * @property int $id_module
 * @property string $type
 *
 */
class AssignmentForm extends \yii\base\Model
{

    public $id;
    public $role;
    public $selected_values;
    public $id_module;
    public $type;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['role', 'selected_values', 'id_module', 'type', 'id'], 'required'],
            [['role', 'type', 'id_module'], 'string'],
            [['selected_values'], 'each', 'rule' => ['integer']],
            [['role'], function ($attribute, $params, $validator) {
                $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
                $roles = $availableRoles[$this->id_module] ?? [];
                if (!is_array($roles) || !in_array($this->role, $roles, true)) {
                    $this->addError($attribute, Module::t('Role is not valid.'));
                }
            }],
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Module::t('ID'),
            'role' => Module::t('Role'),
            'selected_values' => Module::t('Selected Values'),
            'id_module' => Module::t('Module'),
            'type' => Module::t('Type'),
        ];
    }
}
