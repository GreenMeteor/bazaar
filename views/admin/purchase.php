<?php

use humhub\widgets\form\ActiveForm;
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
        <?= Yii::t('BazaarModule.base', '<strong>Purchase</strong> Module') ?>

        <div class="float-end">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back'))
                ->link(['/bazaar/admin/view', 'id' => $module->id])
                ->icon('arrow-left')->sm() ?>
        </div>
    </div>

    <div class="panel-body">

        <div class="card bg-light mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1"><?= Html::encode($module->name) ?></h4>
                        <p class="text-body-secondary mb-0">
                            <?= Yii::t('BazaarModule.base', 'by {author}', ['author' => Html::encode($module->author)]) ?>
                            • v<?= Html::encode($module->version) ?>
                            • <?= Badge::secondary($module->getCategoryLabel()) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h3 class="text-primary mb-0"><?= Html::encode($module->getFormattedPrice()) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$module->isPaid): ?>
            <div class="alert alert-success">
                <?= Icon::get('check-circle') ?>
                <?= Yii::t('BazaarModule.base', 'This is a free module! Click below to install it.') ?>
            </div>

            <div class="text-center">
                <?= Button::success(Yii::t('BazaarModule.base', 'Install Free Module'))
                    ->link(['/bazaar/admin/install', 'id' => $module->id])
                    ->icon('download')->lg() ?>
            </div>

        <?php else: ?>
            <?php $form = ActiveForm::begin(['method' => 'post', 'id' => 'purchase-form']); ?>

            <div class="mb-4">
                <h5><?= Yii::t('BazaarModule.base', 'Purchase Information') ?></h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label"><?= Yii::t('BazaarModule.base', 'Email Address') ?></label>
                            <input type="email" class="form-control"
                                   value="<?= Html::encode(Yii::$app->user->identity->email) ?>" readonly>
                            <div class="form-text">
                                <?= Yii::t('BazaarModule.base', 'Purchase confirmation will be sent to this email') ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label"><?= Yii::t('BazaarModule.base', 'Site URL') ?></label>
                            <input type="url" class="form-control"
                                   value="<?= Html::encode(Yii::$app->request->hostInfo) ?>" readonly>
                            <div class="form-text">
                                <?= Yii::t('BazaarModule.base', 'Module will be licensed for this domain') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                    <label class="form-check-label" for="acceptTerms">
                        <?= Yii::t('BazaarModule.base', 'I agree to the {terms} and {privacy}', [
                            'terms'   => '<a href="https://greenmeteor.net/legal#terms-of-service" target="_blank">'
                                       . Yii::t('BazaarModule.base', 'Terms of Service') . '</a>',
                            'privacy' => '<a href="https://greenmeteor.net/legal#privacy-policy" target="_blank">'
                                       . Yii::t('BazaarModule.base', 'Privacy Policy') . '</a>',
                        ]) ?>
                    </label>
                </div>
            </div>

            <div class="alert alert-info">
                <?= Icon::get('shield-check') ?>
                <?= Yii::t('BazaarModule.base', 'Your purchase is secured by Stripe. You will be redirected to a secure payment page.') ?>
            </div>

            <div id="loading-message" class="alert alert-primary d-none" role="status">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" aria-hidden="true"></div>
                    <?= Yii::t('BazaarModule.base', 'Creating secure checkout session… You will be redirected to Stripe shortly.') ?>
                </div>
            </div>

            <div id="loading-warning" class="alert alert-warning d-none">
                <?= Icon::get('exclamation-triangle') ?>
                <?= Yii::t('BazaarModule.base', 'Taking longer than expected. Please check your connection or try again.') ?>
            </div>

            <div class="text-end">
                <?= Button::secondary(Yii::t('base', 'Cancel'))
                    ->link(['/bazaar/admin/view', 'id' => $module->id]) ?>
                <?= Button::primary(Yii::t('BazaarModule.base', 'Complete Purchase'))
                    ->submit()
                    ->icon('shopping-cart')
                    ->options(['id' => 'purchase-btn', 'disabled' => true]) ?>
            </div>

            <?php ActiveForm::end(); ?>
        <?php endif; ?>

    </div>
</div>

<script <?= Html::nonce() ?>>
(function () {
    'use strict';

    var termsCheckbox = document.getElementById('acceptTerms');
    var purchaseBtn   = document.getElementById('purchase-btn');
    var purchaseForm  = document.getElementById('purchase-form');
    var loadingMsg    = document.getElementById('loading-message');
    var warningMsg    = document.getElementById('loading-warning');

    if (termsCheckbox && purchaseBtn) {
        termsCheckbox.addEventListener('change', function () {
            purchaseBtn.disabled = !this.checked;
        });
    }

    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function () {
            if (loadingMsg) { loadingMsg.classList.remove('d-none'); }
            if (purchaseBtn) {
                purchaseBtn.disabled = true;
                purchaseBtn.textContent = '<?= Yii::t('BazaarModule.base', 'Processing…') ?>';
            }

            setTimeout(function () {
                if (loadingMsg && !loadingMsg.classList.contains('d-none')) {
                    loadingMsg.classList.add('d-none');
                    if (warningMsg) { warningMsg.classList.remove('d-none'); }
                }
            }, 10000);
        });
    }
}());
</script>