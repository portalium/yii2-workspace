<?php

namespace portalium\workspace;

class Module extends \portalium\base\Module
{
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

    /* public function registerEvents()
    {
        \Yii::$app->on(\portalium\user\Module::EVENT_USER_CREATE, [new TriggerActions(), 'onUserCreateAfter']);
    } */
}