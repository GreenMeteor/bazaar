<?php

use humhub\helpers\Html;
use humhub\widgets\bootstrap\Button;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\modules\bazaar\assets\BazaarAsset;

BazaarAsset::register($this);

/* @var $this \humhub\components\View */
/* @var $modules array Purchased Bazaar modules that are installed locally */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong><?= Yii::t('BazaarModule.base', 'Installed Modules') ?></strong>

        <div class="float-end d-flex flex-wrap gap-2">
            <?= Button::secondary(Yii::t('BazaarModule.base', 'Upload Module'))
                ->link(['/bazaar/admin/upload'])
                ->icon('upload')
                ->sm() ?>

            <?= Button::secondary(Yii::t('BazaarModule.base', 'Back to Bazaar'))
                ->link(['/bazaar/admin/index'])
                ->icon('arrow-left')
                ->sm() ?>
        </div>
    </div>

    <div class="panel-body">
        <p class="text-body-secondary mb-3">
            <?= Yii::t(
                'BazaarModule.base',
                'This page lists Bazaar modules that you have purchased and that are currently installed in this HumHub modules directory. Locally uploaded modules and other third-party modules are not shown here.',
            ) ?>
        </p>

        <?php if (empty($modules)): ?>
            <div class="text-center py-5 px-3 border rounded-3">
                <div class="mb-3 text-body-secondary bzr-installed-empty-icon" aria-hidden="true">
                    <?= Icon::get('box-open') ?>
                </div>

                <h5><?= Yii::t('BazaarModule.base', 'No purchased modules installed') ?></h5>

                <p class="text-body-secondary mb-3">
                    <?= Yii::t(
                        'BazaarModule.base',
                        'Purchased modules will appear here after they are installed in your HumHub modules directory.',
                    ) ?>
                </p>

                <?= Button::primary(Yii::t('BazaarModule.base', 'Browse Bazaar'))
                    ->link(['/bazaar/admin/index'])
                    ->icon('shopping-bag')
                    ->sm() ?>
            </div>
        <?php else: ?>
            <div class="row g-3 bzr-installed-grid">
                <?php foreach ($modules as $module): ?>
                    <?php
                    $product = $module['bazaarModule'];
                    $isInstalled = (bool) ($module['installed'] ?? true);
                    $isEnabled = $isInstalled && !empty($module['enabled']);
                    $cover = $product->screenshots[0] ?? null;
                    $moduleId = (string) $module['id'];

                    $description = trim((string) ($module['description'] ?? ''));

                    if ($description === '') {
                        $description = (string) $product->description;
                    }
                    ?>

                    <div class="col-12 col-md-6 col-xl-4">
                        <article class="card-panel bzr-installed-card">
                            <div class="card-header bzr-installed-cover<?= empty($cover) ? ' bzr-installed-cover-empty' : '' ?>">
                                <?php if (!empty($cover)): ?>
                                    <?= Html::img($cover, [
                                        'alt' => '',
                                        'class' => 'bzr-installed-cover-image',
                                        'loading' => 'lazy',
                                    ]) ?>
                                <?php else: ?>
                                    <span class="bzr-installed-cover-pattern" aria-hidden="true"></span>
                                <?php endif; ?>

                                <span class="bzr-installed-owned-badge">
                                    <?= Icon::get('check-circle') ?>
                                    <?= Yii::t('BazaarModule.base', 'Purchased') ?>
                                </span>
                            </div>

                            <div class="card-body bzr-installed-card-body">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div class="min-w-0">
                                        <h5 class="card-title bzr-installed-card-title">
                                            <?= Html::encode($product->name) ?>
                                        </h5>

                                        <?php if ($isInstalled): ?>
                                            <div class="small text-body-secondary bzr-installed-module-id">
                                                <?= Yii::t('BazaarModule.base', 'Module ID') ?>:
                                                <code><?= Html::encode($moduleId) ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isEnabled): ?>
                                        <span class="badge bg-success flex-shrink-0">
                                            <?= Yii::t('BazaarModule.base', 'Enabled') ?>
                                        </span>
                                    <?php elseif ($isInstalled): ?>
                                        <span class="badge bg-secondary flex-shrink-0">
                                            <?= Yii::t('BazaarModule.base', 'Disabled') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($description !== ''): ?>
                                    <p class="card-text text-body-secondary bzr-installed-card-description">
                                        <?= Html::encode(
                                            \humhub\libs\StringHelper::truncate($description, 150),
                                        ) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text text-body-secondary bzr-installed-card-description bzr-installed-card-description-empty">
                                        <?= Yii::t('BazaarModule.base', 'No description available.') ?>
                                    </p>
                                <?php endif; ?>

                                <div class="bzr-installed-card-meta">
                                    <span>
                                        <?= Icon::get('code-branch') ?>
                                        <?= Yii::t('BazaarModule.base', 'Installed version') ?>
                                    </span>

                                    <strong>
                                        <?= Html::encode(
                                            $isInstalled ? (($module['version'] ?? '') ?: '—') : '—',
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="card-footer bzr-installed-card-footer">
                                <?= Button::secondary(Yii::t('BazaarModule.base', 'View Details'))
                                    ->link(['/bazaar/admin/view', 'id' => $product->id])
                                    ->icon('external-link')
                                    ->sm() ?>

                                <?php if ($isInstalled && !$isEnabled): ?>
                                    <?= Button::success(Yii::t('BazaarModule.base', 'Enable'))
                                        ->icon('check')
                                        ->link([
                                            '/bazaar/admin/enable-module',
                                            'id' => $moduleId,
                                        ], false)
                                        ->confirm(
                                            Yii::t('BazaarModule.base', 'Enable Module'),
                                            Yii::t(
                                                'BazaarModule.base',
                                                'Are you sure you want to enable module "{id}"?',
                                                ['id' => $moduleId],
                                            ),
                                            Yii::t('BazaarModule.base', 'Enable'),
                                        )
                                        ->options([
                                            'data-action-method' => 'POST',
                                        ])
                                        ->sm() ?>

                                    <?= Button::secondary(Yii::t('BazaarModule.base', 'Remove Files'))
                                        ->icon('trash')
                                        ->link([
                                            '/bazaar/admin/remove',
                                            'id' => $moduleId,
                                        ], false)
                                        ->confirm(
                                            Yii::t('BazaarModule.base', 'Remove Module Files'),
                                            Yii::t(
                                                'BazaarModule.base',
                                                'Remove the files for module "{id}"? This does not uninstall its database data.',
                                                ['id' => $moduleId],
                                            ),
                                            Yii::t('BazaarModule.base', 'Remove Files'),
                                        )
                                        ->options([
                                            'data-action-method' => 'POST',
                                        ])
                                        ->sm() ?>

                                <?php elseif ($isInstalled && $isEnabled): ?>
                                    <?= Button::warning(Yii::t('BazaarModule.base', 'Disable'))
                                        ->icon('ban')
                                        ->link([
                                            '/bazaar/admin/disable-module',
                                            'id' => $moduleId,
                                        ], false)
                                        ->confirm(
                                            Yii::t('BazaarModule.base', 'Disable Module'),
                                            Yii::t(
                                                'BazaarModule.base',
                                                'Disable module "{id}"? HumHub may remove module-managed data during deactivation.',
                                                ['id' => $moduleId],
                                            ),
                                            Yii::t('BazaarModule.base', 'Disable'),
                                        )
                                        ->options([
                                            'data-action-method' => 'POST',
                                        ])
                                        ->sm() ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>