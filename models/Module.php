<?php

namespace humhub\modules\bazaar\models;

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

    /**
     * @inheritdoc
     */
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

    /**
     * Get formatted price
     * @return string
     */
    public function getFormattedPrice()
    {
        if ($this->price == 0) {
            return \Yii::t('BazaarModule.base', 'Free');
        }

        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    /**
     * Get category label
     * @return string
     */
    public function getCategoryLabel()
    {
        $categories = [
            'communication' => \Yii::t('BazaarModule.base', 'Communication'),
            'content' => \Yii::t('BazaarModule.base', 'Content'),
            'social' => \Yii::t('BazaarModule.base', 'Social'),
            'productivity' => \Yii::t('BazaarModule.base', 'Productivity'),
            'integration' => \Yii::t('BazaarModule.base', 'Integration'),
            'other' => \Yii::t('BazaarModule.base', 'Other'),
        ];

        return $categories[$this->category] ?? $this->category;
    }
}