<?php

namespace humhub\modules\bazaar\services;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;
use yii\httpclient\Client;
use humhub\modules\bazaar\models\Module;

/**
 * Class ApiService
 *
 * Handles communication with the Green Meteor Bazaar API
 * and provides caching, scraping fallback, and module purchase functionality.
 */
class ApiService extends Component
{
    public string $baseUrl = 'https://greenmeteor.net/api/modules.php';
    public string $apiKey = '';
    private Client $_client;
    public bool $useGreenMeteorApi = true;

    public function init(): void
    {
        parent::init();
        $module = Yii::$app->getModule('bazaar');
        $this->apiKey = $module->apiKey;

        $this->_client = new Client([
            'baseUrl' => $this->baseUrl,
            'requestConfig' => [
                'format' => Client::FORMAT_JSON,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'HumHub-Bazaar/1.0',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
        ]);
    }

    public function getModules(): array
    {
        $cacheKey = 'bazaar_modules';
        $module = Yii::$app->getModule('bazaar');

        return Yii::$app->cache->getOrSet(
            $cacheKey,
            function () {
                try {
                    $response = $this->_client->get('', [
                        'action' => 'list',
                        'format' => 'json',
                        'include_purchased' => $this->getCurrentUserSession(),
                    ])->send();

                    if ($response->isOk && isset($response->data['data'])) {
                        Yii::info('API Response received: ' . json_encode($response->data), 'bazaar');
                        return array_map([$this, 'mapModuleData'], $response->data['data']);
                    }

                    Yii::warning('API failed, falling back to scraping', 'bazaar');
                    return array_map([$this, 'mapModuleData'], $this->scrapeModulesFromPage());

                } catch (\Exception $e) {
                    Yii::error('Green Meteor API error: ' . $e->getMessage(), 'bazaar');
                    return array_map([$this, 'mapModuleData'], $this->scrapeModulesFromPage());
                }
            },
            $module->cacheTimeout ?? 3600,
            new TagDependency(['tags' => ['bazaar_modules']])
        );
    }

    public function getModule(string $id): ?Module
    {
        $modulesData = $this->getModules();
        foreach ($modulesData as $moduleData) {
            if ($moduleData['id'] === $id) {
                return new Module($moduleData);
            }
        }

        return null;
    }

    public function purchaseModule($moduleId, array $options = []): array
    {
        Yii::info("purchaseModule called with moduleId: {$moduleId}, options: " . json_encode($options), 'bazaar');

        if (is_numeric($moduleId)) {
            $moduleId = (int)$moduleId;

            $postData = [
                'action' => 'purchase',
                'module_id' => $moduleId,
                'return_url' => $options['return_url'] ?? '',
                'cancel_url' => $options['cancel_url'] ?? '',
            ];

            Yii::info("Making API call with data: " . json_encode($postData), 'bazaar');

            try {
                // Use POST with form data instead of JSON
                $response = $this->_client->createRequest()
                    ->setMethod('POST')
                    ->setUrl('')
                    ->setData($postData)
                    ->setFormat(Client::FORMAT_URLENCODED) // Key change: use form data
                    ->send();

                Yii::info("API response status: " . $response->statusCode, 'bazaar');
                Yii::info("API response headers: " . json_encode($response->headers->toArray()), 'bazaar');
                Yii::info("API raw response: " . $response->content, 'bazaar');

                if (!$response->isOk) {
                    $errorMsg = "Purchase API failed for module {$moduleId}: HTTP {$response->statusCode}";
                    Yii::error($errorMsg, 'bazaar');
                    Yii::error("Response content: " . $response->content, 'bazaar');
                    throw new Exception($errorMsg);
                }

                // Handle both JSON and raw content responses
                $data = $response->data;
                if (empty($data) && $response->content) {
                    $data = json_decode($response->content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid JSON response: " . $response->content);
                    }
                }

                Yii::info("API response data: " . json_encode($data), 'bazaar');

                if (isset($data['error'])) {
                    $errorMsg = "Purchase failed for module {$moduleId}: " . $data['error'];
                    Yii::error($errorMsg, 'bazaar');
                    throw new Exception($errorMsg);
                }

                // Check for checkout_url in the response
                if (isset($data['checkout_url']) && !empty($data['checkout_url'])) {
                    Yii::info("Received checkout URL: " . $data['checkout_url'], 'bazaar');
                    return $data;
                }

                // Check if it's a free module
                if (isset($data['is_free']) && $data['is_free']) {
                    return $data;
                }

                // If we get here, something went wrong
                throw new Exception("No checkout URL or free module flag in response: " . json_encode($data));

            } catch (\yii\httpclient\Exception $e) {
                $errorMsg = "HTTP Client exception for module {$moduleId}: " . $e->getMessage();
                Yii::error($errorMsg, 'bazaar');
                throw new Exception($errorMsg);
            } catch (\Exception $e) {
                $errorMsg = "General exception for module {$moduleId}: " . $e->getMessage();
                Yii::error($errorMsg, 'bazaar');
                throw new Exception($errorMsg);
            }
        }

        Yii::info("Handling non-numeric module ID as free module: {$moduleId}", 'bazaar');

        if (!isset($_SESSION['purchased_modules'])) {
            $_SESSION['purchased_modules'] = [];
        }

        if (!in_array($moduleId, $_SESSION['purchased_modules'])) {
            $_SESSION['purchased_modules'][] = $moduleId;
        }

        return [
            'success' => true,
            'is_free' => true,
            'message' => "Module '{$moduleId}' marked as purchased locally.",
            'moduleId' => $moduleId,
        ];
    }

    /**
     * Map raw API or scraped module data to camelCase keys for Module model
     */
    private function mapModuleData(array $data): array
    {
        Yii::info('Mapping module data: ' . json_encode($data), 'bazaar');

        $price = 0;
        if (isset($data['price'])) {
            if (is_numeric($data['price'])) {
                $price = floatval($data['price']);
            } else {
                $priceStr = preg_replace('/[^0-9.,]/', '', (string)$data['price']);
                $price = floatval(str_replace(',', '', $priceStr));
            }
        }

        $isPaid = false;
        if (isset($data['is_paid'])) {
            $isPaid = (bool)$data['is_paid'];
        } else {
            $isPaid = $price > 0;
        }

        $mapped = [
            'id' => $data['id'] ?? '',
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'version' => $data['version'] ?? '1.0.0',
            'price' => $price,
            'currency' => $data['currency'] ?? 'USD',
            'isPaid' => $isPaid,
            'isPurchased' => (bool)($data['is_purchased'] ?? false),
            'isSoon' => (bool)($data['is_soon'] ?? false),
            'category' => $data['category'] ?? $this->determineCategory($data['name'] ?? '', $data['description'] ?? ''),
            'author' => $data['author'] ?? 'Green Meteor',
            'screenshots' => isset($data['image']) ? [$data['image']] : ($data['screenshots'] ?? []),
            'features' => $this->extractFeatures($data['description'] ?? ''),
            'requirements' => $data['requirements'] ?? ['HumHub 1.18+', 'PHP 8.2+'],
            'downloadUrl' => ($data['is_purchased'] ?? false) ? "https://greenmeteor.net/download?module={$data['id']}" : ($data['download_url'] ?? null),
        ];
        
        // Debug: Log mapped data
        Yii::info('Mapped module: ' . json_encode($mapped), 'bazaar');
        
        return $mapped;
    }

    private function scrapeModulesFromPage(): array
    {
        try {
            $response = $this->_client->get('', ['action' => 'list'])->send();
            if (!$response->isOk) {
                return [];
            }
            $html = $response->content;
            return $this->parseModuleCardsFromHtml($html);

        } catch (\Exception $e) {
            Yii::error('Scraping error: ' . $e->getMessage(), 'bazaar');
            return [];
        }
    }

    private function parseModuleCardsFromHtml(string $html): array
    {
        $modules = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//div[@class="module-card"]');

        foreach ($cards as $card) {
            try {
                $id = $card->getAttribute('data-module-id');
                $titleEl = $xpath->query('.//h3[@class="module-title"]', $card)->item(0);
                $descEl = $xpath->query('.//p[@class="module-description"]', $card)->item(0);
                $priceEl = $xpath->query('.//span[@class="module-price"]', $card)->item(0);
                $imgEl = $xpath->query('.//img', $card)->item(0);

                if (!$titleEl || !$descEl || !$priceEl) continue;

                $name = trim($titleEl->textContent);
                $desc = trim($descEl->textContent);
                $priceText = trim($priceEl->textContent);
                $image = $imgEl ? $imgEl->getAttribute('src') : '';

                // Improved price parsing
                $price = 0;
                if (preg_match('/\$([0-9,]+\.?[0-9]*)/', $priceText, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                } elseif (preg_match('/([0-9,]+\.?[0-9]*)/', $priceText, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                }

                $isSoon = stripos($name, 'coming soon') !== false;
                $isPaid = $price > 0;

                $modules[] = [
                    'id' => $id ?: uniqid(),
                    'name' => $name,
                    'description' => $desc,
                    'version' => '1.0.0',
                    'price' => $price,
                    'currency' => 'USD',
                    'is_paid' => $isPaid,
                    'isPaid' => $isPaid,
                    'is_purchased' => false,
                    'isPurchased' => false,
                    'is_soon' => $isSoon,
                    'isSoon' => $isSoon,
                    'category' => $this->determineCategory($name, $desc),
                    'author' => 'Green Meteor',
                    'screenshots' => $image ? [$image] : [],
                    'features' => $this->extractFeatures($desc),
                    'requirements' => ['HumHub 1.18+', 'PHP 8.2+'],
                    'downloadUrl' => $isPaid ? null : "#",
                ];
                
                // Debug scraped module
                Yii::info('Scraped module: ' . $name . ' - Price: ' . $price . ' - isPaid: ' . ($isPaid ? 'true' : 'false'), 'bazaar');
                
            } catch (\Exception $e) {
                Yii::error('Error parsing module card: ' . $e->getMessage(), 'bazaar');
            }
        }
        return $modules;
    }

    private function determineCategory(string $name, string $description): string
    {
        $text = strtolower($name . ' ' . $description);
        if (strpos($text, 'calendar') !== false || strpos($text, 'event') !== false) return 'productivity';
        if (strpos($text, 'poll') !== false || strpos($text, 'survey') !== false) return 'social';
        if (strpos($text, 'message') !== false || strpos($text, 'mail') !== false) return 'communication';
        if (strpos($text, 'wiki') !== false || strpos($text, 'docs') !== false) return 'content';
        if (strpos($text, 'shop') !== false || strpos($text, 'store') !== false) return 'integration';
        return 'other';
    }

    private function extractFeatures(string $description): array
    {
        return [
            'Professional HumHub module',
            'Full documentation included',
            'Regular updates',
            'Support included'
        ];
    }

    private function getCurrentUserSession(): string
    {
        $user = Yii::$app->user->identity;

        return $user ? $user->email : session_id();
    }
}