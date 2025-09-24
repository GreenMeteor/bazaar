<?php

namespace humhub\modules\bazaar\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use humhub\modules\bazaar\services\ApiService;
use humhub\modules\bazaar\models\Module;

/**
 * AdminController for Bazaar module
 */
class AdminController extends Controller
{
    /**
     * @var ApiService
     */
    private $apiService;

    public function init()
    {
        parent::init();
        $this->apiService = new ApiService();
    }

    /**
     * Display modules list
     * @return string
     */
    public function actionIndex()
    {
        $search = Yii::$app->request->get('search', '');
        $category = Yii::$app->request->get('category', '');
        $sort = Yii::$app->request->get('sort', 'name');

        // Get modules from API
        $modulesData = $this->apiService->getModules();
        
        // FIXED: Convert all array data to Module objects for consistent handling
        $modules = [];
        foreach ($modulesData as $moduleData) {
            if (is_array($moduleData)) {
                $modules[] = Module::fromArray($moduleData);
            } elseif ($moduleData instanceof Module) {
                $modules[] = $moduleData;
            }
        }

        // Apply filters
        if ($search) {
            $modules = array_filter($modules, function($module) use ($search) {
                return stripos($module->name, $search) !== false || 
                       stripos($module->description, $search) !== false;
            });
        }

        if ($category) {
            $modules = array_filter($modules, function($module) use ($category) {
                return $module->category === $category;
            });
        }

        // Apply sorting
        usort($modules, function($a, $b) use ($sort) {
            switch ($sort) {
                case 'price':
                    return $a->price <=> $b->price;
                case 'category':
                    return strcmp($a->category, $b->category);
                case 'name':
                default:
                    return strcmp($a->name, $b->name);
            }
        });

        // Get categories for filter dropdown
        $categories = [];
        foreach ($modules as $module) {
            if (!isset($categories[$module->category])) {
                $categories[$module->category] = $module->getCategoryLabel();
            }
        }

        return $this->render('index', [
            'modules' => $modules,
            'categories' => $categories,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
        ]);
    }

    /**
     * View module details
     * @param string $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $moduleData = $this->apiService->getModule($id);
        
        if (!$moduleData) {
            throw new NotFoundHttpException('Module not found');
        }

        // FIXED: Ensure we have a Module object with proper price handling
        if (is_array($moduleData)) {
            $module = Module::fromArray($moduleData);
        } else {
            $module = $moduleData;
        }

        return $this->render('view', [
            'module' => $module,
        ]);
    }

    /**
     * Purchase module
     * @param string $id
     * @return string|Response
     * @throws NotFoundHttpException
     */
    public function actionPurchase($id)
    {
        $moduleData = $this->apiService->getModule($id);
        
        if (!$moduleData) {
            throw new NotFoundHttpException('Module not found');
        }

        // FIXED: Convert to Module object for consistent handling
        if (is_array($moduleData)) {
            $module = Module::fromArray($moduleData);
        } else {
            $module = $moduleData;
        }

        // Check if module is available for purchase
        if (!$module->isAvailableForPurchase()) {
            Yii::$app->session->addFlash('error', 
                Yii::t('BazaarModule.base', 'This module is not available for purchase'));
            return $this->redirect(['view', 'id' => $id]);
        }

        if (Yii::$app->request->isPost) {
            try {
                $options = [
                    'return_url' => Yii::$app->request->hostInfo . '/bazaar/admin/purchase-success?id=' . $id,
                    'cancel_url' => Yii::$app->request->hostInfo . '/bazaar/admin/view?id=' . $id,
                ];

                $result = $this->apiService->purchaseModule($id, $options);

                if (isset($result['checkout_url'])) {
                    return $this->redirect($result['checkout_url']);
                } elseif (isset($result['is_free']) && $result['is_free']) {
                    Yii::$app->session->addFlash('success', 
                        Yii::t('BazaarModule.base', 'Module downloaded successfully'));
                    return $this->redirect($result['download_url']);
                }

            } catch (\Exception $e) {
                Yii::error('Purchase error: ' . $e->getMessage(), 'bazaar');
                Yii::$app->session->addFlash('error', 
                    Yii::t('BazaarModule.base', 'Purchase failed: {error}', ['error' => $e->getMessage()]));
            }
        }

        return $this->render('purchase', [
            'module' => $module,
        ]);
    }

    /**
     * Purchase success callback
     * @param string $id
     * @return string|Response
     */
    public function actionPurchaseSuccess($id)
    {
        $sessionId = Yii::$app->request->get('session_id');
        $verified = false;
        $verificationError = null;
        
        if ($sessionId) {
            // Verify purchase with API
            $verificationResult = $this->apiService->verifyPurchase($sessionId);
            $verified = $verificationResult['verified'] ?? false;
            $verificationError = $verificationResult['error'] ?? null;
            
            if ($verified) {
                $this->apiService->clearCache(); // Clear cache to refresh purchase status
                Yii::$app->session->addFlash('success', 
                    Yii::t('BazaarModule.base', 'Purchase verified and completed successfully!'));
            } else {
                Yii::$app->session->addFlash('warning', 
                    Yii::t('BazaarModule.base', 'Purchase verification pending. Please contact support if issues persist.'));
                if ($verificationError) {
                    Yii::error('Purchase verification failed: ' . $verificationError, 'bazaar');
                }
            }
        } else {
            Yii::$app->session->addFlash('info', 
                Yii::t('BazaarModule.base', 'Purchase completed! Downloads will be available once payment is processed.'));
        }

        $moduleData = $this->apiService->getModule($id);
        
        if (is_array($moduleData)) {
            $module = Module::fromArray($moduleData);
        } else {
            $module = $moduleData;
        }

        return $this->render('purchase-success', [
            'module' => $module,
            'sessionId' => $sessionId,
            'verified' => $verified,
        ]);
    }

    /**
     * Configure module settings
     * @return string|Response
     */
    public function actionConfig()
    {
        $model = new \humhub\modules\bazaar\models\ConfigureForm();
        $model->loadFromModule();
        
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                Yii::$app->session->addFlash('success', 
                    Yii::t('BazaarModule.base', 'Configuration saved successfully'));
                
                // Clear cache after config changes
                $this->apiService->clearCache();
                
                return $this->redirect(['config']);
            }
        }

        return $this->render('config', [
            'model' => $model,
        ]);
    }

    /**
     * Clear module cache
     * @return Response
     */
    public function actionClearCache()
    {
        $this->apiService->clearCache();
        
        Yii::$app->session->addFlash('success', 
            Yii::t('BazaarModule.base', 'Cache cleared successfully'));
        
        return $this->redirect(['index']);
    }

    /**
     * Test API connection
     * @return array
     */
    public function actionTestApi()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            // Get API settings from POST (from config form) or use current module settings
            $apiUrl = Yii::$app->request->post('api_url');
            $apiKey = Yii::$app->request->post('api_key');
            
            if (!$apiUrl) {
                $module = Yii::$app->getModule('bazaar');
                $apiUrl = $module->apiBaseUrl ?? 'https://greenmeteor.net/api/modules.php';
                $apiKey = $module->apiKey ?? '';
            }
            
            // Create test client with provided settings
            $client = new \yii\httpclient\Client([
                'baseUrl' => $apiUrl,
            ]);
            
            $headers = [
                'Accept' => 'application/json',
                'User-Agent' => 'HumHub-Bazaar/1.0',
            ];
            
            if ($apiKey) {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            }
            
            $response = $client->get('', [
                'action' => 'list',
                'format' => 'json',
            ])->setHeaders($headers)->send();
            
            if ($response->isOk) {
                $data = $response->getData();
                if (isset($data['success']) && $data['success']) {
                    return [
                        'success' => true,
                        'message' => Yii::t('BazaarModule.base', 'API connection successful - Found {count} modules', [
                            'count' => count($data['data'] ?? [])
                        ]),
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => Yii::t('BazaarModule.base', 'API responded but returned an error: {error}', [
                            'error' => $data['error'] ?? 'Unknown error'
                        ]),
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => Yii::t('BazaarModule.base', 'API connection failed: HTTP {code}', [
                        'code' => $response->statusCode
                    ]),
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => Yii::t('BazaarModule.base', 'API connection failed: {error}', [
                    'error' => $e->getMessage()
                ]),
            ];
        }
    }

    /**
     * Debug action to see raw API data - Add this to AdminController.php temporarily
     * @return string
     */
    public function actionDebugApi()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            // Get raw API response
            $response = $this->apiService->_client->get('', [
                'action' => 'list',
                'format' => 'json',
            ])->send();
            
            if ($response->isOk) {
                $rawData = $response->getData();
                
                // Show first module's raw data for debugging
                $firstModule = $rawData['data'][0] ?? null;
                
                return [
                    'success' => true,
                    'raw_response' => $rawData,
                    'first_module_raw' => $firstModule,
                    'first_module_price' => $firstModule['price'] ?? 'NOT SET',
                    'first_module_is_paid' => $firstModule['is_paid'] ?? 'NOT SET',
                    'first_module_type_price' => gettype($firstModule['price'] ?? null),
                    'first_module_type_is_paid' => gettype($firstModule['is_paid'] ?? null),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $response->statusCode,
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}