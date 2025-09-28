<?php

namespace humhub\modules\bazaar\controllers;

use Yii;
use humhub\modules\bazaar\models\Module;
use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\bazaar\models\ConfigureForm;
use humhub\modules\bazaar\services\ApiService;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\helpers\FileHelper;

class AdminController extends Controller
{
    /**
     * @inheritdoc
     */
    public $adminOnly = true;

    /**
     * @inheritdoc
     */
    public function getAccessRules()
    {
        return [
            [
                'permission' => ManageSettings::class,
            ]
        ];
    }

    /**
     * Module bazaar index
     */
    public function actionIndex()
    {
        $apiService = new ApiService();
        $modulesData = $apiService->getModules();
        $modules = [];

        foreach ($modulesData as $index => $data) {
            try {
                $moduleData = [
                    'id' => $data['id'] ?? '',
                    'name' => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                    'version' => $data['version'] ?? '1.0.0',
                    'price' => $data['price'] ?? 0,
                    'currency' => $data['currency'] ?? 'USD',
                    'isPaid' => $data['isPaid'] ?? false,
                    'isPurchased' => $data['isPurchased'] ?? false,
                    'isSoon' => $data['isSoon'] ?? false,
                    'category' => $data['category'] ?? 'other',
                    'author' => $data['author'] ?? 'Green Meteor',
                    'screenshots' => $data['screenshots'] ?? [],
                    'features' => $data['features'] ?? [],
                    'requirements' => $data['requirements'] ?? [],
                    'downloadUrl' => $data['downloadUrl'] ?? null,
                ];

                $module = new Module($moduleData);

                $modules[] = $module;
            } catch (\Exception $e) {
                Yii::error("Failed to create module from data: " . json_encode($data) . 
                    " Error: " . $e->getMessage(), 'bazaar');
            }
        }

        $categories = [];
        foreach ($modules as $module) {
            $categories[$module->category] = $module->getCategoryLabel();
        }

        return $this->render('index', [
            'modules' => $modules,
            'categories' => $categories,
        ]);
    }

    /**
     * Module details
     */
    public function actionView($id)
    {
        $apiService = new ApiService();
        $module = $apiService->getModule($id);

        if (!$module) {
            throw new NotFoundHttpException();
        }

        return $this->render('view', [
            'module' => $module,
        ]);
    }

    /**
     * Install module (download and extract)
     */
    public function actionInstall($id)
    {
        $apiService = new ApiService();
        $module = $apiService->getModule($id);

        if (!$module) {
            throw new NotFoundHttpException();
        }

        if ($module->isPaid && !$module->isPurchased) {
            Yii::$app->session->setFlash('error', 
                Yii::t('BazaarModule.base', 'You must purchase this module before installing it.'));
            return $this->redirect(['view', 'id' => $id]);
        }

        if (!$module->downloadUrl) {
            Yii::$app->session->setFlash('error', 
                Yii::t('BazaarModule.base', 'Download URL not available for this module.'));
            return $this->redirect(['view', 'id' => $id]);
        }

        try {
            $tempDir = Yii::getAlias('@runtime/bazaar_temp');
            FileHelper::createDirectory($tempDir);

            $tempFile = $tempDir . '/' . $module->id . '.zip';
            $modulesDir = Yii::getAlias('@protected/modules');
            $downloadSuccess = $this->downloadFile($module->downloadUrl, $tempFile);

            if (!$downloadSuccess) {
                throw new \Exception('Failed to download module file');
            }

            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                throw new \Exception('Failed to open module zip file');
            }

            $moduleDir = $modulesDir . '/' . $this->getModuleDirName($module, $zip);

            if (is_dir($moduleDir)) {
                $zip->close();
                unlink($tempFile);

                Yii::$app->session->setFlash('warning', 
                    Yii::t('BazaarModule.base', 'Module "{name}" is already installed. Please uninstall the existing version first.', 
                        ['name' => $module->name]));
                return $this->redirect(['view', 'id' => $id]);
            }

            if (!$zip->extractTo($modulesDir)) {
                throw new \Exception('Failed to extract module files');
            }

            $zip->close();
            unlink($tempFile);

            if (Yii::$app->hasModule('modules') && Yii::$app->getModule('modules')) {
                Yii::$app->getModule('modules')->flushCache();
            }

            Yii::$app->session->setFlash('success', 
                Yii::t('BazaarModule.base', 'Module "{name}" has been successfully installed! You can now enable it in Administration > Modules.', 
                    ['name' => $module->name]));

        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }

            Yii::$app->session->setFlash('error', 
                Yii::t('BazaarModule.base', 'Installation failed: {error}', ['error' => $e->getMessage()]));
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Download file from URL
     */
    private function downloadFile($url, $destination)
    {
        try {
            $client = new \yii\httpclient\Client();

            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions([
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_USERAGENT => 'HumHub-Bazaar/1.0',
                ])
                ->send();

            if ($response->isOk) {
                file_put_contents($destination, $response->content);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Yii::error("Download error: " . $e->getMessage(), 'bazaar');
            return false;
        }
    }

    /**
     * Determine module directory name from zip contents
     */
    private function getModuleDirName($module, \ZipArchive $zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (preg_match('#^([^/]+)/(module\.json|config\.php)$#', $filename, $matches)) {
                return $matches[1];
            }
        }

        return $module->id;
    }

    /**
     * Module configuration
     */
    public function actionConfig()
    {
        $model = new ConfigureForm();
        $model->loadSettings();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->view->saved();
            return $this->redirect(['config']);
        }

        return $this->render('config', [
            'model' => $model,
        ]);
    }

    /**
     * Purchase module - FIXED VERSION
     */
    public function actionPurchase($id)
    {
        $moduleConfig = Yii::$app->getModule('bazaar');

        if (!$moduleConfig->enablePurchasing) {
            throw new ForbiddenHttpException();
        }

        $apiService = new ApiService();
        $moduleData = $apiService->getModule($id);

        if (!$moduleData) {
            throw new NotFoundHttpException();
        }

        if (Yii::$app->request->isPost) {

            try {
                $baseUrl = Yii::$app->request->hostInfo;
                $returnUrl = $baseUrl . '/bazaar/admin/purchase-success?module_id=' . $id;
                $cancelUrl = $baseUrl . '/bazaar/admin/view?id=' . $id;

                $result = $apiService->purchaseModule($id, [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ]);

                if (isset($result['success']) && $result['success']) {
                    if (isset($result['is_free']) && $result['is_free']) {
                        Yii::$app->session->setFlash('success', 
                            Yii::t('BazaarModule.base', 'Free module marked as purchased!'));
                        return $this->redirect(['view', 'id' => $id]);
                    }

                    if (isset($result['checkout_url'])) {
                        if (isset($result['session_id'])) {
                            Yii::$app->session->set('stripe_session_' . $id, $result['session_id']);
                        }

                        return $this->redirect($result['checkout_url']);
                    }

                    Yii::$app->session->setFlash('success', 
                        Yii::t('BazaarModule.base', 'Module purchased successfully!'));
                    return $this->redirect(['view', 'id' => $id]);
                }

                Yii::$app->session->setFlash('error', 
                    Yii::t('BazaarModule.base', 'Purchase failed: Unexpected response. Please try again or contact support.'));

            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 
                    Yii::t('BazaarModule.base', 'Purchase failed: {error}', ['error' => $e->getMessage()]));
            }
        }

        return $this->render('purchase', [
            'module' => $moduleData,
        ]);
    }

    /**
     * Handle successful purchase return from Stripe
     */
    public function actionPurchaseSuccess($module_id, $session_id = null)
    {
        if ($session_id) {
            try {
                $apiService = new ApiService();

                Yii::$app->session->setFlash('success', 
                    Yii::t('BazaarModule.base', 'Purchase completed successfully! You can now download your module.'));

                return $this->redirect(['view', 'id' => $module_id]);
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 
                    Yii::t('BazaarModule.base', 'Purchase verification failed. Please contact support.'));
            }
        }

        return $this->redirect(['view', 'id' => $module_id]);
    }

    /**
     * Clear cache
     */
    public function actionClearCache()
    {
        Yii::$app->cache->delete('bazaar_modules');

        if (Yii::$app->cache->flush()) {
            Yii::$app->session->setFlash('success', 
                Yii::t('BazaarModule.base', 'Cache cleared successfully!'));
        } else {
            Yii::$app->session->setFlash('error', 
                Yii::t('BazaarModule.base', 'Failed to clear cache.'));
        }

        return $this->redirect(['index']);
    }
}