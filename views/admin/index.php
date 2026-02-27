<?php

use humhub\widgets\form\ActiveForm;
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

$currentSearch = Yii::$app->request->get('search',   '');
$currentCategory = Yii::$app->request->get('category', '');
$currentSort = Yii::$app->request->get('sort',     '');
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Module</strong> Bazaar') ?>

        <div class="float-end">
            <?= Button::primary(Yii::t('BazaarModule.base', 'Configure'))
                ->link(['/bazaar/admin/config'])
                ->icon('cog')->sm() ?>

            <?= Button::secondary(Yii::t('BazaarModule.base', 'Clear Cache'))
                ->options([
                    'data-action' => 'clearCache',
                    'data-action-url' => Url::to(['/bazaar/admin/clear-cache']),
                ])
                ->icon('refresh')->sm() ?>
        </div>
    </div>

    <div class="panel-body">

        <form method="get" action="<?= Url::to(['/bazaar/admin/index']) ?>" id="bazaar-filter-form">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <?= Html::textInput('search', $currentSearch, [
                            'id' => 'module-search',
                            'class' => 'form-control',
                            'placeholder' => Yii::t('BazaarModule.base', 'Search modules…'),
                        ]) ?>
                        <button class="btn btn-outline-secondary" type="submit">
                            <?= Icon::get('search') ?>
                        </button>
                    </div>
                </div>

                <div class="col-md-3">
                    <?= Html::dropDownList(
                        'category',
                        $currentCategory,
                        array_merge(['' => Yii::t('BazaarModule.base', 'All Categories')], $categories),
                        ['class' => 'form-select filter-auto-submit']
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= Html::dropDownList(
                        'sort',
                        $currentSort,
                        [
                            '' => Yii::t('BazaarModule.base', 'Default order'),
                            'name' => Yii::t('BazaarModule.base', 'Name'),
                            'price' => Yii::t('BazaarModule.base', 'Price'),
                            'category' => Yii::t('BazaarModule.base', 'Category'),
                        ],
                        ['class' => 'form-select filter-auto-submit']
                    ) ?>
                </div>
            </div>
        </form>

        <?php if (empty($modules)): ?>
            <div class="text-center py-5">
                <?= Icon::get('shopping-cart') ?>
                <h4 class="mt-3"><?= Yii::t('BazaarModule.base', 'No modules available') ?></h4>
                <p class="text-body-secondary">
                    <?= Yii::t('BazaarModule.base', 'Check your API configuration or try again later.') ?>
                </p>
            </div>

        <?php else: ?>

            <div class="row g-4 modules-container">
                <?php foreach ($modules as $module): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 module-card position-relative"
                             data-category="<?= Html::encode($module->category) ?>">

                            <?php if (!empty($module->screenshots)): ?>
                                <?= Html::img($module->screenshots[0], [
                                    'class' => 'card-img-top',
                                    'alt'   => Html::encode($module->name),
                                    'style' => 'height:200px;object-fit:cover;',
                                ]) ?>
                            <?php else: ?>
                                <div class="placeholder-image d-flex align-items-center
                                            justify-content-center bg-light"
                                     style="height:200px;">
                                    <?= Icon::get('puzzle-piece') ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($module->isPaid && $module->price > 0 && !$module->isPurchased): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-primary">
                                        <?= Html::encode($module->getFormattedPrice()) ?>
                                    </span>
                                </div>
                            <?php elseif (!$module->isPaid): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-success">
                                        <?= Yii::t('BazaarModule.base', 'Free') ?>
                                    </span>
                                </div>
                            <?php elseif ($module->isPurchased): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-success">
                                        <?= Yii::t('BazaarModule.base', 'Purchased') ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">

                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-1"><?= Html::encode($module->name) ?></h5>
                                    <?= Badge::secondary($module->getCategoryLabel())->sm() ?>
                                </div>

                                <p class="card-text text-body-secondary small mb-2">
                                    <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                                    • v<?= Html::encode($module->version) ?>
                                </p>

                                <p class="card-text flex-grow-1">
                                    <?= Html::encode(
                                        \yii\helpers\StringHelper::truncate($module->description, 100)
                                    ) ?>
                                </p>

                                <?php if (!empty($module->features)): ?>
                                    <div class="mb-3">
                                        <small class="text-body-secondary">
                                            <?= Yii::t('BazaarModule.base', 'Features:') ?>
                                            <?= Html::encode(
                                                implode(', ', array_slice($module->features, 0, 2))
                                            ) ?>
                                            <?php if (count($module->features) > 2): ?>…<?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-auto">

                                    <div class="price" data-price="<?= (float)$module->price ?>">
                                        <?php if ($module->isSoon): ?>
                                            <?= Badge::warning(Yii::t('BazaarModule.base', 'Coming Soon')) ?>
                                        <?php elseif ($module->isPurchased): ?>
                                            <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased')) ?>
                                        <?php elseif ($module->isPaid): ?>
                                            <strong class="text-primary">
                                                <?= Html::encode($module->getFormattedPrice()) ?>
                                            </strong>
                                        <?php else: ?>
                                            <?= Badge::info(Yii::t('BazaarModule.base', 'Free')) ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="btn-group btn-group-sm">
                                        <?= Button::info(Yii::t('BazaarModule.base', 'Details'))
                                            ->link(['/bazaar/admin/view', 'id' => $module->id])
                                            ->sm() ?>

                                        <?php if ($module->isSoon): ?>

                                        <?php elseif ($module->isPurchased): ?>
                                            <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                                ->link(['/bazaar/admin/install', 'id' => $module->id])
                                                ->icon('download')
                                                ->sm() ?>

                                        <?php elseif ($module->isPaid): ?>
                                            <?= Button::primary(Yii::t('BazaarModule.base', 'Buy'))
                                                ->link(['/bazaar/admin/purchase', 'id' => $module->id])
                                                ->icon('shopping-cart')
                                                ->sm() ?>

                                        <?php else: ?>
                                            <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                                ->link(['/bazaar/admin/install', 'id' => $module->id])
                                                ->icon('download')
                                                ->sm() ?>
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

<script <?= Html::nonce() ?>>
    (function () {
        'use strict';

        document.querySelectorAll('.filter-auto-submit').forEach(function (el) {
            el.addEventListener('change', function () {
                document.getElementById('bazaar-filter-form').submit();
            });
        });
    }());
</script>