<?php

use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Badge;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\helpers\Html;
use yii\helpers\Url;
use humhub\modules\bazaar\assets\BazaarAsset;

BazaarAsset::register($this);

/* @var $this \humhub\components\View */
/* @var $modules \humhub\modules\bazaar\models\Module[] */
/* @var $categories array */

$currentSearch = Yii::$app->request->get('search', '');
$currentCategory = Yii::$app->request->get('category', '');
$currentSort = Yii::$app->request->get('sort', '');
$totalModules = count($modules);
$ownedCount = count(array_filter($modules, fn($m) => $m->isPurchased));
?>

<div class="panel panel-default">

    <div class="panel-heading">
        <div class="bzr-c-heading">

            <span class="bzr-c-heading-label">
                <?= Yii::t('BazaarModule.base', '<strong>Module</strong> Bazaar') ?>
            </span>

            <form id="bazaar-filter-form"
                  method="get"
                  action="<?= Url::to(['/bazaar/admin/index']) ?>"
                  class="bzr-c-heading-search">
                <div class="input-group input-group-sm">
                    <?= Html::textInput('search', $currentSearch, [
                        'id' => 'bazaar-search',
                        'class' => 'form-control',
                        'placeholder' => Yii::t('BazaarModule.base', 'Search…'),
                        'autocomplete' => 'off',
                    ]) ?>
                    <button class="btn btn-outline-secondary" type="submit">
                        <?= Icon::get('search') ?>
                    </button>
                </div>
            </form>

            <div class="bzr-c-heading-actions">
                <?= Button::secondary(Yii::t('BazaarModule.base', 'Clear Cache'))
                    ->options([
                        'data-action' => 'clearCache',
                        'data-action-url' => Url::to(['/bazaar/admin/clear-cache']),
                    ])
                    ->icon('refresh')->sm() ?>
                <?= Button::primary(Yii::t('BazaarModule.base', 'Settings'))
                    ->link(['/bazaar/admin/config'])
                    ->icon('cog')->sm() ?>
            </div>

        </div>
    </div>

    <div class="bzr-c-pillstrip">

        <button type="button"
                class="bzr-c-pill <?= $currentCategory === '' ? 'active' : '' ?>"
                data-bzr-cat="">
            <?= Yii::t('BazaarModule.base', 'All') ?>
            <span class="bzr-c-pill-count"><?= $totalModules ?></span>
        </button>

        <button type="button"
                class="bzr-c-pill <?= $currentCategory === 'purchased' ? 'active' : '' ?>"
                data-bzr-cat="purchased">
            <?= Yii::t('BazaarModule.base', 'Purchased') ?>
            <?php if ($ownedCount > 0): ?>
                <span class="bzr-c-pill-count"><?= $ownedCount ?></span>
            <?php endif; ?>
        </button>

        <?php if (!empty($categories)): ?>
            <div class="bzr-c-pill-sep"></div>
            <?php foreach ($categories as $key => $label): ?>
                <button type="button"
                        class="bzr-c-pill <?= $currentCategory === $key ? 'active' : '' ?>"
                        data-bzr-cat="<?= Html::encode($key) ?>">
                    <?= Html::encode($label) ?>
                </button>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="bzr-c-sort-wrap">
            <?= Html::dropDownList(
                'category',
                $currentCategory,
                array_merge([
                    '' => Yii::t('BazaarModule.base', 'All Categories'),
                    'purchased' => Yii::t('BazaarModule.base', 'Purchased'),
                ], $categories),
                ['id' => 'bazaar-category', 'class' => 'form-select']
            ) ?>

            <?= Html::dropDownList(
                'sort',
                $currentSort,
                [
                    '' => Yii::t('BazaarModule.base', 'Default'),
                    'name' => Yii::t('BazaarModule.base', 'Name A–Z'),
                    'price' => Yii::t('BazaarModule.base', 'Price'),
                    'category' => Yii::t('BazaarModule.base', 'Category'),
                ],
                ['id' => 'bazaar-sort', 'class' => 'form-select']
            ) ?>
        </div>

    </div>

    <div class="panel-body bzr-c-body">

        <?php if (empty($modules)): ?>

            <div class="bzr-c-empty">
                <span class="bzr-c-empty-icon"><?= Icon::get('shopping-cart') ?></span>
                <h5><?= Yii::t('BazaarModule.base', 'No modules available') ?></h5>
                <p class="mb-3" style="font-size:.825rem;">
                    <?= Yii::t('BazaarModule.base', 'Check your API configuration or try again later.') ?>
                </p>
                <?= Button::primary(Yii::t('BazaarModule.base', 'Open Settings'))
                    ->link(['/bazaar/admin/config'])
                    ->icon('cog')->sm() ?>
            </div>

        <?php else: ?>

            <div class="no-results text-center py-4 d-none">
                <p class="mb-1" style="font-size:.875rem;font-weight:600;color:var(--bs-emphasis-color);">
                    <?= Yii::t('BazaarModule.base', 'No modules match your search.') ?>
                </p>
                <small class="text-body-secondary">
                    <?= Yii::t('BazaarModule.base', 'Try different keywords or clear the filters.') ?>
                </small>
            </div>

            <div class="row g-2 modules-container">
                <?php foreach ($modules as $module): ?>
                    <div class="col-12 col-xl-6">
                        <div class="card module-card"
                             data-category="<?= Html::encode($module->category) ?>"
                             data-price="<?= (float)$module->price ?>"
                             data-purchased="<?= $module->isPurchased ? 1 : 0 ?>">

                            <div class="bzr-c-card-inner">

                                <div class="bzr-c-card-thumb">
                                    <?php if (!empty($module->screenshots)): ?>
                                        <?= Html::img($module->screenshots[0], [
                                            'alt' => Html::encode($module->name),
                                        ]) ?>
                                    <?php else: ?>
                                        <?= Icon::get('puzzle-piece') ?>
                                    <?php endif; ?>
                                </div>

                                <div class="bzr-c-card-content">
                                    <div class="bzr-c-card-row1">
                                        <h6 class="bzr-c-card-name"><?= Html::encode($module->name) ?></h6>
                                        <div class="bzr-c-card-badges">
                                            <?= Badge::secondary($module->getCategoryLabel())->sm() ?>
                                            <?php if ($module->isSoon): ?>
                                                <?= Badge::warning(Yii::t('BazaarModule.base', 'Soon'))->sm() ?>
                                            <?php elseif ($module->isPurchased): ?>
                                                <?= Badge::success(Yii::t('BazaarModule.base', 'Owned'))->sm() ?>
                                            <?php elseif (!$module->isPaid): ?>
                                                <?= Badge::info(Yii::t('BazaarModule.base', 'Free'))->sm() ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="bzr-c-card-meta">
                                        <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                                        · v<?= Html::encode($module->version) ?>
                                        <?php if (!empty($module->features)): ?>
                                            · <?= Html::encode(implode(', ', array_slice($module->features, 0, 2))) ?>
                                            <?php if (count($module->features) > 2): ?>…<?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bzr-c-card-desc">
                                        <?= Html::encode(\humhub\libs\StringHelper::truncate($module->description, 100)) ?>
                                    </div>
                                </div>

                                <div class="bzr-c-card-actions">
                                    <div>
                                        <?php if ($module->isSoon): ?>
                                            <small class="text-body-secondary fw-semibold">
                                                <?= Yii::t('BazaarModule.base', 'Coming Soon') ?>
                                            </small>
                                        <?php elseif ($module->isPurchased): ?>
                                            <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased'))->sm() ?>
                                        <?php elseif ($module->isPaid): ?>
                                            <span class="bzr-c-card-price">
                                                <?= Html::encode($module->getFormattedPrice()) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= Badge::info(Yii::t('BazaarModule.base', 'Free'))->sm() ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <?= Button::secondary(Yii::t('BazaarModule.base', 'Details'))
                                            ->link(['/bazaar/admin/view', 'id' => $module->id])
                                            ->sm() ?>
                                        <?php if ($module->isSoon): ?>
                                        <?php elseif ($module->isPurchased): ?>
                                            <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                                ->link(['/bazaar/admin/install', 'id' => $module->id])
                                                ->icon('download')->sm() ?>
                                        <?php elseif ($module->isPaid): ?>
                                            <?= Button::primary(Yii::t('BazaarModule.base', 'Buy'))
                                                ->link(['/bazaar/admin/purchase', 'id' => $module->id])
                                                ->icon('shopping-cart')->sm() ?>
                                        <?php else: ?>
                                            <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                                ->link(['/bazaar/admin/install', 'id' => $module->id])
                                                ->icon('download')->sm() ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</div>