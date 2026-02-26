<?php

namespace humhub\modules\bazaar\services;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;
use yii\httpclient\Client;
use humhub\modules\bazaar\models\Module;

/**
 * ApiService
 *
 * Handles all HTTP communication with the Green Meteor Bazaar API:
 *   - GET /api/modules.php?action=list → module catalogue
 *   - GET /api/modules.php?action=get  → single module
 *   - POST /api/modules.php action=purchase → create Stripe checkout session
 *   - GET /api/verify-purchase.php?session_id= → verify a Stripe payment
 *
 */
class ApiService extends Component
{
    /** @var string Base URL for the modules API */
    public string $baseUrl = 'https://greenmeteor.net/api/modules.php';

    /** @var string Verify-purchase endpoint */
    public string $verifyUrl = 'https://greenmeteor.net/api/verify-purchase.php';

    /** @var string API key (passed as X-Api-Key header when set) */
    public string $apiKey = '';

    /** @var bool Reserved for future multi-endpoint support */
    public bool $useGreenMeteorApi = true;

    /** @var Client */
    private Client $_client;

    public function init(): void
    {
        parent::init();

        $module = Yii::$app->getModule('bazaar');
        $this->apiKey = $module->apiKey ?? '';

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'HumHub-Bazaar/1.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if ($this->apiKey !== '') {
            $headers['X-Api-Key'] = $this->apiKey;
        }

        $this->_client = new Client([
            'baseUrl' => $this->baseUrl,
            'requestConfig' => [
                'headers' => $headers,
            ],
        ]);
    }

    /**
     * Returns all modules from the API as plain arrays.
     *
     * Caching strategy:
     *   - Result is cached per user (keyed by email/session-id).
     *   - If the cached data contains any paid-but-unpurchased modules we
     *     immediately bust that cache slice and do a fresh fetch. This ensures
     *     that after a purchase (or manual credit) completes, the very next
     *     page load shows Install instead of Buy without waiting for TTL.
     *   - If every paid module is already marked purchased the cache is kept.
     *
     * Falls back to an empty array on failure so the index page renders
     * gracefully with an error flash message set in the controller.
     *
     * @return array[]
     */
    public function getModules(): array
    {
        $module = Yii::$app->getModule('bazaar');
        $userIdentifier = $this->getCurrentUserIdentifier();
        $cacheKey = 'bazaar_modules_' . md5($userIdentifier);

        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached !== false && is_array($cached)) {
            $hasPaidUnpurchased = array_filter(
                $cached,
                static fn(array $m): bool => ($m['isPaid'] ?? false) && !($m['isPurchased'] ?? false)
            );

            if (empty($hasPaidUnpurchased)) {
                return $cached;
            }

            Yii::$app->cache->delete($cacheKey);
        }

        return Yii::$app->cache->getOrSet(
            $cacheKey,
            function () use ($userIdentifier): array {
                try {
                    $response = $this->_client->createRequest()
                        ->setMethod('GET')
                        ->setUrl('')
                        ->setData([
                            'action' => 'list',
                            'format' => 'json',
                            'include_purchased' => $userIdentifier,
                        ])
                        ->setFormat(Client::FORMAT_URLENCODED)
                        ->send();

                    if ($response->isOk) {
                        $data = $response->data;

                        if (isset($data['data']) && is_array($data['data'])) {
                            return array_map([$this, 'mapModuleData'], $data['data']);
                        }

                        if (is_array($data) && !empty($data)) {
                            return array_map([$this, 'mapModuleData'], $data);
                        }
                    }

                    return [];

                } catch (\Exception $e) {
                    return [];
                }
            },

            $module->cacheTimeout ?? 3600,

            new TagDependency(['tags' => ['bazaar_modules']])
        );
    }

    /**
     * Returns a single Module model by ID, or null if not found.
     *
     * Resolution order:
     *   1. Search the cached per-user catalogue (no extra API call).
     *   2. For paid modules not yet marked purchased in the cache, perform a
     *      fresh lightweight API check using the current user's email so that a
     *      recently completed purchase / manual credit is always reflected
     *      without waiting for the cache to expire or be manually cleared.
     *   3. Fall back to a direct ?action=get request for coming-soon /
     *      uncached modules not present in the catalogue at all.
     *
     * @param  string $id  Module ID (numeric or slug string)
     * @return Module|null
     */
    public function getModule(string $id): ?Module
    {
        $userIdentifier = $this->getCurrentUserIdentifier();
        $modulesData    = $this->getModules();

        foreach ($modulesData as $moduleData) {
            if ((string)$moduleData['id'] !== $id) {
                continue;
            }

            if (($moduleData['isPaid'] ?? false) && !($moduleData['isPurchased'] ?? false)) {
                $freshPurchased = $this->checkPurchaseStatus($id, $userIdentifier);

                if ($freshPurchased) {
                    $moduleData['isPurchased'] = true;
                    $moduleData['downloadUrl'] = $moduleData['downloadUrl']
                        ?? "https://greenmeteor.net/download?module={$id}";

                    TagDependency::invalidate(Yii::$app->cache, ['bazaar_modules']);
                }
            }

            return new Module($moduleData);
        }

        try {
            $response = $this->_client->createRequest()
                ->setMethod('GET')
                ->setUrl('')
                ->setData([
                    'action' => 'get',
                    'module_id' => $id,
                    'include_purchased' => $userIdentifier,
                ])
                ->setFormat(Client::FORMAT_URLENCODED)
                ->send();

            if ($response->isOk && isset($response->data['data'])) {
                return new Module($this->mapModuleData($response->data['data']));
            }

        } catch (\Exception $e) {
            Yii::error(
                'ApiService::getModule direct fetch failed for id=' . $id . ': ' . $e->getMessage(),
                'bazaar'
            );
        }

        return null;
    }

    /**
     * Initiates a module purchase by asking the Green Meteor API to create a
     * Stripe Checkout session.
     *
     * For free modules the API returns is_free: true; for paid modules it
     * returns checkout_url which the controller must redirect to.
     *
     * @param  string|int $moduleId  Module ID
     * @param  array $options Must include return_url and cancel_url
     * @return array API response data (checkout_url or is_free)
     * @throws Exception On HTTP error or missing checkout URL
     */
    public function purchaseModule(string|int $moduleId, array $options = []): array
    {
        $userEmail = $this->getCurrentUserIdentifier();
        $siteUrl = Yii::$app->request->hostInfo;

        $postData = [
            'action' => 'purchase',
            'module_id' => $moduleId,
            'return_url' => $options['return_url'] ?? '',
            'cancel_url' => $options['cancel_url'] ?? '',
            'user_email' => $userEmail,
            'site_url' => $siteUrl,
        ];

        try {
            $response = $this->_client->createRequest()
                ->setMethod('POST')
                ->setUrl('')
                ->setData($postData)
                ->setFormat(Client::FORMAT_URLENCODED)
                ->send();

            if (!$response->isOk) {
                throw new Exception(
                    "Purchase API returned HTTP {$response->statusCode}: {$response->content}"
                );
            }

            $data = $response->data;

            if (empty($data) && $response->content !== '') {
                $data = json_decode($response->content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Non-JSON purchase response: ' . $response->content);
                }
            }

            if (isset($data['error'])) {
                throw new Exception('API error: ' . $data['error']);
            }

            if (empty($data['checkout_url']) && empty($data['is_free'])) {
                throw new Exception(
                    'Missing checkout_url or is_free in API response: ' . json_encode($data)
                );
            }

            return $data;

        } catch (\yii\httpclient\Exception $e) {
            throw new Exception('HTTP client exception: ' . $e->getMessage());
        }
    }

    /**
     * Calls /api/verify-purchase.php to confirm a Stripe Checkout session
     * was actually paid.
     *
     * @param  string $stripeSessionId  The cs_xxx session ID from Stripe
     * @return array  verified, module_id, payment_status, download_url
     * @throws Exception On HTTP or JSON error
     */
    public function verifyPurchase(string $stripeSessionId): array
    {
        try {
            $verifyClient = new Client([
                'baseUrl' => $this->verifyUrl,
                'requestConfig' => [
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => 'HumHub-Bazaar/1.0',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ],
                ],
            ]);

            $response = $verifyClient->createRequest()
                ->setMethod('GET')
                ->setUrl('')
                ->setData([
                    'session_id' => $stripeSessionId,
                    'user_session' => session_id(),
                ])
                ->setFormat(Client::FORMAT_URLENCODED)
                ->send();

            if (!$response->isOk) {
                throw new Exception("Verify API returned HTTP {$response->statusCode}");
            }

            $data = $response->data;

            if (empty($data) && $response->content !== '') {
                $data = json_decode($response->content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Non-JSON verify response: ' . $response->content);
                }
            }

            if (isset($data['error'])) {
                throw new Exception('Verify API error: ' . $data['error']);
            }

            $moduleId = $data['module_id'] ?? null;

            return [
                'verified' => (bool)($data['verified'] ?? false),
                'module_id' => $moduleId,
                'payment_status' => $data['payment_status'] ?? 'unknown',
                'download_url' => ($data['verified'] ?? false) && $moduleId
                    ? ($data['download_url'] ?? "https://greenmeteor.net/download?module={$moduleId}")
                    : null,
            ];

        } catch (\yii\httpclient\Exception $e) {
            throw new Exception('HTTP client exception during verify: ' . $e->getMessage());
        }
    }

    /**
     * Makes a fresh, uncached API check to see if the current user has already
     * purchased a specific module.
     *
     * Use this instead of relying on getModule() in situations where the cached
     * data may be stale:
     *   - The purchase-success page (just completed a Stripe payment)
     *   - The install action (guard before allowing download)
     *   - Any place where a user claims they've already purchased
     *
     * Also invalidates the current user's cache slice when a purchase is
     * confirmed so the next normal page load reflects the new state without
     * waiting for the TTL to expire.
     *
     * @param  string $moduleId
     * @param  string|null $userIdentifier Email or session-id; defaults to current user
     * @return bool
     */
    public function checkPurchaseStatus(string $moduleId, ?string $userIdentifier = null): bool
    {
        $userEmail = $userIdentifier ?? $this->getCurrentUserIdentifier();

        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $response = $this->_client->createRequest()
                ->setMethod('GET')
                ->setUrl('')
                ->setData([
                    'action' => 'get',
                    'module_id' => $moduleId,
                    'include_purchased' => $userEmail,
                ])
                ->setFormat(Client::FORMAT_URLENCODED)
                ->send();

            if (!$response->isOk) {
                return false;
            }

            $data = $response->data['data'] ?? null;
            $isPurchased = (bool)($data['is_purchased'] ?? false);

            if ($isPurchased) {
                $cacheKey = 'bazaar_modules_' . md5($userEmail);

                Yii::$app->cache->delete($cacheKey);
            }

            return $isPurchased;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Normalises raw API data (snake_case keys) into the camelCase array
     * expected by the Module model.
     *
     * downloadUrl is built whenever the module is accessible (free OR
     * purchased). Both isPurchased AND downloadUrl must be truthy for the
     * Install button to render in the views.
     *
     * @param  array $data  Raw row from the API response
     * @return array
     */
    private function mapModuleData(array $data): array
    {
        $price = 0.0;
        if (isset($data['price'])) {
            $price = is_numeric($data['price'])
                ? (float)$data['price']
                : (float)preg_replace('/[^0-9.]/', '', (string)$data['price']);
        }

        $isPaid = isset($data['is_paid']) ? (bool)$data['is_paid'] : $price > 0;
        $isPurchased = (bool)($data['is_purchased'] ?? false);

        $downloadUrl = null;

        if (!$isPaid || $isPurchased) {
            $downloadUrl = $data['download_url']
                ?? "https://greenmeteor.net/download?module={$data['id']}";
        }

        $screenshots = [];

        if (!empty($data['image'])) {
            $screenshots[] = $data['image'];
        }

        if (!empty($data['screenshots']) && is_array($data['screenshots'])) {
            $screenshots = array_merge($screenshots, $data['screenshots']);
        }

        $screenshots = array_values(array_unique(array_filter($screenshots)));

        return [
            'id' => (string)($data['id'] ?? ''),
            'name' => $data['name'] ?? '',
            'description'  => $data['description'] ?? '',
            'version' => $data['version'] ?? '1.0.0',
            'price' => $price,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'isPaid' => $isPaid,
            'isPurchased' => $isPurchased,
            'isSoon' => (bool)($data['is_soon'] ?? false),
            'category' => $data['category'] ?? $this->inferCategory($data['name'] ?? '', $data['description'] ?? ''),
            'author' => $data['author']               ?? 'Green Meteor',
            'screenshots' => $screenshots,
            'features' => $this->parseFeatures($data),
            'requirements' => $data['requirements'] ?? [],
            'downloadUrl' => $downloadUrl,
            'productId'    => $data['product_id'] ?? null,
            'priceId' => $data['price_id'] ?? null,
        ];
    }

    /**
     * Infers a category from the module name and description when the API
     * does not supply one explicitly.
     */
    private function inferCategory(string $name, string $description): string
    {
        $text = strtolower($name . ' ' . $description);

        $rules = [
            'productivity' => ['calendar', 'event', 'schedule', 'reminder', 'task', 'todo', 'issue'],
            'social' => ['poll', 'survey', 'vote', 'like', 'reaction'],
            'communication' => ['message', 'mail', 'chat', 'notification', 'mention'],
            'content' => ['wiki', 'docs', 'document', 'page', 'article', 'blog'],
            'integration' => ['shop', 'store', 'commerce', 'stripe', 'api', 'webhook'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /**
     * Extracts features from API data.
     *
     * Priority:
     *   1. metadata.features (comma-separated string)
     *   2. features array returned directly by the API
     *
     * @param  array    $data
     * @return string[]
     */
    private function parseFeatures(array $data): array
    {
        if (!empty($data['metadata']['features']) && is_string($data['metadata']['features'])) {
            $features = array_values(array_filter(
                array_map('trim', explode(',', $data['metadata']['features']))
            ));

            if (!empty($features)) {
                return $features;
            }
        }

        if (!empty($data['features']) && is_array($data['features'])) {
            return $data['features'];
        }

        return [];
    }

    /**
     * Returns a stable identifier for the current user, used by the API to
     * determine which modules they have purchased.
     *
     * Tries several known HumHub identity property paths before falling back
     * to a direct DB lookup, then finally to session_id() if no email can
     * be resolved (session_id always yields no purchases on greenmeteor.net).
     */
    private function getCurrentUserIdentifier(): string
    {
        $identity = Yii::$app->user->identity;

        if (!$identity) {
            return session_id();
        }

        if (!empty($identity->email)) {
            return (string)$identity->email;
        }

        if (isset($identity->profile) && !empty($identity->profile->email)) {
            return (string)$identity->profile->email;
        }

        if (!empty($identity->id)) {
            try {
                $user = \humhub\modules\user\models\User::findOne($identity->id);
                if ($user && !empty($user->email)) {
                    return (string)$user->email;
                }
            } catch (\Throwable $e) {
            }
        }

        return session_id();
    }
}