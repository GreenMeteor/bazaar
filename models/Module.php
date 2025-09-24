<?php

namespace humhub\modules\bazaar\models;

use yii\base\Model;

class Module extends Model
{
    public $id;
    public $name;
    public $description;
    public $version;
    public $price = 0.0;
    public $currency = 'USD';
    public $category = 'other';
    public $author = 'Unknown';
    public $screenshots = [];
    public $features = [];
    public $requirements = [];
    public $downloadUrl;
    public $isPurchased = false;
    public $isPaid = false;
    public $isSoon = false;
    public $productId;
    public $priceId;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'name'], 'required'],
            [['id', 'name', 'description', 'version', 'category', 'author', 'downloadUrl', 'productId', 'priceId'], 'string'],
            [['price'], 'number', 'min' => 0],
            [['currency'], 'string', 'max' => 3],
            [['screenshots', 'features', 'requirements'], 'each', 'rule' => ['string']],
            [['isPurchased', 'isPaid', 'isSoon'], 'boolean'],
        ];
    }

    /**
     * Create Module instance from API data array
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data)
    {
        // DEBUG: Log incoming data for troubleshooting
        \Yii::info('Creating module from data: ' . json_encode($data), 'bazaar-debug');
        
        // CRITICAL FIX: Robust price handling with explicit debugging
        $price = 0.0;
        if (isset($data['price'])) {
            if (is_numeric($data['price'])) {
                $price = (float)$data['price'];
            } elseif (is_string($data['price'])) {
                // Handle formatted prices like "$19.99" or "19.99 USD"
                $cleanPrice = preg_replace('/[^\d.]/', '', $data['price']);
                $price = $cleanPrice ? (float)$cleanPrice : 0.0;
            }
        }
        
        // CRITICAL FIX: Handle isPaid flag explicitly
        // The API uses 'is_paid' - we must respect this flag over price
        $isPaid = false;
        if (array_key_exists('is_paid', $data)) {
            $isPaid = (bool)$data['is_paid'];
        } elseif (array_key_exists('isPaid', $data)) {
            $isPaid = (bool)$data['isPaid'];
        } else {
            // Only fallback to price if no explicit flag is provided
            $isPaid = $price > 0;
        }
        
        // DEBUG: Log the decision process
        \Yii::info("Module {$data['id']}: price={$price}, is_paid_flag=" . 
                   json_encode($data['is_paid'] ?? 'null') . 
                   ", calculated_isPaid={$isPaid}", 'bazaar-debug');

        $module = new static();
        $module->setAttributes([
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? '1.0.0',
            'price' => $price,
            'currency' => $data['currency'] ?? 'USD',
            'category' => $data['category'] ?? 'other',
            'author' => $data['author'] ?? 'Unknown',
            'screenshots' => $data['screenshots'] ?? (isset($data['image']) ? [$data['image']] : []),
            'features' => $data['features'] ?? [],
            'requirements' => $data['requirements'] ?? [],
            'downloadUrl' => $data['downloadUrl'] ?? $data['download_url'] ?? null,

            // Handle both camelCase and snake_case
            'isPurchased' => (bool)($data['isPurchased'] ?? $data['is_purchased'] ?? false),
            'isPaid' => $isPaid,
            'isSoon' => (bool)($data['isSoon'] ?? $data['is_soon'] ?? false),
            'productId' => $data['productId'] ?? $data['product_id'] ?? null,
            'priceId' => $data['priceId'] ?? $data['price_id'] ?? null,
        ], false);

        return $module;
    }

    /**
     * Convert Module instance to array
     *
     * @param array $fields
     * @param array $expand
     * @param bool $recursive
     * @return array
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'price' => $this->price,
            'currency' => $this->currency,
            'category' => $this->category,
            'author' => $this->author,
            'screenshots' => $this->screenshots,
            'features' => $this->features,
            'requirements' => $this->requirements,
            'downloadUrl' => $this->downloadUrl,
            'isPurchased' => $this->isPurchased,
            'isPaid' => $this->isPaid,
            'isSoon' => $this->isSoon,
            'productId' => $this->productId,
            'priceId' => $this->priceId,
        ];
    }

    /**
     * Get formatted price string
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        if ($this->isSoon) {
            return \Yii::t('BazaarModule.base', 'Coming Soon');
        }

        if ($this->isPaid && $this->price > 0) {
            // FIXED: Use Yii's built-in currency formatter for consistency
            return \Yii::$app->formatter->asCurrency($this->price, $this->currency);
        }

        return \Yii::t('BazaarModule.base', 'Free');
    }

    /**
     * Get raw price for display (without currency symbol)
     *
     * @return string
     */
    public function getRawPrice(): string
    {
        if ($this->isSoon) {
            return \Yii::t('BazaarModule.base', 'Coming Soon');
        }

        if ($this->isPaid && $this->price > 0) {
            return number_format($this->price, 2);
        }

        return \Yii::t('BazaarModule.base', 'Free');
    }

    /**
     * Get category label
     *
     * @return string
     */
    public function getCategoryLabel(): string
    {
        $categories = [
            'communication' => \Yii::t('BazaarModule.base', 'Communication'),
            'content' => \Yii::t('BazaarModule.base', 'Content'),
            'social' => \Yii::t('BazaarModule.base', 'Social'),
            'productivity' => \Yii::t('BazaarModule.base', 'Productivity'),
            'integration' => \Yii::t('BazaarModule.base', 'Integration'),
            'other' => \Yii::t('BazaarModule.base', 'Other'),
        ];

        return $categories[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Check if module is available for purchase
     *
     * @return bool
     */
    public function isAvailableForPurchase(): bool
    {
        return $this->isPaid && !$this->isPurchased && !$this->isSoon && $this->price > 0;
    }

    /**
     * Check if module can be downloaded
     *
     * @return bool
     */
    public function isDownloadable(): bool
    {
        return (!$this->isPaid || $this->isPurchased) && !$this->isSoon && !empty($this->downloadUrl);
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        if ($this->isSoon) {
            return \Yii::t('BazaarModule.base', 'Coming Soon');
        }

        if (!$this->isPaid) {
            return \Yii::t('BazaarModule.base', 'Free');
        }

        if ($this->isPurchased) {
            return \Yii::t('BazaarModule.base', 'Purchased');
        }

        return \Yii::t('BazaarModule.base', 'Available for Purchase');
    }

    /**
     * Get price display class for styling
     *
     * @return string
     */
    public function getPriceDisplayClass(): string
    {
        if ($this->isSoon) {
            return 'text-warning';
        }

        if (!$this->isPaid) {
            return 'text-success';
        }

        if ($this->isPurchased) {
            return 'text-info';
        }

        return 'text-primary';
    }
}