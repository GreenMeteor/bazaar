<?php

namespace humhub\modules\bazaar\services;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;
use yii\httpclient\Client;

/**
 * Class ApiService
 *
 * Handles communication with the Green Meteor Bazaar API
 * and provides caching, scraping fallback, and module purchase functionality.
 *
 * @package humhub\modules\bazaar\services
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
        if ($module) {
            $this->apiKey = $module->apiKey ?? '';
        }

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
        $cacheTimeout = $module->cacheTimeout ?? 3600;

        return Yii::$app->cache->getOrSet(
            $cacheKey,
            function () {
                try {
                    $response = $this->_client->get('', [
                        'action' => 'list',
                        'format' => 'json',
                    ])->send();

                    if ($response->isOk && isset($response->data['success']) && $response->data['success']) {
                        return $this->transformApiData($response->data['data']);
                    }

                    return $this->scrapeModulesFromPage();
                } catch (\Exception $e) {
                    Yii::error('Green Meteor API error: ' . $e->getMessage(), 'bazaar');
                    return $this->scrapeModulesFromPage();
                }
            },
            $cacheTimeout,
            new TagDependency(['tags' => ['bazaar_modules']])
        );
    }

    public function getModule($moduleId): ?array
    {
        // FIXED: Ensure moduleId is properly cast for comparison
        $moduleId = (string)$moduleId; // Convert to string for consistent comparison
        
        try {
            $response = $this->_client->get('', [
                'action' => 'get',
                'module_id' => $moduleId,
                'format' => 'json',
            ])->send();

            if ($response->isOk && isset($response->data['success']) && $response->data['success']) {
                return $this->transformSingleModule($response->data['data']);
            }

        } catch (\Exception $e) {
            Yii::error('Green Meteor API error fetching module: ' . $e->getMessage(), 'bazaar');
        }

        // Fallback: search in cached modules list
        $modules = $this->getModules();
        foreach ($modules as $module) {
            // FIXED: Handle both string and numeric ID comparisons
            if ((string)($module['id'] ?? '') === $moduleId) {
                return $module;
            }
        }

        return null;
    }

    public function purchaseModule(int $moduleId, array $options = []): array
    {
        $postData = [
            'action' => 'purchase',
            'module_id' => $moduleId,
        ];

        if (!empty($options['return_url'])) {
            $postData['return_url'] = $options['return_url'];
        }

        if (!empty($options['cancel_url'])) {
            $postData['cancel_url'] = $options['cancel_url'];
        }

        $response = $this->_client->post('', [], json_encode($postData))
            ->setHeaders(['Content-Type' => 'application/json'])
            ->send();

        if (!$response->isOk) {
            throw new Exception("Purchase failed for module {$moduleId}: HTTP {$response->statusCode}");
        }

        $data = $response->getData();

        if (isset($data['error'])) {
            throw new Exception("Purchase failed for module {$moduleId}: " . $data['error']);
        }

        if (!isset($data['success']) || !$data['success']) {
            throw new Exception("Purchase failed for module {$moduleId}: Unknown error");
        }

        return $data;
    }

    public function verifyPurchase(string $sessionId): array
    {
        try {
            $response = $this->_client->get('verify-purchase.php', [
                'session_id' => $sessionId,
            ])->send();

            if (!$response->isOk) {
                throw new \Exception("Verification failed: HTTP {$response->statusCode}");
            }

            $data = $response->getData();

            if (isset($data['error'])) {
                throw new \Exception("Verification failed: " . $data['error']);
            }

            return [
                'verified' => $data['verified'] ?? false,
                'module_id' => $data['module_id'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            Yii::error('Purchase verification error: ' . $e->getMessage(), 'bazaar');
            return [
                'verified' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function clearCache(): void
    {
        TagDependency::invalidate(Yii::$app->cache, ['bazaar_modules']);
    }

    private function transformApiData(array $rawData): array
    {
        $modules = [];
        foreach ($rawData as $item) {
            $modules[] = $this->transformSingleModule($item);
        }
        return $modules;
    }

    private function transformSingleModule(array $item): array
    {
        // DEBUG: Log raw API data
        Yii::info('Raw module data: ' . json_encode($item), 'bazaar-debug');
        
        // FIXED: Ensure price is properly handled as float
        $price = 0.0;
        if (isset($item['price'])) {
            if (is_numeric($item['price'])) {
                $price = (float)$item['price'];
            } elseif (is_string($item['price'])) {
                // Handle string prices like "$19.99" or "19.99"
                $cleanPrice = preg_replace('/[^\d.]/', '', $item['price']);
                $price = $cleanPrice ? (float)$cleanPrice : 0.0;
            }
        }

        // CRITICAL FIX: Properly determine isPaid - API flag takes precedence
        $isPaid = false;
        if (isset($item['is_paid'])) {
            $isPaid = (bool)$item['is_paid'];
        } else {
            // Only use price as fallback if no explicit is_paid flag
            $isPaid = $price > 0;
        }
        
        // DEBUG: Log transformation
        Yii::info("Module {$item['id']}: price={$price}, is_paid_flag=" . ($item['is_paid'] ?? 'null') . ", calculated_isPaid={$isPaid}", 'bazaar-debug');

        // FIXED: Handle screenshots properly
        $screenshots = [];
        if (!empty($item['screenshots']) && is_array($item['screenshots'])) {
            $screenshots = $item['screenshots'];
        } elseif (!empty($item['image'])) {
            $screenshots = [$item['image']];
        }

        return [
            'id' => $item['id'] ?? '',
            'name' => $item['name'] ?? '',
            'description' => $item['description'] ?? '',
            'version' => $item['version'] ?? '1.0.0',
            'price' => $price,
            'currency' => $item['currency'] ?? 'USD',
            'category' => $item['category'] ?? 'other',
            'author' => $item['author'] ?? 'Green Meteor',
            'screenshots' => $screenshots,
            'features' => is_array($item['features'] ?? null) ? $item['features'] : $this->extractFeatures($item['description'] ?? ''),
            'requirements' => $item['requirements'] ?? ['HumHub 1.18+', 'PHP 8.2+'],
            'downloadUrl' => $item['download_url'] ?? null,
            'isPurchased' => (bool)($item['is_purchased'] ?? false),
            'isPaid' => $isPaid,
            'isSoon' => (bool)($item['is_soon'] ?? false),
            'productId' => $item['product_id'] ?? null,
            'priceId' => $item['price_id'] ?? null,
        ];
    }

    private function scrapeModulesFromPage(): array
    {
        try {
            $response = $this->_client->get('', ['action' => 'list'])->send();
            if (!$response->isOk) {
                return [];
            }

            return $this->parseModuleCardsFromHtml($response->content);
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
                $id = $card->getAttribute('data-module-id') ?: uniqid();
                $titleEl = $xpath->query('.//h3[@class="module-title"]', $card)->item(0);
                $descEl = $xpath->query('.//p[@class="module-description"]', $card)->item(0);
                $priceEl = $xpath->query('.//span[@class="module-price"]', $card)->item(0);
                $imgEl = $xpath->query('.//img', $card)->item(0);

                if (!$titleEl || !$descEl || !$priceEl) {
                    continue;
                }

                $name = trim($titleEl->textContent);
                $desc = trim($descEl->textContent);
                $priceText = trim($priceEl->textContent);
                $image = $imgEl ? $imgEl->getAttribute('src') : '';

                // FIXED: Better price parsing from scraped content
                $price = 0.0;
                if (preg_match('/\$?([0-9,]+\.?[0-9]*)/', $priceText, $matches)) {
                    $price = (float)str_replace(',', '', $matches[1]);
                }

                $modules[] = [
                    'id' => $id,
                    'name' => $name,
                    'description' => $desc,
                    'version' => '1.0.0',
                    'price' => $price,
                    'currency' => 'USD',
                    'category' => $this->determineCategory($name, $desc),
                    'author' => 'Green Meteor',
                    'screenshots' => $image ? [$image] : [],
                    'features' => $this->extractFeatures($desc),
                    'requirements' => ['HumHub 1.18+', 'PHP 8.2+'],
                    'downloadUrl' => $price > 0 ? null : "https://greenmeteor.net/download?module={$id}",
                    'isPurchased' => false,
                    'isPaid' => $price > 0,
                    'isSoon' => false,
                ];
            } catch (\Exception $e) {
                Yii::error('Error parsing module card: ' . $e->getMessage(), 'bazaar');
            }
        }

        return $modules;
    }

    private function determineCategory(string $name, string $description): string
    {
        $text = strtolower($name . ' ' . $description);
        if (strpos($text, 'calendar') !== false || strpos($text, 'event') !== false || strpos($text, 'schedule') !== false) {
            return 'productivity';
        }
        if (strpos($text, 'poll') !== false || strpos($text, 'survey') !== false || strpos($text, 'vote') !== false) {
            return 'social';
        }
        if (strpos($text, 'message') !== false || strpos($text, 'mail') !== false || strpos($text, 'chat') !== false) {
            return 'communication';
        }
        if (strpos($text, 'wiki') !== false || strpos($text, 'docs') !== false || strpos($text, 'document') !== false) {
            return 'content';
        }
        if (strpos($text, 'shop') !== false || strpos($text, 'store') !== false || strpos($text, 'commerce') !== false) {
            return 'integration';
        }
        return 'other';
    }

    private function extractFeatures(string $description): array
    {
        return [
            'Professional HumHub module',
            'Full documentation included',
            'Regular updates and support',
            'Easy installation'
        ];
    }
}