<?php

namespace portalium\workspace\bundles;

use yii\web\AssetBundle;

class AssignmentAsset extends AssetBundle
{
    public $sourcePath = '@vendor/portalium/yii2-workspace/src/assets/';

    public $js = [
        'assignment.js'
    ];

    public $publishOptions = [
        'forceCopy' => YII_DEBUG,
    ];

    public $jsOptions = [
        'position' => \yii\web\View::POS_END
    ];

    public function init()
    {
        parent::init();
    }
}
