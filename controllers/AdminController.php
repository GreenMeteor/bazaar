<?php

namespace humhub\modules\bazaar\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use humhub\modules\admin\components\Controller;
use humhub\modules\bazaar\models\Module as BazaarModule;
use humhub\modules\bazaar\models\ConfigureForm;
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

        if (!$bazaarModule->enablePurchasing) {
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
        $module = $this->findModule($id);

        if ($module->isSoon) {
            $this->view->error(Yii::t('BazaarModule.base', 'This module is not yet available.'));

            return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
        }

        if ($module->isPaid && !$module->isPurchased) {
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
                $this->view->success(Yii::t('BazaarModule.base',
                    '"{name}" has been installed. Enable it under Administration → Modules.',
                    ['name' => $module->name]
                ));
            } else {
                $this->view->error($result['error'] ?? Yii::t('BazaarModule.base', 'Installation failed.'));
            }

        } catch (\Throwable $e) {
            $this->view->error(Yii::t('BazaarModule.base', 'Installation error: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['/bazaar/admin/view', 'id' => $id]);
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
        $modulesPath = Yii::getAlias('@app') . '/modules/';

        if (!is_dir($modulesPath) || !is_writable($modulesPath)) {
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base',
                    'Modules directory is not writable: {path}', ['path' => $modulesPath]
                ),
            ];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'bazaar_') . '.zip';
        $apiKey = Yii::$app->getModule('bazaar')->apiKey ?? '';

        $headers = [
            'Accept: application/zip, application/octet-stream',
            'User-Agent: HumHub-Bazaar/1.0',
        ];

        if ($apiKey !== '') {
            $headers[] = 'X-Api-Key: ' . $apiKey;
        }

        $ch = curl_init($module->downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $content = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '' || $httpCode !== 200) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base',
                    'Download failed (HTTP {code}){error}',
                    ['code' => $httpCode, 'error' => $curlErr ? ": $curlErr" : '']
                ),
            ];
        }

        if (substr($content, 0, 2) !== 'PK') {
            Yii::error(
                "Module '{$module->id}' download is not a ZIP. Response preview: " . substr($content, 0, 500),
                'bazaar'
            );
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base',
                    'Downloaded file is not a valid ZIP. The server returned an unexpected response — check the application log for details.'
                ),
            ];
        }

        if (file_put_contents($tempFile, $content) === false) {
            return ['success' => false, 'error' => Yii::t('BazaarModule.base', 'Could not save downloaded file.')];
        }

        $zip = new \ZipArchive();
        $res = $zip->open($tempFile);

        if ($res !== true) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error' => Yii::t('BazaarModule.base', 'Failed to open ZIP (code: {code}).', ['code' => $res]),
            ];
        }

        $zip->extractTo($modulesPath);
        $zip->close();
        @unlink($tempFile);

        Yii::$app->cache->flush();
        Yii::info("Module '{$module->id}' installed to {$modulesPath}", 'bazaar');

        return ['success' => true];
    }
}