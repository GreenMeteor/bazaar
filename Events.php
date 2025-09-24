<?php

namespace humhub\modules\bazaar;

use Yii;
use yii\helpers\Url;
use yii\base\BaseObject;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\admin\permissions\ManageSettings;

class Events extends BaseObject
{
    /**
     * Add bazaar menu item to admin menu
     */
    public static function onAdminMenuInit($event)
    {
        if (!Yii::$app->user->can(ManageSettings::class)) {
            return;
        }

        /** @var AdminMenu $menu */
        $menu = $event->sender;

        $menu->addEntry(new MenuLink([
            'label' => Yii::t('BazaarModule.base', 'Module Bazaar'),
            'url' => Url::toRoute(['/bazaar/admin/index']),
            'icon' => Icon::get('shopping-cart'),
            'isActive' => Yii::$app->controller->module
                && Yii::$app->controller->module->id === 'bazaar'
                && Yii::$app->controller->id === 'admin',
            'sortOrder' => 500,
            'isVisible' => true,
        ]));
    }
}