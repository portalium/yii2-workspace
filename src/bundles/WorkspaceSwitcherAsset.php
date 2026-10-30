<?php

namespace portalium\workspace\bundles;

use yii\web\AssetBundle;

/**
 * Styles of the workspace card in the side menu ({@see \portalium\workspace\widgets\WorkspaceSwitcher}).
 */
class WorkspaceSwitcherAsset extends AssetBundle
{
    public $sourcePath = '@vendor/portalium/yii2-workspace/src/assets/switcher';

    public $css = [
        'css/workspace-switcher.css',
    ];

    public $depends = [
        'portalium\theme\bundles\AppAsset',
    ];
}
