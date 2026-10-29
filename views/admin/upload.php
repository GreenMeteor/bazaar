<?php

use humhub\helpers\Html;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use humhub\modules\bazaar\assets\BazaarAsset;

BazaarAsset::register($this);

/* @var $this \humhub\components\View */
/* @var $model \humhub\modules\bazaar\models\ModuleUploadForm */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong><?= Yii::t('BazaarModule.base', 'Module') ?></strong>
        <?= Yii::t('BazaarModule.base', 'Upload') ?>

        <div class="float-end">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Installed Modules'))
                ->link(['/bazaar/admin/installed'])
                ->icon('puzzle-piece')->sm() ?>
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back to Bazaar'))
                ->link(['/bazaar/admin/index'])
                ->icon('arrow-left')->sm() ?>
        </div>
    </div>

    <div class="panel-body">
        <p class="text-body-secondary">
            <?= Yii::t(
                'BazaarModule.base',
                'Install a module ZIP from your computer. The archive is validated before it is extracted into the HumHub modules directory.'
            ) ?>
        </p>

        <?php $form = ActiveForm::begin([
            'id' => 'bazaar-module-upload-form',
            'options' => ['enctype' => 'multipart/form-data'],
        ]) ?>

            <div class="bzr-upload-zone" id="bazaar-upload-zone">
                <div class="bzr-upload-icon">
                    <?= \humhub\modules\ui\icon\widgets\Icon::get('cloud-upload') ?>
                </div>
                <div class="bzr-upload-label" id="bazaar-upload-label">
                    <?= Yii::t('BazaarModule.base', 'Click to choose a ZIP file, or drag and drop it here') ?>
                </div>

                <?= $form->field($model, 'zipFile', ['options' => ['class' => 'bzr-upload-field']])
                    ->label(false)
                    ->fileInput([
                        'id' => 'bazaar-upload-input',
                        'accept' => '.zip,application/zip',
                    ]) ?>
            </div>
            
            <div class="mt-3">
                <?= $form->field($model, 'replaceExisting')->checkbox([
                    'uncheck' => '0',
                    'label' => Yii::t('BazaarModule.base', 'Replace an existing module'),
                ]) ?>
            </div>

            <p class="text-body-secondary small">
                <?= Yii::t('BazaarModule.base', 'The existing module must be disabled before its files can be replaced.') ?>
            </p>

            <?php if ($model->hasErrors()): ?>
                <div class="alert alert-danger">
                    <?= Html::errorSummary($model, ['header' => '']) ?>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <?= Button::primary(Yii::t('BazaarModule.base', 'Upload & Install'))
                    ->icon('upload')
                    ->submit()
                    ->options(['data-ui-loader' => '']) ?>
            </div>

        <?php ActiveForm::end() ?>
    </div>
</div>
