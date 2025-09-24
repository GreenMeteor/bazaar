<?php

use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Badge;
use humhub\widgets\modal\Modal;
use humhub\modules\ui\icon\widgets\Icon;
use yii\helpers\Html;

/* @var $this \humhub\components\View */
/* @var $module array */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Module</strong> Details') ?>

        <div class="float-end">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back to Bazaar'))
                ->link(['/bazaar/admin/index'])
                ->icon('arrow-left')->sm() ?>
        </div>
    </div>

    <div class="panel-body">
        <div class="row">
            <!-- Module Images -->
            <div class="col-md-5">
                <?php if (!empty($module['screenshots'])): ?>
                    <div id="moduleCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($module['screenshots'] as $index => $screenshot): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <?= Html::img($screenshot, [
                                        'class' => 'd-block w-100 rounded',
                                        'alt' => 'Screenshot ' . ($index + 1),
                                        'style' => 'height: 300px; object-fit: cover;'
                                    ]) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($module['screenshots']) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#moduleCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                                <span class="visually-hidden"><?= Yii::t('BazaarModule.base', 'Previous') ?></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#moduleCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                                <span class="visually-hidden"><?= Yii::t('BazaarModule.base', 'Next') ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="placeholder-image d-flex align-items-center justify-content-center bg-light rounded mb-4" 
                         style="height: 300px;">
                        <?= Icon::get('puzzle-piece') ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Module Information -->
            <div class="col-md-7">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="mb-1"><?= Html::encode($module['name']) ?></h2>
                        <p class="text-body-secondary mb-2">
                            <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module['author'])]) ?>
                            • v<?= Html::encode($module['version']) ?>
                        </p>
                    </div>
                    <?= Badge::secondary($module['category'] ?? 'other')->lg() ?>
                </div>

                <!-- Price and Purchase -->
                <div class="mb-4">
                    <?php if ($module['is_purchased'] ?? false): ?>
                        <div class="d-flex align-items-center mb-3">
                            <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased'))->lg() ?>
                            <?= Button::primary(Yii::t('BazaarModule.base', 'Download'))
                                ->link($module['download_url'] ?? '#')
                                ->icon('download') ?>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="price-display">
                                <?php if (($module['price'] ?? 0) > 0): ?>
                                    <h3 class="text-primary mb-0">
                                        <?= number_format($module['price'], 2) ?> <?= $module['currency'] ?? 'USD' ?>
                                    </h3>
                                <?php else: ?>
                                    <h3 class="text-success mb-0"><?= Yii::t('BazaarModule.base', 'Free') ?></h3>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if (($module['price'] ?? 0) > 0): ?>
                                    <?= Button::primary(Yii::t('BazaarModule.base', 'Purchase'))
                                        ->link(['/bazaar/admin/purchase', 'id' => $module['id']])
                                        ->icon('shopping-cart') ?>
                                <?php else: ?>
                                    <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                        ->link($module['download_url'] ?? '#')
                                        ->icon('download') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <h5><?= Yii::t('BazaarModule.base', 'Description') ?></h5>
                    <p><?= Html::encode($module['description'] ?? '') ?></p>
                </div>

                <!-- Features -->
                <?php if (!empty($module['features'])): ?>
                    <div class="mb-4">
                        <h5><?= Yii::t('BazaarModule.base', 'Features') ?></h5>
                        <ul class="list-unstyled">
                            <?php foreach ($module['features'] as $feature): ?>
                                <li class="mb-2">
                                    <?= Icon::get('check-circle') ?>
                                    <?= Html::encode($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Requirements -->
                <?php if (!empty($module['requirements'])): ?>
                    <div class="mb-4">
                        <h5><?= Yii::t('BazaarModule.base', 'Requirements') ?></h5>
                        <ul class="list-unstyled">
                            <?php foreach ($module['requirements'] as $requirement): ?>
                                <li class="mb-1">
                                    <?= Icon::get('info-circle') ?>
                                    <?= Html::encode($requirement) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>