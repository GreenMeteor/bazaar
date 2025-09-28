<?php

use humhub\widgets\form\ActiveForm;
use humhub\widgets\bootstrap\Button;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\modules\bazaar\assets\BazaarAsset;

BazaarAsset::register($this);

/* @var $this \humhub\components\View */
/* @var $model \humhub\modules\bazaar\models\ConfigureForm */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('BazaarModule.base', '<strong>Bazaar</strong> Configuration') ?>
    </div>

    <?php $form = ActiveForm::begin(); ?>
    <div class="panel-body">

        <?= $form->field($model, 'apiBaseUrl')->textInput([
            'placeholder' => 'https://api.greenmeteor.net/v1'
        ])->hint(Yii::t('BazaarModule.base', 'The base URL for the Green Meteor API')) ?>

        <?= $form->field($model, 'apiKey')->passwordInput([
            'placeholder' => Yii::t('BazaarModule.base', 'Enter your API key...')
        ])->hint(Yii::t('BazaarModule.base', 'Your Green Meteor API authentication key')) ?>

        <?= $form->field($model, 'cacheTimeout')->textInput([
            'type' => 'number',
            'min' => 60,
            'placeholder' => '3600'
        ])->hint(Yii::t('BazaarModule.base', 'How long to cache API responses (in seconds)')) ?>

        <?= $form->field($model, 'enablePurchasing')->checkbox()
            ->hint(Yii::t('BazaarModule.base', 'Allow users to purchase modules directly from the bazaar')) ?>

        <div class="alert alert-info">
            <?= Icon::get('info-circle') ?>
            <?= Yii::t('BazaarModule.base', 'To get your API key, register at {link}', [
                'link' => '<a href="https://greenmeteor.net/" target="_blank">greenmeteor.net/</a>'
            ]) ?>
        </div>

    </div>

    <div class="panel-footer text-end">
        <?= Button::primary(Yii::t('base', 'Save'))->submit() ?>
        <?= Button::secondary(Yii::t('base', 'Cancel'))->link(['/bazaar/admin/index']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>