<?php

namespace humhub\modules\bazaar\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use humhub\modules\admin\components\Controller;
use humhub\modules\admin\jobs\DisableModuleJob;
use humhub\modules\admin\jobs\RemoveModuleJob;
use humhub\modules\queue\helpers\QueueHelper;
use humhub\modules\bazaar\models\Module as BazaarModule;
use humhub\modules\bazaar\models\ConfigureForm;
use humhub\modules\bazaar\models\ModuleUploadForm;
use humhub\modules\bazaar\services\ModuleInstaller;
use yii\web\UploadedFile;
use humhub\modules\admin\libs\CacheHelper;

/**
 * AdminController — Bazaar module
 *
 * Handles module listing, purchasing, installation, and configuration
 * within the HumHub administration panel.
 *
 * @since 1.0
 */
class AdminController extends Controller
{
    /**
     * Renders all available modules from the Green Meteor API with optional
     * server-side search, category, and sort filters.
     */
    public function actionIndex(): string
    {
        $apiService = Yii::$app->getModule('bazaar')->getApiService();

        try {
            $modulesData = $apiService->getModules();
        } catch (\Throwable $e) {
            Yii::error('Failed to fetch modules: ' . $e->getMessage(), 'bazaar');
            $modulesData = [];

            $this->view->error(
                Yii::t('BazaarModule.base', 'Could not reach the Green Meteor API. Please check your configuration.')
            );
        }

        $modules = [];
        $categories = [];
        $seenIds = [];

        foreach ($modulesData as $data) {
            $module = new BazaarModule($data);
            $moduleId = (string)$module->id;

            if (isset($seenIds[$moduleId])) {
                continue;
            }
            $seenIds[$moduleId] = true;
            $modules[] = $module;

            if (!empty($module->category)) {
                $categories[$module->category] = $module->getCategoryLabel();
            }
        }

        $search = Yii::$app->request->get('search', '');
        $category = Yii::$app->request->get('category', '');
        $sort = Yii::$app->request->get('sort', '');

        if ($search !== '') {
            $modules = array_filter($modules, static function (BazaarModule $m) use ($search): bool {
                return stripos($m->name, $search) !== false
                    || stripos($m->description, $search) !== false;
            });
        }

        if ($category !== '') {
            $modules = array_filter($modules, static function (BazaarModule $m) use ($category): bool {
                return $m->category === $category;
            });
        }

        switch ($sort) {
            case 'name':
                usort($modules, static fn(BazaarModule $a, BazaarModule $b): int => strcmp($a->name, $b->name));
                break;
            case 'price':
                usort($modules, static fn(BazaarModule $a, BazaarModule $b): int => $a->price <=> $b->price);
                break;
            case 'category':
                usort($modules, static fn(BazaarModule $a, BazaarModule $b): int => strcmp($a->category, $b->category));
                break;
        }

        asort($categories);

        return $this->render('@bazaar/views/admin/index', [
            'modules' => array_values($modules),
            'categories' => $categories,
        ]);
    }

    /**
     * Shows detail for a single module.
     *
     * @param string $id Module ID (numeric string or slug for coming-soon modules)
     */
    public function actionView(string $id): string
    {
        return $this->render('@bazaar/views/admin/view', [
            'module' => $this->findModule($id),
        ]);
    }

    /**
     * GET  – Purchase confirmation / checkout page.
     * POST – Creates a Stripe Checkout session via the Green Meteor API and
     *        redirects the user to the Stripe-hosted payment page.
     *
     * @param string $id Module ID
     */
    public function actionPurchase(string $id): \yii\web\Response|string
    {
        $bazaarModule = Yii::$app->getModule('bazaar');
        $module = $this->findModule($id);

        if (!$bazaarModule->isPurchasingEnabled()) {
            $this->view->error(Yii::t('BazaarModule.base', 'Purchasing is currently disabled.'));
            return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
        }

        if ($module->isPurchased) {
            return $this->redirect(['/bazaar/admin/purchase-success', 'id' => $id]);
        }

        if (Yii::$app->request->isPost) {
            $apiService = $bazaarModule->getApiService();

            $returnUrl = Yii::$app->urlManager->createAbsoluteUrl([
                '/bazaar/admin/purchase-success', 'id' => $id,
            ]);

            $cancelUrl = Yii::$app->urlManager->createAbsoluteUrl([
                '/bazaar/admin/purchase', 'id' => $id,
            ]);

            try {
                $result = $apiService->purchaseModule($module->id, [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ]);

                if (!empty($result['checkout_url'])) {
                    return $this->redirect($result['checkout_url']);
                }

                if (!empty($result['is_free'])) {
                    return $this->redirect(['/bazaar/admin/purchase-success', 'id' => $id]);
                }

                $this->view->error(Yii::t('BazaarModule.base', 'Failed to initiate purchase. Please try again.'));

            } catch (\Throwable $e) {
                $this->view->error(Yii::t('BazaarModule.base', 'Purchase failed: {error}', ['error' => $e->getMessage()]));
            }
        }

        return $this->render('@bazaar/views/admin/purchase', [
            'module' => $module,
        ]);
    }

    /**
     * Stripe redirects here after a successful payment with ?session_id=cs_xxx.
     * Verifies the session with the Green Meteor API and updates the module's
     * purchased state and download URL accordingly.
     *
     * @param string $id Module ID
     * @param string|null $session_id Stripe Checkout Session ID
     */
    public function actionPurchaseSuccess(string $id, ?string $session_id = null): \yii\web\Response|string
    {
        $module = $this->findModule($id);
        $verified = false;
        $apiService = Yii::$app->getModule('bazaar')->getApiService();

        if ($session_id !== null) {
            try {
                $result = $apiService->verifyPurchase($session_id);
                $verified = (bool)($result['verified'] ?? false);

                if ($verified) {
                    $module->isPurchased = true;
                    $module->downloadUrl = $result['download_url'] ?? null;

                    Yii::$app->cache->delete('bazaar_modules_' . md5(
                        (string)(Yii::$app->user->identity->email ?? '')
                    ));
                } else {
                    $this->view->error(Yii::t('BazaarModule.base', 'Payment could not be verified. Please contact support.'));
                }

            } catch (\Throwable $e) {
                $this->view->error(Yii::t('BazaarModule.base', 'Verification error: {error}', ['error' => $e->getMessage()]));
            }
        } else {
            $verified = $module->isPurchased;

            if (!$verified) {
                $verified = $apiService->checkPurchaseStatus((string)$module->id);
                if ($verified) {
                    $module->isPurchased = true;
                    $module->downloadUrl = null;
                }
            }
        }

        return $this->render('@bazaar/views/admin/purchase-success', [
            'module' => $module,
            'verified' => $verified,
        ]);
    }

    /**
     * Downloads and installs a module ZIP into the HumHub modules directory.
     * Sends the configured API key as X-Api-Key so authenticated download
     * endpoints respond with the ZIP rather than an error page.
     * Validates the response is a genuine ZIP (PK magic bytes) before
     * attempting extraction. Requires the module to be purchased or free.
     *
     * @param string $id Module ID
     */
    public function actionInstall(string $id): \yii\web\Response
    {
        $this->forcePostRequest();

        $module = $this->findModule($id);

        if ($module->isSoon) {
            $this->view->error(Yii::t('BazaarModule.base', 'This module is not yet available.'));
            return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
        }

        if ($module->isPaid && !$module->isPurchased) {
            if (!Yii::$app->getModule('bazaar')->isPurchasingEnabled()) {
                $this->view->error(Yii::t('BazaarModule.base', 'Purchasing is currently disabled.'));
                return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
            }

            $this->view->error(Yii::t('BazaarModule.base', 'You must purchase this module before installing it.'));
            return $this->redirect(['/bazaar/admin/purchase', 'id' => $id]);
        }

        if (empty($module->downloadUrl)) {
            $this->view->error(Yii::t('BazaarModule.base', 'No download URL is available for this module.'));
            return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
        }

        try {
            $result = $this->downloadAndInstall($module);

            if ($result['success']) {
                $this->view->success(Yii::t(
                    'BazaarModule.base',
                    '"{name}" has been installed. Enable it under Administration → Modules.',
                    ['name' => $module->name]
                ));
            } else {
                $this->view->error($result['error'] ?? Yii::t('BazaarModule.base', 'Installation failed.'));
            }
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'bazaar');
            $this->view->error(Yii::t('BazaarModule.base', 'Installation error: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
    }

    /**
     * Uploads and installs a module ZIP supplied by an administrator.
     */
    public function actionUpload(): \yii\web\Response|string
    {
        $model = new ModuleUploadForm();
        $model->zipFile = UploadedFile::getInstance($model, 'zipFile');

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $archivePath = $model->upload();

            if ($archivePath !== null) {
                try {
                    $result = $model->install($archivePath);

                    if ($result !== null) {
                        Yii::$app->cache->flush();
                        $this->view->success(Yii::t(
                            'BazaarModule.base',
                            'Module "{name}" version {version} was {action}.',
                            [
                                'name' => (string)($result['manifest']['name'] ?? $result['root']),
                                'version' => (string)($result['manifest']['version'] ?? ''),
                                'action' => !empty($result['replaced']) ? Yii::t('BazaarModule.base', 'updated') : Yii::t('BazaarModule.base', 'installed'),
                            ]
                        ));

                        return $this->redirect(['/bazaar/admin/installed']);
                    }
                } finally {
                    @unlink($archivePath);
                }
            }
        }

        return $this->render('@bazaar/views/admin/upload', ['model' => $model]);
    }

    /**
     * Lists only Bazaar purchases that are also present in the local modules directory.
     * Locally uploaded modules and unrelated third-party modules are intentionally excluded.
     */
    public function actionInstalled(): string
    {
        $installer = Yii::createObject(ModuleInstaller::class);

        return $this->render('@bazaar/views/admin/installed', [
            'modules' => $this->getPurchasedInstalledModules($installer),
        ]);
    }

    /** Enables an installed, purchased Bazaar module. The action is POST-only. */
    public function actionEnableModule(string $id): \yii\web\Response
    {
        $this->forcePostRequest();
        $installer = Yii::createObject(ModuleInstaller::class);

        try {
            $purchasedModule = $this->findPurchasedInstalledModule($installer, $id);
            if ($purchasedModule === null) {
                throw new \yii\web\ForbiddenHttpException(Yii::t(
                    'BazaarModule.base',
                    'Only purchased modules installed locally can be enabled here.'
                ));
            }

            $module = $this->resolveLocalHumHubModule($installer, $id);
            if (!$module instanceof \humhub\components\Module) {
                throw new \yii\web\NotFoundHttpException(Yii::t('BazaarModule.base', 'HumHub could not load this module. Check that its config.php is present and valid.'));
            }

            if (QueueHelper::isQueued(new DisableModuleJob(['moduleId' => $module->id]))) {
                throw new \RuntimeException(Yii::t('BazaarModule.base', 'Module deactivation is still in progress. Please try again shortly.'));
            }
            if (QueueHelper::isQueued(new RemoveModuleJob(['moduleId' => $module->id]))) {
                throw new \RuntimeException(Yii::t('BazaarModule.base', 'Module removal is still in progress. Please try again shortly.'));
            }

            if ($module->enable() === false) {
                throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not enable the module. Check the application logs for details.'));
            }

            Yii::$app->moduleManager->flushCache();
            Yii::$app->cache->flush();
            $this->view->success(Yii::t('BazaarModule.base', 'Module enabled.'));
        } catch (\Throwable $e) {
            Yii::error('Bazaar module enable failed for ' . $id . ': ' . $e->getMessage(), 'bazaar');
            $this->view->error(Yii::t('BazaarModule.base', 'Could not enable module: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['/bazaar/admin/installed']);
    }

    /** Queues deactivation of an installed, purchased Bazaar module. The action is POST-only. */
    public function actionDisableModule(string $id): \yii\web\Response
    {
        $this->forcePostRequest();
        $installer = Yii::createObject(ModuleInstaller::class);

        try {
            $purchasedModule = $this->findPurchasedInstalledModule($installer, $id);
            if ($purchasedModule === null) {
                throw new \yii\web\ForbiddenHttpException(Yii::t(
                    'BazaarModule.base',
                    'Only purchased modules installed locally can be disabled here.'
                ));
            }

            $module = $this->resolveLocalHumHubModule($installer, $id);
            if (!$module instanceof \humhub\components\Module) {
                throw new \yii\web\NotFoundHttpException(Yii::t('BazaarModule.base', 'HumHub could not load this module. Check that its config.php is present and valid.'));
            }

            if (!$module->isEnabled) {
                $this->view->info(Yii::t('BazaarModule.base', 'Module is already disabled.'));
            } else {
                Yii::$app->queue->push(new DisableModuleJob(['moduleId' => $module->id]));
                $this->view->info(Yii::t('BazaarModule.base', 'Module deactivation started. It may take a moment to complete.'));
            }
        } catch (\Throwable $e) {
            Yii::error('Bazaar module disable failed for ' . $id . ': ' . $e->getMessage(), 'bazaar');
            $this->view->error(Yii::t('BazaarModule.base', 'Could not disable module: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['/bazaar/admin/installed']);
    }

    /**
     * Resolves an installed module from its directory, registering its local
     * config on demand when HumHub has not discovered it in the current runtime.
     */
    private function resolveLocalHumHubModule(ModuleInstaller $installer, string $directoryId): ?\humhub\components\Module
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $directoryId)) {
            return null;
        }

        $moduleManager = Yii::$app->moduleManager;

        try {
            $module = $moduleManager->getModule($directoryId, false);
            if ($module instanceof \humhub\components\Module) {
                return $module;
            }

            $modulePath = $installer->getModulesPath() . $directoryId;
            if (!is_dir($modulePath) || !is_file($modulePath . DIRECTORY_SEPARATOR . 'config.php')) {
                return null;
            }

            $registeredId = $moduleManager->register($modulePath);
            if (!is_string($registeredId) || $registeredId === '') {
                return null;
            }

            $module = $moduleManager->getModule($registeredId, false);
            return $module instanceof \humhub\components\Module ? $module : null;
        } catch (\Throwable $e) {
            Yii::debug('Could not register local Bazaar module directory "' . $directoryId . '": ' . $e->getMessage(), 'bazaar');
            return null;
        }
    }

    /** Finds a purchased Bazaar product matched to a local module directory. */
    private function findPurchasedInstalledModule(ModuleInstaller $installer, string $id): ?array
    {
        foreach ($this->getPurchasedInstalledModules($installer) as $module) {
            if (($module['id'] ?? '') === $id && !empty($module['installed'])) {
                return $module;
            }
        }

        return null;
    }

    /** Removes files for a disabled, purchased Bazaar module. The action is POST-only. */
    public function actionRemove(string $id): \yii\web\Response
    {
        $this->forcePostRequest();
        $installer = Yii::createObject(ModuleInstaller::class);

        try {
            $purchasedInstalled = $this->getPurchasedInstalledModules($installer);
            $isEligible = false;
            foreach ($purchasedInstalled as $installedModule) {
                if ($installedModule['id'] === $id) {
                    $isEligible = true;
                    break;
                }
            }

            if (!$isEligible) {
                throw new \yii\web\ForbiddenHttpException(Yii::t(
                    'BazaarModule.base',
                    'Only purchased Bazaar modules that are installed can be removed here.'
                ));
            }

            if (!$installer->deleteModule($id)) {
                throw new \RuntimeException(Yii::t('BazaarModule.base', 'The module directory could not be completely removed.'));
            }

            Yii::$app->cache->flush();
            $this->view->success(Yii::t('BazaarModule.base', 'Module "{id}" files were removed.', ['id' => $id]));
        } catch (\Throwable $e) {
            Yii::error('Bazaar module removal failed for ' . $id . ': ' . $e->getMessage(), 'bazaar');
            $this->view->error(Yii::t('BazaarModule.base', 'Could not remove module: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['/bazaar/admin/installed']);
    }

    /**
     * Returns locally installed modules that the Bazaar API marks as purchased.
     *
     * The current API payload exposes a numeric catalogue ID and does not
     * provide a HumHub directory ID. Prefer an explicit directory identifier
     * when one is available, then use a normalized exact name match between
     * the API product name and the local module manifest/directory name.
     * Ambiguous product names are deliberately not matched.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPurchasedInstalledModules(ModuleInstaller $installer): array
    {
        $installed = $installer->getInstalledModules();

        $apiService = Yii::$app->getModule('bazaar')->getApiService();
        // Force a fresh list so newly completed purchases aren't hidden by a
        // per-user cache entry from before the purchase.
        $apiModules = $apiService->getModules(true);
        $purchasedByDirectoryId = [];
        $purchasedByName = [];

        foreach ($apiModules as $data) {
            if (!is_array($data) || empty($data['isPurchased']) || empty($data['id'])) {
                continue;
            }

            $product = new BazaarModule($data);
            if (!$product->isPurchased) {
                continue;
            }

            // Use an explicit installable module identifier if a future API
            // response supplies one. The current API's `id` is only a catalogue ID.
            $directoryId = trim((string)($data['moduleId'] ?? ''));
            if ($directoryId !== '') {
                $purchasedByDirectoryId[$directoryId] = $product;
            }

            // The API currently returns the product name but not its HumHub
            // directory ID. Index normalized names and only accept unique matches.
            $normalizedName = $this->normalizeModuleIdentifier((string)$product->name);
            if ($normalizedName !== '') {
                $purchasedByName[$normalizedName][] = $product;
            }
        }

        $result = [];
        $matchedProductIds = [];
        foreach ($installed as $localModule) {
            $directoryId = trim((string)($localModule['id'] ?? ''));
            if ($directoryId === '') {
                continue;
            }

            $product = $purchasedByDirectoryId[$directoryId] ?? null;

            if (!$product instanceof BazaarModule) {
                $localNames = [
                    $this->normalizeModuleIdentifier((string)($localModule['name'] ?? '')),
                    $this->normalizeModuleIdentifier($directoryId),
                ];

                foreach (array_unique(array_filter($localNames)) as $normalizedName) {
                    $matches = $purchasedByName[$normalizedName] ?? [];
                    if (count($matches) === 1) {
                        $product = $matches[0];
                        break;
                    }
                }
            }

            if (!$product instanceof BazaarModule || !$product->isPurchased) {
                continue;
            }

            $localModule['bazaarModule'] = $product;
            $localModule['installed'] = true;
            $result[] = $localModule;
            $matchedProductIds[(string)$product->id] = true;
        }

        // The current API exposes catalogue IDs and product names, but not a
        // reliable HumHub directory identifier. Keep every confirmed purchase
        // visible even when its local installation cannot be matched safely.
        // Such rows are informational only and cannot be removed from here.
        foreach ($apiModules as $data) {
            if (!is_array($data) || empty($data['isPurchased']) || empty($data['id'])) {
                continue;
            }

            $productId = (string)$data['id'];
            if (isset($matchedProductIds[$productId])) {
                continue;
            }

            $product = new BazaarModule($data);
            if (!$product->isPurchased) {
                continue;
            }

            // The API catalogue ID is not a local module ID. As a final
            // fallback, check whether a directory named after the normalized
            // product name exists, even if its manifest is missing.
            $directoryId = $this->findModuleDirectoryByName($installer, (string)$product->name);
            $isInstalled = $directoryId !== null;
            $manifestPath = $isInstalled
                ? $installer->getModulesPath() . $directoryId . DIRECTORY_SEPARATOR . 'module.json'
                : null;
            $manifest = $manifestPath && is_file($manifestPath)
                ? json_decode((string)file_get_contents($manifestPath), true)
                : null;

            $result[] = [
                'id' => $directoryId ?? '',
                'name' => (string)$product->name,
                'version' => is_array($manifest) ? (string)($manifest['version'] ?? '') : '',
                'description' => (string)$product->description,
                'enabled' => $isInstalled && $installer->isModuleEnabled($directoryId),
                'path' => $isInstalled ? $installer->getModulesPath() . $directoryId : '',
                'installed' => $isInstalled,
                'bazaarModule' => $product,
            ];
            $matchedProductIds[$productId] = true;
        }

        return $result;
    }

    /**
     * Finds a local module directory by its normalized directory name.
     * This fallback also detects directories whose module.json is missing.
     */
    private function findModuleDirectoryByName(ModuleInstaller $installer, string $name): ?string
    {
        $normalizedName = $this->normalizeModuleIdentifier($name);
        if ($normalizedName === '') {
            return null;
        }

        $modulesPath = $installer->getModulesPath();
        if (!is_dir($modulesPath)) {
            return null;
        }

        // Prefer exact identifier matches. If a product display name is shorter
        // than its local directory (for example "Gravatar" and "gravatar-main"),
        // allow a unique prefix match. Never choose arbitrarily between modules.
        $candidates = [];
        foreach (new \DirectoryIterator($modulesPath) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $directoryId = $entry->getFilename();
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $directoryId)) {
                continue;
            }

            $identifiers = [$directoryId];
            $manifestPath = $entry->getPathname() . DIRECTORY_SEPARATOR . 'module.json';
            if (is_file($manifestPath)) {
                $manifest = json_decode((string)file_get_contents($manifestPath), true);
                if (is_array($manifest)) {
                    $identifiers[] = (string)($manifest['name'] ?? '');
                    $identifiers[] = (string)($manifest['id'] ?? '');
                }
            }

            $bestScore = null;
            foreach ($identifiers as $identifier) {
                $candidateName = $this->normalizeModuleIdentifier($identifier);
                if ($candidateName === '') {
                    continue;
                }

                if ($candidateName === $normalizedName) {
                    $score = 0;
                } elseif (str_starts_with($candidateName, $normalizedName)
                    || str_starts_with($normalizedName, $candidateName)) {
                    $score = 1;
                } else {
                    continue;
                }

                $bestScore = $bestScore === null ? $score : min($bestScore, $score);
            }

            if ($bestScore !== null) {
                $candidates[$directoryId] = $bestScore;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $bestScore = min($candidates);
        $bestMatches = array_keys(array_filter(
            $candidates,
            static fn(int $score): bool => $score === $bestScore
        ));

        return count($bestMatches) === 1 ? $bestMatches[0] : null;
    }

    /**
     * Normalizes a module name or directory identifier for exact matching.
     */
    private function normalizeModuleIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($identifier))) ?? '';
    }

    /**
     * Tests the Green Meteor API connection and returns JSON.
     * Bypasses the cache so the result always reflects the live API state.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function actionTestConnection(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $apiService = Yii::$app->getModule('bazaar')->getApiService();
            $modulesData = $apiService->getModules(
                (string)(Yii::$app->user->identity->email ?? '')
            );

            if (!empty($modulesData)) {
                Yii::$app->cache->delete('bazaar_modules_' . md5(
                    (string)(Yii::$app->user->identity->email ?? '')
                ));

                return [
                    'success' => true,
                    'message' => Yii::t('BazaarModule.base',
                        'Connection successful. {count} modules found.',
                        ['count' => count($modulesData)]
                    ),
                    'count' => count($modulesData),
                ];
            }

            return [
                'success' => false,
                'message' => Yii::t('BazaarModule.base',
                    'Connected but no modules were returned. Please try again later or contact support.'
                ),
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => Yii::t('BazaarModule.base',
                    'Connection failed: {error}', ['error' => $e->getMessage()]
                ),
            ];
        }
    }

    /**
     * GET  – Shows the Bazaar configuration form.
     * POST – Saves settings and redirects to the module index.
     */
    public function actionConfig(): \yii\web\Response|string
    {
        $model = new ConfigureForm();
        $model->loadSettings();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->cache->delete('bazaar_modules_' . md5(
                (string)(Yii::$app->user->identity->email ?? '')
            ));

            $this->view->saved();
            return $this->redirect(['/bazaar/admin/index']);
        }

        return $this->render('@bazaar/views/admin/config', [
            'model' => $model,
        ]);
    }

    /**
     * Flushes the entire application cache, forcing a fresh API fetch for all
     * users on their next request. Accepts GET (link) and POST (AJAX).
     * Uses a full flush because per-user cache keys cannot be enumerated.
     */
    public function actionClearCache(): \yii\web\Response
    {
        Yii::$app->response->on(\yii\web\Response::EVENT_AFTER_SEND, function () {
            try {
                CacheHelper::flushCache();
            } catch (\Throwable $e) {
                Yii::error("Cache flush failed: " . $e->getMessage(), __METHOD__);
            }
        });

        $this->view->success(Yii::t('BazaarModule.base', 'Cache cleared successfully!'));

        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'success' => true,
                'message' => Yii::t('BazaarModule.base', 'Cache cleared successfully!')
            ]);
        }

        return $this->redirect(['/bazaar/admin/index']);
    }

    /**
     * Resolves a module by ID from the API service or throws a 404.
     *
     * @throws NotFoundHttpException
     */
    private function findModule(string $id): BazaarModule
    {
        $module = Yii::$app->getModule('bazaar')->getApiService()->getModule($id);

        if ($module === null) {
            throw new NotFoundHttpException(
                Yii::t('BazaarModule.base', 'The requested module could not be found.')
            );
        }

        return $module;
    }

    /**
     * Downloads the module ZIP from its download URL and extracts it to the
     * HumHub modules directory. Attaches the configured API key as X-Api-Key
     * on the cURL request so authenticated endpoints return the ZIP payload
     * instead of an error or login page. Validates PK magic bytes before
     * passing the file to ZipArchive to prevent misleading extraction errors.
     *
     * @param BazaarModule $module
     * @return array{success: bool, error?: string}
     */
    private function downloadAndInstall(BazaarModule $module): array
    {
        $installer = Yii::createObject(ModuleInstaller::class);
        $modulesPath = $installer->getModulesPath();

        if (!is_dir($modulesPath) || !is_writable($modulesPath)) {
            return [
                'success' => false,
                'error' => Yii::t(
                    'BazaarModule.base',
                    'Modules directory is not writable: {path}',
                    ['path' => $modulesPath]
                ),
            ];
        }

        if (!preg_match('#^https://#i', (string)$module->downloadUrl)) {
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base', 'Only HTTPS module download URLs are permitted.'),
            ];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'bazaar_download_');

        if ($tempFile === false) {
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base', 'Could not create a temporary download file.'),
            ];
        }

        $bazaarModule = Yii::$app->getModule('bazaar');
        $apiKey = (string)$bazaarModule->settings->get('apiKey', $bazaarModule->apiKey ?? '');
        $headers = [
            'Accept: application/zip, application/octet-stream',
            'User-Agent: HumHub-Bazaar/1.1',
        ];

        if ($apiKey !== '') {
            $headers[] = 'X-Api-Key: ' . $apiKey;
        }

        $fp = fopen($tempFile, 'wb');

        if ($fp === false) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base', 'Could not open the temporary download file.'),
            ];
        }

        $ch = curl_init($module->downloadUrl);
        $written = 0;
        $maxSize = ModuleInstaller::MAX_ARCHIVE_SIZE;

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'HumHub-Bazaar/1.1',
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use ($fp, &$written, $maxSize): int {
                $length = strlen($data);

                if ($written + $length > $maxSize) {
                    return 0;
                }

                $written += $length;

                return fwrite($fp, $data);
            },
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curlErr !== '' || $httpCode < 200 || $httpCode >= 300) {
            @unlink($tempFile);

            return [
                'success' => false,
                'error' => Yii::t(
                    'BazaarModule.base',
                    'Download failed (HTTP {code}){error}',
                    ['code' => $httpCode, 'error' => $curlErr !== '' ? ': ' . $curlErr : '']
                ),
            ];
        }

        if ($written === 0 || $written > $maxSize) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base', 'The downloaded module is empty or exceeds the 64 MB limit.'),
            ];
        }

        try {
            $result = $installer->install($tempFile, false);

            Yii::$app->cache->flush();

            Yii::info("Module '{$module->id}' installed to {$modulesPath}", 'bazaar');

            return ['success' => true, 'result' => $result];
        } catch (\Throwable $e) {
            Yii::error(
                "Module '{$module->id}' installation failed: " . $e->getMessage(),
                'bazaar'
            );

            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            @unlink($tempFile);
        }
    }

}