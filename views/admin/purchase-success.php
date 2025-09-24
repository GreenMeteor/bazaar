<?php

use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Badge;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\helpers\Html;

/* @var $this \humhub\components\View */
/* @var $module \humhub\modules\bazaar\models\Module */
/* @var $sessionId string|null */
/* @var $verified bool */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Purchase</strong> Complete') ?>
        
        <div class="float-end">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back to Bazaar'))
                ->link(['/bazaar/admin/index'])
                ->icon('arrow-left')->sm() ?>
        </div>
    </div>

    <div class="panel-body">
        
        <!-- Success Message -->
        <div class="text-center mb-5">
            <div class="mb-3">
                <?= Icon::get('check-circle', ['class' => 'text-success', 'style' => 'font-size: 4rem;']) ?>
            </div>
            <h2 class="text-success mb-3">
                <?= Yii::t('BazaarModule.base', 'Thank you for your purchase!') ?>
            </h2>
            <p class="lead text-body-secondary">
                <?= Yii::t('BazaarModule.base', 'Your payment has been processed successfully.') ?>
            </p>
        </div>

        <!-- Module Information Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <?php if (!empty($module->screenshots)): ?>
                            <?= Html::img($module->screenshots[0], [
                                'class' => 'img-fluid rounded',
                                'alt' => $module->name,
                                'style' => 'max-height: 120px; width: 100%; object-fit: cover;'
                            ]) ?>
                        <?php else: ?>
                            <div class="placeholder-image d-flex align-items-center justify-content-center bg-light rounded" 
                                 style="height: 120px;">
                                <?= Icon::get('puzzle-piece') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h4 class="mb-1"><?= Html::encode($module->name) ?></h4>
                            <?= Badge::success(Yii::t('BazaarModule.base', 'Purchased')) ?>
                        </div>
                        <p class="text-body-secondary mb-2">
                            <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                            • v<?= Html::encode($module->version) ?>
                        </p>
                        <p class="mb-0"><?= Html::encode($module->description) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Details -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <?= Icon::get('receipt') ?>
                        <?= Yii::t('BazaarModule.base', 'Purchase Details') ?>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4"><?= Yii::t('BazaarModule.base', 'Module:') ?></dt>
                            <dd class="col-sm-8"><?= Html::encode($module->name) ?></dd>
                            
                            <dt class="col-sm-4"><?= Yii::t('BazaarModule.base', 'Price:') ?></dt>
                            <dd class="col-sm-8"><?= $module->getFormattedPrice() ?></dd>
                            
                            <?php if ($sessionId): ?>
                                <dt class="col-sm-4"><?= Yii::t('BazaarModule.base', 'Session:') ?></dt>
                                <dd class="col-sm-8">
                                    <code><?= Html::encode(substr($sessionId, 0, 20)) ?>...</code>
                                </dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-4"><?= Yii::t('BazaarModule.base', 'Status:') ?></dt>
                            <dd class="col-sm-8">
                                <?php if (isset($verified) && $verified): ?>
                                    <?= Badge::success(Yii::t('BazaarModule.base', 'Verified')) ?>
                                <?php elseif (isset($verified) && !$verified): ?>
                                    <?= Badge::warning(Yii::t('BazaarModule.base', 'Pending Verification')) ?>
                                <?php else: ?>
                                    <?= Badge::info(Yii::t('BazaarModule.base', 'Processing')) ?>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <?= Icon::get('download') ?>
                        <?= Yii::t('BazaarModule.base', 'Next Steps') ?>
                    </div>
                    <div class="card-body">
                        <?php if ($module->isPurchased && $module->downloadUrl): ?>
                            <p><?= Yii::t('BazaarModule.base', 'Your module is ready to download and install:') ?></p>
                            
                            <div class="d-grid gap-2">
                                <?= Button::primary(Yii::t('BazaarModule.base', 'Download Module'))
                                    ->link($module->downloadUrl)
                                    ->icon('download')
                                    ->options(['class' => 'btn-lg']) ?>
                                    
                                <?= Button::info(Yii::t('BazaarModule.base', 'View Module Details'))
                                    ->link(['/bazaar/admin/view', 'id' => $module->id])
                                    ->icon('info-circle') ?>
                            </div>
                            
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?= Icon::get('clock') ?>
                                <?= Yii::t('BazaarModule.base', 'Your download will be available shortly. Please allow a few minutes for payment processing.') ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?= Button::info(Yii::t('BazaarModule.base', 'Check Status'))
                                    ->link(['/bazaar/admin/view', 'id' => $module->id])
                                    ->icon('refresh') ?>
                                    
                                <?= Button::secondary(Yii::t('BazaarModule.base', 'Contact Support'))
                                    ->link('https://greenmeteor.net/support')
                                    ->icon('headphones')
                                    ->options(['target' => '_blank']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Important Information -->
        <div class="alert alert-info mt-4">
            <h6><?= Icon::get('info-circle') ?> <?= Yii::t('BazaarModule.base', 'Important Information') ?></h6>
            <ul class="mb-0">
                <li><?= Yii::t('BazaarModule.base', 'A confirmation email has been sent to your registered email address') ?></li>
                <li><?= Yii::t('BazaarModule.base', 'Keep your purchase receipt for future reference and support') ?></li>
                <li><?= Yii::t('BazaarModule.base', 'Module updates and support are included with your purchase') ?></li>
                <li><?= Yii::t('BazaarModule.base', 'For technical support, visit {link}', [
                    'link' => '<a href="https://greenmeteor.net/support" target="_blank">greenmeteor.net/support</a>'
                ]) ?></li>
            </ul>
        </div>

    </div>
</div>

<script <?= Html::nonce() ?>>
$(document).ready(function() {
    // Auto-refresh status if verification is pending
    <?php if (isset($verified) && !$verified): ?>
    setTimeout(function() {
        window.location.reload();
    }, 10000); // Refresh after 10 seconds
    <?php endif; ?>
});
</script>