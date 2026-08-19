<?php

namespace portalium\workspace;

use portalium\base\Event;
use portalium\workspace\components\TriggerActions;

class Module extends \portalium\base\Module
{
    const EVENT_ROLE_UPDATE_AFTER = 'roleUpdateAfter';
    const EVENT_USER_CREATE_AFTER = 'userCreateAfter';

    const EVENT_WORKSPACE_CREATE_AFTER = 'workspaceCreateAfter';
    const EVENT_WORKSPACE_DELETE_BEFORE = 'workspaceDeleteBefore';
    
    public static $supportWorkspace = true;
    public $apiRules = [
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => [
                'workspace/user',
            ],
            'pluralize' => false,
        ],
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => [
                'workspace/invitation',
            ],
            'pluralize' => false,

            'extraPatterns' => [
                'POST resend/{id}' => 'resend',
                'GET,POST accept' => 'accept',
                'POST reject' => 'reject',
                'POST reject-expired' => 'reject-expired',
                'POST reject_expired' => 'reject-expired',
                'POST rejectExpired' => 'reject-expired',
            ],
        ],
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => [
                'workspace/default',
            ],
            'pluralize' => false,

            'extraPatterns' => [
                'POST set-workspace' => 'set-workspace',
                'POST set_workspace' => 'set-workspace',
                'POST setWorkspace' => 'set-workspace',

                'GET manage' => 'manage',
            ],
        ],
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => [
                'workspace/assignment',
            ],
            'pluralize' => false,
            'extraPatterns' => [

                'GET assignment/<id:\d+>' => 'assignment',
                'POST assign' => 'assign',
                'POST assign-update' => 'assign-update',
                'POST assign_update' => 'assign-update',
                'POST assignUpdate' => 'assign-update',
                'POST remove' => 'remove',
                'GET assigned-users/<id:\d+>' => 'assigned-users',
                'GET assigned_users/<id:\d+>' => 'assigned-users',
                'GET assignedUsers/<id:\d+>' => 'assigned-users',
                'GET get-roles' => 'get-roles',
                'GET get_roles' => 'get-roles',
                'GET getRoles' => 'get-roles',
                'POST get-role-by-module' => 'get-role-by-module',
                'POST get_role_by_module' => 'get-role-by-module',
                'POST getRoleByModule' => 'get-role-by-module',
            ],
        ],
    ];
    public static $tablePrefix = 'workspace_';
    public static $name = 'Workspace';
    public static function moduleInit()
    {
        self::registerTranslation('workspace', '@portalium/workspace/messages', [
            'workspace' => 'workspace.php',
        ]);
    }

    public function getMenuItems()
    {
        $menuItems = [
            [
                [
                    'menu' => 'web',
                    'type' => 'action',
                    'route' => '/workspace/default/index',
                ],
                [
                    'menu' => 'web',
                    'type' => 'action',
                    'route' => '/workspace/default/manage',
                ],
                [
                    'menu' => 'web',
                    'type' => 'widget',
                    'label' => 'portalium\workspace\widgets\Workspace',
                    'name' => 'Workspace',
                ],
                
            ],
        ];
        return $menuItems;
    }

    public function registerComponents()
    {
        return [
            'workspace' => [
                'class' => 'portalium\workspace\components\Workspace',
            ]
        ];
    }

    public static function t($message, array $params = [])
    {
        return parent::coreT('workspace', $message, $params);
    }

    public function registerEvents()
    {
        Event::on($this::className(), \portalium\rbac\Module::EVENT_ITEM_DELETE, [new TriggerActions(), 'onRoleDeleteBefore']);
        Event::on($this::className(), \portalium\rbac\Module::EVENT_ITEM_UPDATE, [new TriggerActions(), 'onRoleUpdateBefore']);
        Event::on($this::className(), \portalium\site\Module::EVENT_SETTING_UPDATE, [new TriggerActions(), 'onSettingUpdateAfter']);
        Event::on($this::className(), \portalium\user\Module::EVENT_USER_CREATE, [new TriggerActions(), 'onUserCreateAfter']);
        Event::on($this::className(), \portalium\user\Module::EVENT_USER_DELETE_BEFORE, [new TriggerActions(), 'onUserDeleteBefore']);
    }
}