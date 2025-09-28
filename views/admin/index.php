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
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Module</strong> Bazaar') ?>

        <div class="float-end">
            <?= Button::primary(Yii::t('BazaarModule.base', 'Configure'))
                ->link(['/bazaar/admin/config'])
                ->icon('cog')->sm() ?>
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Clear Cache'))
                ->link(['/bazaar/admin/clear-cache'])
                ->icon('refresh')->sm() ?>
        </div>
    </div>

    <div class="panel-body">
        <!-- DEBUG SECTION - Remove this after fixing -->
        <?php if (YII_DEBUG): ?>
        <div class="alert alert-info">
            <h5>Debug Information</h5>
            <p><strong>Total modules:</strong> <?= count($modules) ?></p>
            <p><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
            <p><strong>Current time:</strong> <?= date('Y-m-d H:i:s') ?></p>

            <h6>Sample Module Data:</h6>
            <?php if (!empty($modules)): ?>
                <?php $sampleModule = $modules[0]; ?>
                <pre><?= Html::encode(print_r([
                    'name' => $sampleModule->name,
                    'price' => $sampleModule->price,
                    'price_type' => gettype($sampleModule->price),
                    'isPaid' => $sampleModule->isPaid,
                    'isPaid_type' => gettype($sampleModule->isPaid),
                    'currency' => $sampleModule->currency,
                    'formatted_price' => method_exists($sampleModule, 'getFormattedPrice') ? $sampleModule->getFormattedPrice() : 'N/A'
                ], true)) ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- END DEBUG SECTION -->

        <!-- Filter Bar -->
        <div class="row mb-4">
            <div class="col-md-6">
                <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['index']]); ?>
                <div class="input-group">
                    <?= Html::textInput('search', Yii::$app->request->get('search'), [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('BazaarModule.base', 'Search modules...'),
                    ]) ?>
                    <button class="btn btn-outline-secondary" type="submit">
                        <?= Icon::get('search') ?>
                    </button>
                </div>
                <?php ActiveForm::end(); ?>
            </div>

            <div class="col-md-3">
                <?= Html::dropDownList('category', Yii::$app->request->get('category', ''), 
                    array_merge(['' => Yii::t('BazaarModule.base', 'All Categories')], $categories), 
                    ['class' => 'form-select filter-category']) ?>
            </div>

            <div class="col-md-3">
                <?= Html::dropDownList('sort', Yii::$app->request->get('sort', ''), [
                    'name' => Yii::t('BazaarModule.base', 'Name'),
                    'price' => Yii::t('BazaarModule.base', 'Price'),
                    'category' => Yii::t('BazaarModule.base', 'Category'),
                ], ['class' => 'form-select filter-sort']) ?>
            </div>
        </div>

        <!-- Modules Grid -->
        <?php if (empty($modules)): ?>
            <div class="text-center py-5">
                <?= Icon::get('shopping-cart') ?>
                <h4 class="mt-3"><?= Yii::t('BazaarModule.base', 'No modules available') ?></h4>
                <p class="text-body-secondary">
                    <?= Yii::t('BazaarModule.base', 'Check your API configuration or try again later.') ?>
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($modules as $index => $module): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 module-card">
                            <!-- Module Image -->
                            <div class="module-image">
                                <?php if (!empty($module->screenshots)): ?>
                                    <?= Html::img($module->screenshots[0], [
                                        'class' => 'card-img-top',
                                        'alt' => $module->name,
                                        'style' => 'height: 200px; object-fit: cover;',
                                    ]) ?>
                                <?php else: ?>
                                    <div class="placeholder-image d-flex align-items-center justify-content-center bg-light" 
                                         style="height: 200px;">
                                        <?= Icon::get('puzzle-piece') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <!-- Module Header -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-1"><?= Html::encode($module->name) ?></h5>
                                    <?= Badge::secondary($module->getCategoryLabel())->sm() ?>
                                </div>

                                <!-- Module Info -->
                                <p class="card-text text-body-secondary small mb-2">
                                    <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                                    • v<?= Html::encode($module->version) ?>
                                </p>

                                <!-- DEBUG INFO for specific module -->
                                <?php if (YII_DEBUG): ?>
                                <div class="small text-info mb-2" style="font-family: monospace;">
                                    DEBUG: price=<?= Html::encode($module->price) ?> 
                                    (<?= gettype($module->price) ?>), 
                                    isPaid=<?= $module->isPaid ? 'true' : 'false' ?> 
                                    (<?= gettype($module->isPaid) ?>)
                                </div>
                                <?php endif; ?>

                                <!-- Description -->
                                <p class="card-text flex-grow-1">
                                    <?= Html::encode(\yii\helpers\StringHelper::truncate($module->description, 100)) ?>
                                </p>

                                <!-- Features Preview -->
                                <?php if (!empty($module->features)): ?>
                                    <div class="mb-3">
                                        <small class="text-body-secondary">
                                            <?= Yii::t('BazaarModule.base', 'Features:') ?>
                                            <?= Html::encode(implode(', ', array_slice($module->features, 0, 2))) ?>
                                            <?php if (count($module->features) > 2): ?>...<?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <!-- Actions -->
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <!-- Price / Badge -->
                                    <div class="price">
                                        <?php if ($module->isSoon): ?>
                                            <?= Badge::warning(Yii::t('BazaarModule.base', 'Coming Soon')) ?>
                                        <?php elseif ($module->isPurchased): ?>
                                            <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased')) ?>
                                        <?php elseif ($module->isPaid): ?>
                                            <strong class="text-primary">
                                                <?php if (method_exists($module, 'getFormattedPrice')): ?>
                                                    <?= $module->getFormattedPrice() ?>
                                                <?php else: ?>
                                                    <?= Html::encode(number_format($module->price, 2)) ?> <?= Html::encode($module->currency) ?>
                                                <?php endif; ?>
                                            </strong>
                                        <?php else: ?>
                                            <?= Badge::info(Yii::t('BazaarModule.base', 'Free')) ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="btn-group btn-group-sm">
                                        <?= Button::info(Yii::t('BazaarModule.base', 'Details'))
                                            ->link(['/bazaar/admin/view', 'id' => $module->id])
                                            ->sm() ?>

                                        <?php if (!$module->isPurchased && !$module->isSoon): ?>
                                            <?php if ($module->isPaid): ?>
                                                <?= Button::primary(Yii::t('BazaarModule.base', 'Buy'))
                                                    ->link(['/bazaar/admin/purchase', 'id' => $module->id])
                                                    ->sm() ?>
                                            <?php else: ?>
                                                <?= Button::success(Yii::t('BazaarModule.base', 'Install'))
                                                    ->link($module->downloadUrl ?? '#')
                                                    ->sm() ?>
                                            <?php endif; ?>
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
$(document).ready(function() {
    $('.filter-category, .filter-sort').on('change', function() {
        window.location.href = '<?= Url::to(['/bazaar/admin/index']) ?>?' + 
            'category=' + $('.filter-category').val() + '&sort=' + $('.filter-sort').val();
    });
});
</script>