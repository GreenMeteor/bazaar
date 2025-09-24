<?php

namespace humhub\modules\bazaar;

use humhub\components\Module as BaseModule;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\bazaar\models\ConfigureForm;

class Module extends BaseModule
{
    /**
     * @var string Green Meteor API base URL
     */
    public $apiBaseUrl = 'https://greenmeteor.net/api/modules.php';

    /**
     * @var string API key for authentication (not needed for Green Meteor integration)
     */
    public $apiKey = '';

    /**
     * @var int Cache duration in seconds (default: 1 hour)
     */
    public $cacheTimeout = 3600;

    /**
     * @var bool Enable module purchasing functionality
     */
    public $enablePurchasing = true;

    /**
     * @var bool Use Green Meteor domain as API endpoint (default: true)
     */
    public $useGreenMeteorApi = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->loadSettings();
    }

    /**
     * Load settings from database
     */
    private function loadSettings()
    {
        if (!\Yii::$app->hasModule('bazaar') || !$this->settings) {
            return;
        }

        try {
            $this->apiBaseUrl = $this->settings->get('apiBaseUrl', $this->apiBaseUrl);
            $this->apiKey = $this->settings->get('apiKey', $this->apiKey);
            $this->cacheTimeout = (int)$this->settings->get('cacheTimeout', $this->cacheTimeout);
            $this->enablePurchasing = (bool)$this->settings->get('enablePurchasing', $this->enablePurchasing);
        } catch (\Exception $e) {
        }
    }

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return \yii\helpers\Url::to(['/bazaar/admin/config']);
    }

    /**
     * @inheritdoc
     */
    public function getPermissions($contentContainer = null)
    {
        if ($contentContainer !== null) {
            return [];
        }

        return [
            new ManageSettings(),
        ];
    }

    /**
     * Get service for API communication
     * @return \humhub\modules\bazaar\services\ApiService
     */
    public function getApiService()
    {
        return \Yii::createObject([
            'class' => 'humhub\modules\bazaar\services\ApiService',
            'baseUrl' => $this->apiBaseUrl,
            'apiKey' => $this->apiKey,
            'useGreenMeteorApi' => $this->useGreenMeteorApi,
        ]);
    }
}