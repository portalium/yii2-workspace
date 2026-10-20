<?php

namespace portalium\workspace\bundles;

use yii\web\AssetBundle;

/**
 * Davet listesi: doğrulama hatasından sonra davet penceresini yazılanlarla yeniden açar, gönderirken düğmeyi
 * pasifleştirip bekleme göstergesi gösterir (çift gönderim olmaz) ve satır düğmelerini biçimler. Kendi dizinini
 * (assets/invitation) yayınlar; AssignmentAsset ile aynı kaynak dizini paylaşmaz.
 */
class InvitationAsset extends AssetBundle
{
    public $sourcePath = '@vendor/portalium/yii2-workspace/src/assets/invitation';

    public $css = [
        'css/invitation.css',
    ];

    public $js = [
        'js/invitation.js',
    ];

    public $publishOptions = [
        'forceCopy' => YII_DEBUG,
    ];

    public $jsOptions = [
        'position' => \yii\web\View::POS_END,
        'appendTimestamp' => true,
    ];

    public $depends = [
        'portalium\theme\bundles\AppAsset',
    ];
}
