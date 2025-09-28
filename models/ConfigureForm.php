<?php

namespace humhub\modules\bazaar\models;

use Yii;
use humhub\components\SettingsManager;

class ConfigureForm extends \yii\base\Model
{
    public $apiBaseUrl;
    public $apiKey;
    public $cacheTimeout;
    public $enablePurchasing;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['apiBaseUrl'], 'required'],
            [['apiBaseUrl'], 'url'],
            [['apiKey'], 'string', 'max' => 255],
            [['cacheTimeout'], 'integer', 'min' => 60],
            [['enablePurchasing'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'apiBaseUrl' => Yii::t('BazaarModule.base', 'API Base URL'),
            'apiKey' => Yii::t('BazaarModule.base', 'API Key'),
            'cacheTimeout' => Yii::t('BazaarModule.base', 'Cache Timeout (seconds)'),
            'enablePurchasing' => Yii::t('BazaarModule.base', 'Enable Purchasing'),
        ];
    }

    /**
     * Load settings
     */
    public function loadSettings()
    {
        $settings = Yii::$app->getModule('bazaar')->settings;

        $this->apiBaseUrl = $settings->get('apiBaseUrl', 'https://api.greenmeteor.net/v1');
        $this->apiKey = $settings->get('apiKey', '');
        $this->cacheTimeout = (int)$settings->get('cacheTimeout', 3600);
        $this->enablePurchasing = (bool)$settings->get('enablePurchasing', true);
    }

    /**
     * Save settings
     */
    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $settings = Yii::$app->getModule('bazaar')->settings;

        $settings->set('apiBaseUrl', $this->apiBaseUrl);
        $settings->set('apiKey', $this->apiKey);
        $settings->set('cacheTimeout', $this->cacheTimeout);
        $settings->set('enablePurchasing', $this->enablePurchasing);

        return true;
    }
}