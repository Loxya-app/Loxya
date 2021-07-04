<?php
declare(strict_types=1);

namespace Robert2\API;

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Robert2\API\Config\Config;
use Robert2\API\Errors\ErrorHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

/**
 * App.
 *
 * @method self add(\Psr\Http\Server\MiddlewareInterface|string|callable $middleware)
 * @method Response handle(Request $request)
 * @method void run(Request|null $request = null)
 */
class App
{
    private $container;
    private $app;

    public function __construct()
    {
        $this->container = static::createContainer();

        $this->app = AppFactory::create(null, $this->container);
        $this->app->addBodyParsingMiddleware();

        $this->configureRouter();
        $this->configureMiddlewares();
        $this->configureErrorHandlers();
        $this->configureCors();
    }

    public function __call($name, $arguments)
    {
        return \call_user_func_array([$this->app, $name], $arguments);
    }

    // ------------------------------------------------------
    // -
    // -    Internal Methods
    // -
    // ------------------------------------------------------

    protected function configureCors()
    {
        $isCORSEnabled = (bool)$this->container->get('settings')['enableCORS'];
        if (isTestMode() || !$isCORSEnabled) {
            return;
        }

        $this->app->add(function (Request $request, RequestHandler $handler): ResponseInterface {
            /** @var \Slim\Http\Response */
            $response = $handler->handle($request);

            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            $response = $response->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization'
            );

            return $response;
        });
    }

    protected function configureRouter()
    {
        $settings = $this->container->get('settings');
        $isCORSEnabled = (bool)$settings['enableCORS'] && !isTestMode();
        $useRouterCache = (
            (bool)$settings['useRouterCache']
            && !isTestMode()
            && Config::getEnv() !== 'development'
        );

        // - Route cache
        if ($useRouterCache) {
            $routeCollector = $this->app->getRouteCollector();
            $routeCollector->setCacheFile(VAR_FOLDER . DS . 'cache' . DS . 'routes.php');
        }

        // - Middleware
        $this->app->addRoutingMiddleware();

        //
        // -- Routes: Api
        //

        $getActionFqdn = function ($action) {
            return sprintf('Robert2\\API\\Controllers\\%s', $action);
        };

        $this->app->group('/api', function (RouteCollectorProxy $group) use ($isCORSEnabled, $getActionFqdn) {
            // - Autorise les requêtes de type OPTIONS sur les routes d'API.
            if ($isCORSEnabled) {
                $group->options('/{routes:.+}', function (Request $request, Response $response) {
                    return $response;
                });
            }

            // - Toutes les routes d'API sont définies dans le fichier `Config/routes.php`.
            $routeMethods = include CONFIG_FOLDER . DS . 'routes.php';
            foreach ($routeMethods as $method => $routes) {
                foreach ($routes as $route => $action) {
                    $group->$method($route, $getActionFqdn($action));
                }
            }

            // - Not found API
            $group->any('/[{path:.*}]', function (Request $request) {
                throw new HttpNotFoundException($request);
            });
        });

        //
        // -- Routes: "statics"
        //

        $this->app->map(['GET', 'POST'], '/install', $getActionFqdn('SetupController:index'))
            ->setName('install');

        // - Download files
        $this->app->get('/bills/{id:[0-9]+}/pdf[/]', $getActionFqdn('BillController:getOnePdf'))
            ->setName('getBillPdf');
        $this->app->get('/estimates/{id:[0-9]+}/pdf[/]', $getActionFqdn('EstimateController:getOnePdf'))
            ->setName('getEstimatePdf');
        $this->app->get('/events/{id:[0-9]+}/pdf[/]', $getActionFqdn('EventController:getOnePdf'))
            ->setName('getEventPdf');
        $this->app->get('/documents/{id:[0-9]+}/download[/]', $getActionFqdn('DocumentController:getOne'))
            ->setName('getDocumentFile');
        $this->app->get('/materials/{id:[0-9]+}/picture[/]', $getActionFqdn('MaterialController:getPicture'))
            ->setName('getMaterialPicture');
        $this->app->get('/materials/pdf[/]', $getActionFqdn('MaterialController:getAllPdf'))
            ->setName('getMaterialsListPdf');

        // - Login services
        $this->app->get('/logout', $getActionFqdn('AuthController:logout'));

        // - All remaining non-API routes should be handled by Front-End Router
        $this->app->get('/[{path:.*}]', $getActionFqdn('EntryController:index'));
    }

    protected function configureMiddlewares()
    {
        $this->app->add(new Middlewares\Acl);
        $this->app->add([$this->container->get('auth'), 'middleware']);
        $this->app->add(new Middlewares\Pagination);
    }

    protected function configureErrorHandlers()
    {
        $shouldLog = !isTestMode();
        $displayErrorDetails = (
            (bool)$this->container->get('settings')['displayErrorDetails']
            || isTestMode()
            || Config::getEnv() === 'development'
        );

        $logger = $this->container->get('logger')->createLogger('error');
        $errorMiddleware = $this->app->addErrorMiddleware($displayErrorDetails, $shouldLog, $shouldLog, $logger);

        // - Default error handler.
        $defaultErrorHandler = new ErrorHandler(
            $this->app->getCallableResolver(),
            $this->app->getResponseFactory(),
            $logger
        );
        $errorMiddleware->setDefaultErrorHandler($defaultErrorHandler);
    }

    // ------------------------------------------------------
    // -
    // -    Internal static Methods
    // -
    // ------------------------------------------------------

    protected static function createContainer()
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(CONFIG_FOLDER . DS . 'definitions.php');

        $container = $builder->build();

        //
        // - Settings
        //

        $container->set('settings', Config::getSettings());

        return $container;
    }
}
