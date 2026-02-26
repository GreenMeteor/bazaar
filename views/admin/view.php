<?php

use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Badge;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\helpers\Html;
use humhub\modules\bazaar\assets\BazaarAsset;

BazaarAsset::register($this);

/* @var $this \humhub\components\View */
/* @var $module \humhub\modules\bazaar\models\Module */
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

            <div class="col-md-5">
                <?php if (!empty($module->screenshots)): ?>
                    <div id="moduleCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($module->screenshots as $index => $screenshot): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <?= Html::img($screenshot, [
                                        'class' => 'd-block w-100 rounded',
                                        'alt'   => Html::encode(Yii::t('BazaarModule.base', 'Screenshot {n}', ['n' => $index + 1])),
                                        'style' => 'height:300px;object-fit:cover;',
                                    ]) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($module->screenshots) > 1): ?>
                            <button class="carousel-control-prev" type="button"
                                    data-bs-target="#moduleCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                                <span class="visually-hidden"><?= Yii::t('BazaarModule.base', 'Previous') ?></span>
                            </button>
                            <button class="carousel-control-next" type="button"
                                    data-bs-target="#moduleCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                                <span class="visually-hidden"><?= Yii::t('BazaarModule.base', 'Next') ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="placeholder-image d-flex align-items-center justify-content-center bg-light rounded mb-4"
                         style="height:300px;">
                        <?= Icon::get('puzzle-piece') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-7">

                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="mb-1"><?= Html::encode($module->name) ?></h2>
                        <p class="text-body-secondary mb-2">
                            <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                            • v<?= Html::encode($module->version) ?>
                        </p>
                    </div>
                    <?= Badge::secondary($module->getCategoryLabel())->lg() ?>
                </div>

                <div class="mb-3">
                    <?php if ($module->isSoon): ?>
                        <?= Badge::warning(Yii::t('BazaarModule.base', 'Coming Soon'))->lg() ?>
                    <?php elseif ($module->isPurchased): ?>
                        <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased'))->lg() ?>
                    <?php elseif ($module->isPaid): ?>
                        <?= Badge::primary(Yii::t('BazaarModule.base', 'Paid'))->lg() ?>
                    <?php else: ?>
                        <?= Badge::info(Yii::t('BazaarModule.base', 'Free'))->lg() ?>
                    <?php endif; ?>
                </div>

                <div class="mb-4 d-flex align-items-center justify-content-between">
                    <div class="price-display">
                        <?php if ($module->isPaid && !$module->isPurchased): ?>
                            <h3 class="text-primary mb-0"><?= Html::encode($module->getFormattedPrice()) ?></h3>
                        <?php elseif (!$module->isPaid): ?>
                            <h3 class="text-success mb-0"><?= Yii::t('BazaarModule.base', 'Free') ?></h3>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if ($module->isPurchased && $module->downloadUrl): ?>
                            <div class="btn-group" role="group">
                                <?= Button::primary(Yii::t('BazaarModule.base', 'Install'))
                                    ->link(['/bazaar/admin/install', 'id' => $module->id])
                                    ->loader(false)
                                    ->icon('cog')
                                    ->options(['data-confirm' => Yii::t('BazaarModule.base', 'This will download and install the module. Continue?')]) ?>
                                <?= Button::secondary(Yii::t('BazaarModule.base', 'Download Only'))
                                    ->link($module->downloadUrl)
                                    ->loader(false)
                                    ->icon('download')
                                    ->options(['target' => '_blank'])
                                    ->sm() ?>
                            </div>

                        <?php elseif ($module->isPaid && !$module->isSoon): ?>
                            <?= Button::primary(Yii::t('BazaarModule.base', 'Purchase'))
                                ->link(['/bazaar/admin/purchase', 'id' => $module->id])
                                ->loader(false)
                                ->icon('shopping-cart') ?>

                        <?php elseif (!$module->isPaid && $module->downloadUrl): ?>
                            <div class="btn-group" role="group">
                                <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                    ->link(['/bazaar/admin/install', 'id' => $module->id])
                                    ->icon('cog')
                                    ->options(['data-confirm' => Yii::t('BazaarModule.base', 'This will download and install the module. Continue?')]) ?>
                                <?= Button::secondary(Yii::t('BazaarModule.base', 'Download Only'))
                                    ->link($module->downloadUrl)
                                    ->icon('download')
                                    ->options(['target' => '_blank'])
                                    ->loader(false)
                                    ->sm() ?>
                            </div>

                        <?php else: ?>
                            <?= Button::secondary(Yii::t('BazaarModule.base', 'Not Available'))
                                ->icon('ban')
                                ->disabled() ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (($module->isPurchased && $module->downloadUrl) || (!$module->isPaid && $module->downloadUrl)): ?>
                    <div class="alert alert-info mb-4">
                        <h6><?= Icon::get('info-circle') ?><?= Yii::t('BazaarModule.base', 'Installation Instructions') ?></h6>
                        <ol class="mb-0 small">
                            <li><?= Yii::t('BazaarModule.base', 'Download the module zip file') ?></li>
                            <li><?= Yii::t('BazaarModule.base', 'Extract to your HumHub modules directory: /protected/modules/') ?></li>
                            <li><?= Yii::t('BazaarModule.base', 'Enable the module in Administration > Modules') ?></li>
                            <li><?= Yii::t('BazaarModule.base', 'Configure module settings as needed') ?></li>
                        </ol>
                    </div>
                <?php endif; ?>

                <?php if (!empty($module->description)): ?>
                    <div class="mb-4">
                        <h5><?= Yii::t('BazaarModule.base', 'Description') ?></h5>
                        <p><?= Html::encode($module->description) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($module->features)): ?>
                    <div class="mb-4">
                        <h5><?= Yii::t('BazaarModule.base', 'Features') ?></h5>
                        <ul class="list-unstyled">
                            <?php foreach ($module->features as $feature): ?>
                                <li class="mb-2">
                                    <?= Icon::get('check-circle') ?>
                                    <?= Html::encode($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($module->requirements)): ?>
                    <div class="mb-4">
                        <h5><?= Yii::t('BazaarModule.base', 'Requirements') ?></h5>
                        <ul class="list-unstyled">
                            <?php foreach ($module->requirements as $requirement): ?>
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