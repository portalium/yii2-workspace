<?php

namespace portalium\workspace;

use portalium\base\Event;
use portalium\workspace\components\TriggerActions;

class Module extends \portalium\base\Module
{
    const EVENT_ROLE_UPDATE_AFTER = 'roleUpdateAfter';
    const EVENT_USER_CREATE_AFTER = 'userCreateAfter';

    const EVENT_WORKSPACE_CREATE_AFTER = 'workspaceCreateAfter';
    
    public $apiRules = [
        [
            'class' => 'yii\rest\UrlRule',
            'controller' => [
                'workspace/default',
            ]
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
    }
}