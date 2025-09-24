<?php

namespace humhub\modules\bazaar\assets;

use yii\web\AssetBundle;

class BazaarAsset extends AssetBundle
{
    public $sourcePath = '@bazaar/resources';

    public $css = [
        'css/bazaar.css',
    ];

    public $js = [
        'js/bazaar.js',
    ];

    public $depends = [
        'humhub\assets\CoreApiAsset',
        'humhub\assets\JqueryAsset',
        'humhub\modules\ui\assets\BootstrapAsset',
    ];
}