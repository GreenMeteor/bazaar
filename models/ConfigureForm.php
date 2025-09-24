<?php

namespace humhub\modules\bazaar\models;

use Yii;
use yii\base\Model;

/**
 * ConfigureForm for Bazaar module settings
 */
class ConfigureForm extends Model
{
    public $apiBaseUrl = 'https://greenmeteor.net/api/modules.php';
    public $apiKey;
    public $cacheTimeout = 3600;
    public $enablePurchasing = true;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['apiBaseUrl'], 'required'],
            [['apiBaseUrl'], 'url'],
            [['apiKey'], 'string', 'max' => 255],
            [['cacheTimeout'], 'integer', 'min' => 60, 'max' => 86400], // 1 min to 24 hours
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
     * Load current configuration
     */
    public function loadFromModule()
    {
        $module = Yii::$app->getModule('bazaar');
        if ($module) {
            $this->apiBaseUrl = $module->apiBaseUrl ?? $this->apiBaseUrl;
            $this->apiKey = $module->apiKey ?? '';
            $this->cacheTimeout = $module->cacheTimeout ?? $this->cacheTimeout;
            $this->enablePurchasing = $module->enablePurchasing ?? $this->enablePurchasing;
        }
    }

    /**
     * Save configuration to module settings
     */
    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $module = Yii::$app->getModule('bazaar');
        if ($module) {
            // In a real implementation, you'd save these to database or config files
            // For now, we'll just set them on the module instance
            $module->apiBaseUrl = $this->apiBaseUrl;
            $module->apiKey = $this->apiKey;
            $module->cacheTimeout = (int)$this->cacheTimeout;
            $module->enablePurchasing = (bool)$this->enablePurchasing;
            
            // You might want to save to database here:
            // $this->saveToDatabase();
        }

        return true;
    }

    /**
     * Test API connection
     */
    public function testConnection()
    {
        try {
            $client = new \yii\httpclient\Client([
                'baseUrl' => $this->apiBaseUrl,
            ]);

            $response = $client->get('', [
                'action' => 'list',
                'format' => 'json',
            ])->send();

            if ($response->isOk) {
                return [
                    'success' => true,
                    'message' => Yii::t('BazaarModule.base', 'API connection successful'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => Yii::t('BazaarModule.base', 'API connection failed: HTTP {code}', ['code' => $response->statusCode]),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => Yii::t('BazaarModule.base', 'API connection failed: {error}', ['error' => $e->getMessage()]),
            ];
        }
    }
}