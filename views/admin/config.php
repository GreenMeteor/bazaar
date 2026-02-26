<?php

use humhub\widgets\form\ActiveForm;
use humhub\widgets\bootstrap\Button;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\helpers\Html;
use humhub\modules\bazaar\assets\BazaarAsset;
use yii\helpers\Url;

BazaarAsset::register($this);

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
            'placeholder' => 'https://greenmeteor.net/api/modules.php',
        ])->hint(Yii::t('BazaarModule.base', 'The Green Meteor modules API endpoint')) ?>

        <?= $form->field($model, 'apiKey')->passwordInput([
            'placeholder' => Yii::t('BazaarModule.base', 'Enter your API key...'),
        ])->hint(Yii::t('BazaarModule.base', 'Your Green Meteor API authentication key (optional for basic access)')) ?>

        <?= $form->field($model, 'cacheTimeout')->textInput([
            'type'        => 'number',
            'min'         => 60,
            'max'         => 86400,
            'placeholder' => '3600',
        ])->hint(Yii::t('BazaarModule.base', 'How long to cache API responses in seconds (60 – 86400)')) ?>

        <?= $form->field($model, 'enablePurchasing')->checkbox()
            ->hint(Yii::t('BazaarModule.base', 'Allow admins to purchase modules directly from the bazaar')) ?>

        <div class="alert alert-info">
            <?= Icon::get('info-circle') ?>
            <?= Yii::t('BazaarModule.base', 'To get your API key, register at {link}', [
                'link' => '<a href="https://greenmeteor.net/developer" target="_blank">https://greenmeteor.net</a>',
            ]) ?>
        </div>

        <div class="card bg-light mt-4">
            <div class="card-body">
                <h6 class="card-title"><?= Yii::t('BazaarModule.base', 'API Connection Test') ?></h6>
                <p class="card-text text-body-secondary">
                    <?= Yii::t('BazaarModule.base', 'Test your saved API configuration before use.') ?>
                </p>

                <?= Button::info(Yii::t('BazaarModule.base', 'Test Connection'))
                    ->options([
                        'id' => 'bazaar-test-btn',
                        'type' => 'button',
                        'data-action' => 'testConnection',
                        'data-action-url' => Url::to(['/bazaar/admin/test-connection']),
                    ])
                    ->icon('plug')
                    ->loader(false) ?>

                <div id="bazaar-test-result" class="mt-3 d-none"></div>
            </div>
        </div>

        <br>

        <div class="text-end">
            <?= Button::secondary(Yii::t('base', 'Cancel'))->link(['/bazaar/admin/index']) ?>
            <?= Button::primary(Yii::t('base', 'Save'))->submit() ?>
        </div>

    </div>
    <?php ActiveForm::end(); ?>
</div>