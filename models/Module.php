<?php

namespace humhub\modules\bazaar\models;

use Yii;
use yii\base\Model;

class Module extends Model
{
    public $id;
    public $name;
    public $description;
    public $version;
    public $price;
    public $productId;
    public $priceId;
    public $currency;
    public $category;
    public $author;
    public $screenshots = [];
    public $features = [];
    public $requirements = [];
    public $downloadUrl;
    public $isPurchased = false;
    public $isPaid = false;
    public $isSoon = false;

    public function rules()
    {
        return [
            [['id', 'name'], 'required'],
            [['id', 'name', 'description', 'version', 'category', 'author', 'downloadUrl'], 'string'],
            [['price'], 'number'],
            [['currency', 'productId', 'priceId'], 'string'],
            [['screenshots', 'features', 'requirements'], 'each', 'rule' => ['string']],
            [['isPurchased', 'isPaid', 'isSoon'], 'boolean'],
        ];
    }

    public function getFormattedPrice()
    {
        if ((float)$this->price === 0.0) {
            return Yii::t('BazaarModule.base', 'Free');
        }

        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    /**
     * Category translation map
     */
    protected static function categoryMap()
    {
        return [
            'communication' => Yii::t('BazaarModule.base', 'Communication'),
            'content' => Yii::t('BazaarModule.base', 'Content'),
            'social' => Yii::t('BazaarModule.base', 'Social'),
            'productivity' => Yii::t('BazaarModule.base', 'Productivity'),
            'integration' => Yii::t('BazaarModule.base', 'Integration'),
            'other' => Yii::t('BazaarModule.base', 'Other'),
        ];
    }

    public function getCategoryLabel()
    {
        $map = self::categoryMap();

        return $map[$this->category]
            ?? Yii::t('BazaarModule.base', ucfirst(str_replace(['-', '_'], ' ', $this->category)));
    }

    /**
     * Dummy block for message extractor
     * Ensures categories are always detected
     */
    protected static function registerCategoryMessages()
    {
        if (false) {
            Yii::t('BazaarModule.base', 'Communication');
            Yii::t('BazaarModule.base', 'Content');
            Yii::t('BazaarModule.base', 'Social');
            Yii::t('BazaarModule.base', 'Productivity');
            Yii::t('BazaarModule.base', 'Integration');
            Yii::t('BazaarModule.base', 'Other');
        }
    }
}