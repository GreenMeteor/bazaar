<?php

namespace humhub\modules\bazaar\assets;

use Yii;
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

    public static function register($view)
    {
        Yii::$app->view->registerJsConfig('bazaar', [
            'text' => [
                'testingConnection' => Yii::t('BazaarModule.javascript', 'Testing connection…'),
                'connectionFailed' => Yii::t('BazaarModule.javascript', 'Could not reach the API. Please try again later or contact support.'),
                'cacheCleared' => Yii::t('BazaarModule.javascript', 'Cache cleared. Reloading…'),
                'cacheFailed' => Yii::t('BazaarModule.javascript', 'Failed to clear cache.'),
            ],
        ]);

        return parent::register($view);
    }
}