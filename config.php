<?php

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\bazaar\Events;
use humhub\modules\bazaar\Module;

return [
    'id'        => 'bazaar',
    'class'     => Module::class,
    'namespace' => 'humhub\modules\bazaar',
    'controllerMap' => [
        'admin' => 'humhub\modules\bazaar\controllers\AdminController',
    ],

    'events' => [
        [AdminMenu::class, AdminMenu::EVENT_INIT, [Events::class, 'onAdminMenuInit']],
    ],
];