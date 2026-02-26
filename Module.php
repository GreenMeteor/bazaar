<?php

namespace humhub\modules\bazaar;

use Yii;
use yii\helpers\Url;
use humhub\components\Module as BaseModule;
use humhub\modules\admin\permissions\ManageSettings;

/**
 * Bazaar Module
 *
 * Provides a storefront inside the HumHub admin panel for browsing and
 * purchasing Green Meteor modules. Paid modules are processed via Stripe
 * Checkout hosted on greenmeteor.net.
 */
class Module extends BaseModule
{
    /**
     * @var string Green Meteor modules API endpoint
     */
    public string $apiBaseUrl = 'https://greenmeteor.net/api/modules.php';

    /**
     * @var string Optional API key (not required for the public module list)
     */
    public string $apiKey = '';

    /**
     * @var int How long (in seconds) to cache the module catalogue. Default 1 hour.
     */
    public int $cacheTimeout = 3600;

    /**
     * @var bool Allow admins to initiate purchases from this interface.
     */
    public bool $enablePurchasing = true;

    /**
     * @var bool Route all requests through the Green Meteor API domain.
     */
    public bool $useGreenMeteorApi = true;

    /**
     * @inheritdoc
     */
    public function getConfigUrl(): string
    {
        return Url::to(['/bazaar/admin/config']);
    }

    /**
     * @inheritdoc
     */
    public function getPermissions($contentContainer = null): array
    {
        if ($contentContainer !== null) {
            return [];
        }

        return [
            new ManageSettings(),
        ];
    }

    /**
     * Returns a configured ApiService instance.
     *
     * Settings are read from the HumHub settings manager (persisted via
     * ConfigureForm) and override the module-level defaults.
     *
     * @return \humhub\modules\bazaar\services\ApiService
     */
    public function getApiService(): \humhub\modules\bazaar\services\ApiService
    {
        $settings = $this->settings;

        $baseUrl = $settings->get('apiBaseUrl', $this->apiBaseUrl);
        $apiKey = $settings->get('apiKey', $this->apiKey);
        $cacheTimeout = (int)$settings->get('cacheTimeout', $this->cacheTimeout);

        return Yii::createObject([
            'class' => 'humhub\modules\bazaar\services\ApiService',
            'baseUrl' => $baseUrl,
            'apiKey' => $apiKey,
            'useGreenMeteorApi' => $this->useGreenMeteorApi,
        ]);
    }
}