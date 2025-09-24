<?php

use humhub\widgets\form\ActiveForm;
use humhub\widgets\bootstrap\Button;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\helpers\Html;

/* @var $this \humhub\components\View */
/* @var $model \humhub\modules\bazaar\models\ConfigureForm */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Bazaar</strong> Configuration') ?>
        
        <div class="float-end">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back to Bazaar'))
                ->link(['/bazaar/admin/index'])
                ->icon('arrow-left')->sm() ?>
        </div>
    </div>

    <?php $form = ActiveForm::begin(); ?>
    <div class="panel-body">

        <?= $form->field($model, 'apiBaseUrl')->textInput([
            'placeholder' => 'https://greenmeteor.net/api/modules.php'
        ])->hint(Yii::t('BazaarModule.base', 'The base URL for the Green Meteor API')) ?>

        <?= $form->field($model, 'apiKey')->passwordInput([
            'placeholder' => Yii::t('BazaarModule.base', 'Enter your API key...')
        ])->hint(Yii::t('BazaarModule.base', 'Your Green Meteor API authentication key (optional for basic access)')) ?>

        <?= $form->field($model, 'cacheTimeout')->textInput([
            'type' => 'number',
            'min' => 60,
            'max' => 86400,
            'placeholder' => '3600'
        ])->hint(Yii::t('BazaarModule.base', 'How long to cache API responses (in seconds, 60-86400)')) ?>

        <?= $form->field($model, 'enablePurchasing')->checkbox()
            ->hint(Yii::t('BazaarModule.base', 'Allow users to purchase modules directly from the bazaar')) ?>

        <div class="alert alert-info">
            <?= Icon::get('info-circle') ?>
            <?= Yii::t('BazaarModule.base', 'To get your API key and access premium features, register at {link}', [
                'link' => '<a href="https://greenmeteor.net/" target="_blank">greenmeteor.net</a>'
            ]) ?>
        </div>

        <!-- API Test Section -->
        <div class="card bg-light mt-4">
            <div class="card-body">
                <h6 class="card-title"><?= Yii::t('BazaarModule.base', 'API Connection Test') ?></h6>
                <p class="card-text text-body-secondary">
                    <?= Yii::t('BazaarModule.base', 'Test your API configuration before saving') ?>
                </p>
                <?= Button::info(Yii::t('BazaarModule.base', 'Test Connection'))
                    ->options(['id' => 'test-api-btn', 'type' => 'button'])
                    ->icon('plug') ?>
                <div id="api-test-result" class="mt-2" style="display: none;"></div>
            </div>
        </div>

    </div>

    <div class="panel-footer text-end">
        <?= Button::primary(Yii::t('base', 'Save'))->submit()->icon('save') ?>
        <?= Button::secondary(Yii::t('base', 'Cancel'))->link(['/bazaar/admin/index']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<script <?= Html::nonce() ?>>
$(document).ready(function() {
    $('#test-api-btn').on('click', function() {
        var btn = $(this);
        var resultDiv = $('#api-test-result');
        
        // Show loading state
        btn.prop('disabled', true).html('<?= Icon::get("spinner") ?> <?= Yii::t("BazaarModule.base", "Testing...") ?>');
        resultDiv.hide();
        
        // Get current form values
        var apiUrl = $('#configureform-apibaseurl').val();
        var apiKey = $('#configureform-apikey').val();
        
        // Test API connection
        $.ajax({
            url: '<?= yii\helpers\Url::to(["/bazaar/admin/test-api"]) ?>',
            method: 'POST',
            data: {
                api_url: apiUrl,
                api_key: apiKey,
                '<?= Yii::$app->request->csrfParam ?>': '<?= Yii::$app->request->csrfToken ?>'
            },
            dataType: 'json',
            success: function(response) {
                var alertClass = response.success ? 'alert-success' : 'alert-danger';
                var icon = response.success ? '<?= Icon::get("check-circle") ?>' : '<?= Icon::get("x-circle") ?>';
                
                resultDiv.html(
                    '<div class="alert ' + alertClass + '">' + 
                    icon + ' ' + response.message + 
                    '</div>'
                ).show();
            },
            error: function() {
                resultDiv.html(
                    '<div class="alert alert-danger">' +
                    '<?= Icon::get("x-circle") ?> <?= Yii::t("BazaarModule.base", "Connection test failed") ?>' +
                    '</div>'
                ).show();
            },
            complete: function() {
                btn.prop('disabled', false).html('<?= Icon::get("plug") ?> <?= Yii::t("BazaarModule.base", "Test Connection") ?>');
            }
        });
    });
});
</script>