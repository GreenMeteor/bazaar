<?php

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\bazaar\Events;

return [
    'id' => 'bazaar',
    'class' => 'humhub\modules\bazaar\Module',
    'namespace' => 'humhub\modules\bazaar',
    'events' => [
        [AdminMenu::class, AdminMenu::EVENT_INIT, [Events::class, 'onAdminMenuInit']],
    ],
];