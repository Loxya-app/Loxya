<?php
declare(strict_types=1);

namespace Robert2\API\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

use Robert2\Install;
use Robert2\API\Errors;
use Robert2\API\Config;
use Robert2\API\Models;
use Robert2\API\I18n\I18n;

class HomeController
{
    public function __construct($container)
    {
        $this->_bindTwig($container);

        $this->router = $container->get('router');
        $this->config = $container->get('settings');
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Main routes methods
    // —
    // ——————————————————————————————————————————————————————

    public function entrypoint(Request $request, Response $response)
    {
        if (!Config\Config::customConfigExists()) {
            return $response->withRedirect('/install', 307); // 307 is "Temporary Redirect"
        }
        return $this->view->render($response, 'entrypoint.twig');
    }

    public function install(Request $request, Response $response)
    {
        $installProgress = Install\Install::getInstallProgress();
        $currentStep = $installProgress['step'];
        $stepData = [];
        $error = false;
        $validationErrors = null;

        $i18n = new I18n();
        $lang = $i18n->getCurrentLocale();
        $allCurrencies = Install\Install::getAllCurrencies();

        if ($request->isGet() && $currentStep === 'welcome') {
            return $this->view->render(
                $response,
                'install.twig',
                $this->_getCheckRequirementsData() + ['lang' => $lang]
            );
        }

        if ($request->isPost()) {
            $installData = $request->getParsedBody();

            if ($currentStep === 'settings') {
                $installData['currency'] = $allCurrencies[$installData['currency']];
            }

            try {
                $installProgress = Install\Install::setInstallProgress($currentStep, $installData);

                if ($currentStep === 'company') {
                    $this->_validateCompanyData($installData);
                }

                if ($currentStep === 'database') {
                    $settings = Install\Install::getSettingsFromInstallData();
                    Config\Config::saveCustomConfig($settings, true);
                    Config\Config::getPDO(); // - Try to connect to DB
                }

                if ($currentStep === 'dbStructure') {
                    Install\Install::createMissingTables();
                    Install\Install::insertInitialDataIntoDB();
                }

                if ($currentStep === 'adminUser') {
                    $user = new Models\User();
                    if (array_key_exists('skipUserCreation', $installData)
                        && $installData['skipUserCreation'] === 'yes'
                    ) {
                        $existingAdmins = $user->getAll()->where('group_id', 'admin')->get()->toArray();
                        if (empty($existingAdmins)) {
                            throw new \InvalidArgumentException(
                                "At least one user must exists. Please create an admin user."
                            );
                        }
                    } else {
                        $installData['user']['group_id'] = 'admin';
                        $user->edit(null, $installData['user']);
                    }
                }

                if ($currentStep === 'categories') {
                    $categories = explode(',', $installData['categories']);
                    $Category = new Models\Category();
                    $Category->bulkAdd(array_unique($categories));
                }
            } catch (\Exception $e) {
                $installProgress = Install\Install::setInstallProgress($currentStep);

                $stepData = $installData;
                $error = $e->getMessage();
                if (method_exists($e, 'getValidationErrors')) {
                    $error = "validationErrors";
                    $validationErrors = $e->getValidationErrors();
                }

                if ($currentStep === 'database') {
                    Config\Config::deleteCustomConfig();
                }
            }
        }

        if ($installProgress['step'] === 'coreSettings' && empty($stepData['JWTSecret'])) {
            $stepData['JWTSecret'] = md5(uniqid('Robert2', true));
        }

        if ($installProgress['step'] === 'settings') {
            $stepData['currencies'] = $allCurrencies;
        }

        if ($installProgress['step'] === 'dbStructure') {
            try {
                $stepData = [
                    'migrationStatus' => Install\Install::getMigrationsStatus(),
                    'canProcess'      => true
                ];
            } catch (\Exception $e) {
                $stepData = [
                    'migrationStatus' => [],
                    'errorCode'       => $e->getCode(),
                    'error'           => $e->getMessage(),
                    'canProcess'      => false
                ];
            }
        }

        if ($installProgress['step'] === 'adminUser') {
            $user = new Models\User();
            $stepData['existingAdmins'] = $user->getAll()->where('group_id', 'admin')->get()->toArray();
        }

        return $this->view->render($response, 'install.twig', [
            'lang'             => $lang,
            'step'             => $installProgress['step'],
            'stepNumber'       => array_search($installProgress['step'], Install\Install::INSTALL_STEPS),
            'error'            => $error,
            'validationErrors' => $validationErrors,
            'stepData'         => $stepData,
            'config'           => $this->config,
        ]);
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Private methods
    // —
    // ——————————————————————————————————————————————————————

    private function _bindTwig($container): void
    {
        $this->view = new \Slim\Views\Twig(VIEWS_FOLDER, [
            'cache' => false,
        ]);

        $this->view->addExtension(new \Slim\Views\TwigExtension(
            $container->router,
            $container->request->getUri()
        ));

        $this->_bindCustomTwigFunctions();
    }

    private function _getCheckRequirementsData(): array
    {
        $phpVersion = PHP_VERSION;
        if (strpos(PHP_VERSION, '+')) {
            $phpVersion = substr(PHP_VERSION, 0, strpos(PHP_VERSION, '+'));
        }

        $phpversionOK = version_compare(PHP_VERSION, '7.1.0') >= 0;
        $loadedExtensions = get_loaded_extensions();
        $neededExstensions = Install\Install::REQUIRED_EXTENSIONS;
        $missingExtensions = array_diff($neededExstensions, $loadedExtensions);

        return [
            'step'              => 'welcome',
            'phpVersion'        => $phpVersion,
            'phpVersionOK'      => $phpversionOK,
            'missingExtensions' => $missingExtensions,
            'requirementsOK'    => $phpversionOK && empty($missingExtensions),
        ];
    }

    private function _bindCustomTwigFunctions(): void
    {
        $i18n = new I18n();
        $translate = new \Twig\TwigFunction('translate', [$i18n, 'translate']);
        $version = new \Twig\TwigFunction('version', $this->getVersion());
        $serverConfig = new \Twig\TwigFunction('serverConfig', $this->getServerConfig());

        $this->view->getEnvironment()->addFunction($translate);
        $this->view->getEnvironment()->addFunction($version);
        $this->view->getEnvironment()->addFunction($serverConfig);
    }

    private function _validateCompanyData($companyData): void
    {
        $errors = [];
        foreach (['name', 'street', 'zipCode', 'locality'] as $mandatoryfield) {
            if (empty($companyData[$mandatoryfield])) {
                $errors[$mandatoryfield] = "Missing value";
                continue;
            }
        }

        if (empty($errors)) {
            return;
        }

        $error = new Errors\ValidationException();
        $error->setValidationErrors($errors);
        throw $error;
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Custom twig functions methods
    // —
    // ——————————————————————————————————————————————————————

    protected function getVersion(): callable
    {
        return function (): string {
            return trim(file_get_contents(PUBLIC_FOLDER . DS . 'version.txt'));
        };
    }

    protected function getServerConfig(): callable
    {
        $rawConfig = Config\Config::getSettings();
        $config = [
            'baseUrl' => $rawConfig['apiUrl'],
            'api'     => [
                'url'     => $rawConfig['apiUrl'] . '/api',
                'headers' => $rawConfig['apiHeaders'],
                'version' => $this->getVersion()()
            ],
            'defaultPaginationLimit' => $rawConfig['maxItemsPerPage'],
            'defaultLang'            => $rawConfig['defaultLang'],
            'currency'               => $rawConfig['currency'],
            'beneficiaryTagName'     => $rawConfig['defaultTags']['beneficiary'],
            'technicianTagName'      => $rawConfig['defaultTags']['technician'],
            'billingMode'            => $rawConfig['billingMode'],
            'degressiveRate'         => sprintf(
                'function (daysCount) { return %s; }',
                $rawConfig['degressiveRateFunction']
            ),
        ];

        return function () use ($config): string {
            $jsonConfig = json_encode($config, Config\Config::JSON_OPTIONS);
            $jsonConfig = preg_replace('/"degressiveRate": "/', '"degressiveRate": ', $jsonConfig);
            return preg_replace('/}"/', '}', $jsonConfig);
        };
    }
}
