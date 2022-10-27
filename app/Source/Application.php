<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\Migrations\Configuration\Connection\ConnectionLoader;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use GuzzleHttp\Psr7\ServerRequest;
use JetBrains\PhpStorm\Pure;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\PsrHandler;
use Monolog\Level;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\Psr17\ServerRequestCreator;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\ResponseEmitter;
use Slim\Routing\Dispatcher;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use TrayDigita\Streak\Source\ACL\Lists;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\ObjectFileReader;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Json\EncodeDecode;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Controller\Collector as ControllerCollector;
use TrayDigita\Streak\Source\Controller\Storage as ControllerStorage;
use TrayDigita\Streak\Source\Module\Collector as ModuleCollector;
use TrayDigita\Streak\Source\Module\Storage as ModuleStorage;
use TrayDigita\Streak\Source\Middleware\Collector as MiddlewareCollector;
use TrayDigita\Streak\Source\Middleware\Storage as MiddlewareStorage;
use TrayDigita\Streak\Source\RouteAnnotations\Collector as AnnotationCollector;
use TrayDigita\Streak\Source\Session\Driver\DefaultDriver;
use TrayDigita\Streak\Source\Session\Sessions;
use TrayDigita\Streak\Source\Scheduler\Scheduler;
use TrayDigita\Streak\Source\Traits\ComposerLoaderObject;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Views\Html\Renderer;
use WoohooLabs\Yin\JsonApi\Exception\DefaultExceptionFactory;
use WoohooLabs\Yin\JsonApi\Exception\ExceptionFactoryInterface;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequest;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequestInterface;
use WoohooLabs\Yin\JsonApi\Serializer\DeserializerInterface;
use WoohooLabs\Yin\JsonApi\Serializer\JsonDeserializer;

/**
 * @mixin App
 */
class Application implements ContainerizeInterface
{
    use Containerize,
        ComposerLoaderObject;

    final const VERSION = '1.0.0';

    public readonly string $version;

    /**
     * Application Name
     *
     * @var string
     */
    public readonly string $name;

    /**
     * @var Container
     * @readonly
     */
    public readonly Container $container;

    /**
     * @var int
     * @readonly
     */
    public readonly int $initialMemoryUsage;

    /**
     * @var array
     * @readonly
     */
    public readonly array $defaultConfigurations;

    /**
     * @var string
     */
    protected string $defaultCurrentResponseType = 'text/html';

    /**
     * @var string
     */
    private string $deploymentEnvironment = 'production';

    /**
     * @var bool
     */
    protected bool $middlewareAttached = false;
    /**
     * @var bool
     */
    protected bool $moduleDispatched = false;

    /**
     * @var bool
     */
    protected bool $controllerAttached = false;
    private ?ResponseInterface $response = null;

    /**
     * @param ?Container $container will create factory container
     */
    public function __construct(Container $container = null)
    {
        $this->version = self::VERSION;
        $this->name    = 'Streak!';
        $this->defaultConfigurations = [
            'application' => [
                'environment' => 'production',
                'siteUrl' => null,
                'language' => null,
            ],
            'json' => [
                'pretty' => null,
                'displayVersion' => true,
            ],
            'logging' => [
                'enable' => null,
                'level'   => Level::Warning,
            ],
            'error' => [
                'hidePath' => true,
            ],
            'security' => [
                'salt' => null,
                'secret' => null,
            ],
            'path' => [
                'api'    => StoragePath::DEFAULT_API_PATH,
                'admin'  => StoragePath::DEFAULT_ADMIN_PATH,
                'member' => StoragePath::DEFAULT_MEMBER_PATH,
                'storage' => 'storage',
            ],
            'database' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'port' => 3306,
                'user' => null,
                'password' => '',
                'name' => null,
            ],
            'cache' => [
                'adapter' => FilesystemAdapter::class
            ],
            'session' => [
                'driver' => DefaultDriver::class
            ],
            'directory' => [
                'sbinDir'  => "/usr/sbin",
                'binDir'   => "/usr/bin",
                'root'     => "/",
            ],
        ];
        $this->initialMemoryUsage = memory_get_usage(true);
        $this->container = $this->createFactory($container);
    }

    /**
     * @return int
     * @noinspection PhpUnused
     */
    public function getInitialMemoryUsage(): int
    {
        return $this->initialMemoryUsage;
    }

    public function addStop(string $name)
    {
        $this->getContainer(Benchmark::class)->addStop($name);
    }

    /**
     * @return string
     */
    public function getDefaultCurrentResponseType(): string
    {
        return $this->defaultCurrentResponseType;
    }

    /**
     * @param string $defaultCurrentResponseType
     */
    public function setDefaultCurrentResponseType(string $defaultCurrentResponseType): void
    {
        $this->defaultCurrentResponseType = $defaultCurrentResponseType;
    }

    protected function createFactory(?Container $container = null) : Container
    {
        $baseNS = Consolidation::getNameSpace(__NAMESPACE__);
        $appDir = Consolidation::appDirectory();

        $container = $container??new Container();
        // add application
        $container->setProtect(Application::class, $this);
        // register handler
        $container->get(SystemInitialHandler::class)->register();

        $events = $container->get(Events::class);
        /* ---------------------------------------------------------
         * Configurations
         */
        if (!$container->has(Configurations::class)) {
            $container->set(
                Configurations::class,
                function () {
                    $root = Consolidation::rootDirectory();
                    $file = "$root/Config.php";
                    $config = file_exists($file) && is_file($file)
                        ? (require $file)
                        : [];
                    return new Configurations($config);
                }
            );
        }

        // global
        $container->setGlobalAliases('Configurations', Configurations::class);
        $configurations = $container->get(Configurations::class);
        $config = new Configurations($this->defaultConfigurations);
        $config->merge($configurations->toArray());
        $app = $config['application'];
        $jsonConfig = $config['json'];
        if (!is_string($app['environment'])) {
            $app['environment'] = 'production';
        }
        $jsonConfig['pretty'] = $jsonConfig['pretty'] === null
            ? $app['environment'] !== 'production'
            : (bool) $jsonConfig['pretty'];

        $language = $app['language'];
        if (!is_string($language)) {
            $language = 'en';
            $app['language'] = $language;
        }
        foreach ($config as $key => $item) {
            $configurations->replace($key, $item);
        }

        $this->deploymentEnvironment = $app['environment'];

        // FREED
        /* ---------------------------------------------------------
         * Parameters
         */
        $container->setParameters([
            'applicationName'       => fn () => $this->getName(),
            'applicationVersion'    => fn () => $this->getVersion(),
            'controllerNamespaces'  => fn () => $events->dispatch('Controller:namespace', ["$baseNS\\Controller"]),
            'controllerDirectory'   => fn () => $events->dispatch('Controller:directory', "$appDir/Controller"),

            'moduleNamespaces'      => fn () => $events->dispatch('Module:namespace', ["$baseNS\\Module"]),
            'moduleDirectory'       => fn () => $events->dispatch('Module:directory', "$appDir/Module"),

            'middlewareNamespaces'  => fn () => $events->dispatch('Middleware:namespace', ["$baseNS\\Middleware"]),
            'middlewareDirectory'   => fn () => $events->dispatch('Middleware:directory', "$appDir/Middleware"),
            'middlewareDispatcher'  => fn () => $container->get(App::class)->getMiddlewareDispatcher(),

            'connection'            => fn () => $container->get(Instance::class)->getConnection(),
            'prettyJson'            => fn () => (bool) $events->dispatch('Json:pretty', (bool) $jsonConfig['pretty']),

            'logName'            => fn () => $events->dispatch('Logger:name', $this->getName()),
            'logTimezone'        => fn () => $container->get(Time::class)->getCurrentUTCTime()->getTimezone(),
            'logHandlers'           => function (Container $container) : array {
                $defaultHandlers = [];
                if (Validator::isCli()) {
                    $defaultHandlers[] = new PsrHandler(
                        new ConsoleLogger($container->get(ConsoleOutput::class))
                    );
                }
                $handlers = $container->get(Events::class)->dispatch('Logger:handlers', $defaultHandlers);
                foreach ($handlers as $key => $handler) {
                    if (!$handler instanceof HandlerInterface) {
                        unset($handlers[$key]);
                    }
                }
                return array_values($handlers);
            }
        ]);

        unset($configurations, $app);

        /* ---------------------------------------------------------
         * Factories
         */
        $factories = [
            // SORT ORDER!
            /* ---------------------------------------------------------
             * Factory Request & Response
             */
            ResponseFactory::class => [ResponseFactoryInterface::class],
            ServerRequestCreator::class => [
                'callback' => fn() => ServerRequestCreatorFactory::determineServerRequestCreator(),
                'alias' => [
                    ServerRequestCreatorInterface::class
                ],
            ],

            /* ---------------------------------------------------------
             * Time
             */
            Time::class => null,

            /* ---------------------------------------------------------
             * Logger
             */
            Logger::class => [
                \Monolog\Logger::class,
                LoggerInterface::class,
            ],
            PsrHandler::class => [
                'callback' => fn () => $container->get(Logger::class)->getPsrHandler(),
                'alias' => [
                    HandlerInterface::class
                ]
            ],

            /* ---------------------------------------------------------
             * Renderer
             */
            StoragePath::class => null,
            Renderer::class => null,
            ResponseEmitter::class => null,

            /* ---------------------------------------------------------
             * Database
             */
            Instance::class => null,
            ExistingConnection::class => [
                ConnectionLoader::class
            ],
            SingleConnectionProvider::class => [
                ConnectionProvider::class
            ],
            ConfigurationLoader::class => [
                'callback' => function (Container $container) use ($baseNS) {
                    $events = $container->get(Events::class);
                    $root   = $container->get(StoragePath::class)->getRootDirectory();
                    $migrationPath = $events->dispatch(
                        'Migrations:path',
                        'migrations'
                    );
                    $migrationDirectory = sprintf('%s/%s', $root, $migrationPath);
                    $default = [
                        'migrations_paths' => [
                            "$baseNS\\Migrations" => $migrationDirectory
                        ]
                    ];
                    $configurations = $events->dispatch('Migrations:configurations', $default);
                    if (!is_array($configurations)) {
                        $configurations = [];
                    }
                    if (!isset($configurations['migrations_paths'])
                        || !is_array($configurations['migrations_paths'])
                    ) {
                        $configurations['migrations_paths'] = $default['migrations_paths'];
                    }
                    foreach ($configurations['migrations_paths'] as $namespace => $path) {
                        if (!Validator::isValidNamespace($namespace)) {
                            unset($configurations['migrations_paths'][$namespace]);
                            continue;
                        }
                        if (!isset($path)
                            || !is_string($path)
                            || !Validator::isRelativePath($path)
                        ) {
                            $path = sprintf('%s/%s', $root, $path);
                        }
                        if (!is_dir($path)) {
                            mkdir($path, 0755, true);
                        }
                        $configurations['migrations_paths'][$namespace] = $path;
                    }

                    if (empty($configurations['migrations_paths'])) {
                        $configurations['migrations_paths'] = $default['migrations_paths'];
                        if (!is_dir($default['migrations_paths']["$baseNS\\Migrations"])) {
                            mkdir($default['migrations_paths']["$baseNS\\Migrations"], 0755, true);
                        }
                    }

                    $metadata = new TableMetadataStorageConfiguration();
                    $tableName = $events->dispatch('Migrations:table', $metadata->getTableName());
                    if (!$tableName || !preg_match('~^[a-zA-Z0-9_]+$~', $tableName)) {
                        $tableName = $metadata->getTableName();
                    }
                    $configurations['table_storage']['table_name'] = $tableName;
                    return new ConfigurationArray($configurations);
                },
                'alias' => [
                    ConfigurationArray::class
                ]
            ],
            DependencyFactory::class => fn () => DependencyFactory::fromConnection(
                $container->get(ConfigurationLoader::class),
                $container->get(ConnectionLoader::class)
            ),

            /* ---------------------------------------------------------
             * Console
             */
            ArgvInput::class => [
                InputInterface::class
            ],
            ConsoleOutput::class => [
                OutputInterface::class
            ],
            Runner::class => [\Symfony\Component\Console\Application::class],

            /* ---------------------------------------------------------
             * Cache
             */
            DefaultMarshaller::class => [MarshallerInterface::class],
            Cache::class => [CacheItemPoolInterface::class],

            /* ---------------------------------------------------------
             * Sessions
             */
            Sessions::class => null,

            /* ---------------------------------------------------------
             * Scheduler
             */
            Scheduler::class => null,

            /* ---------------------------------------------------------
             * ACL
             */
            Lists::class => null,

            /* ---------------------------------------------------------
             * Controller
             */
            ControllerStorage::class => null,
            ControllerCollector::class => null,
            AnnotationReader::class => Reader::class,
            ObjectFileReader::class => null,
            AnnotationCollector::class => null,
            ExpressionLanguage::class => null,

            /* ---------------------------------------------------------
             * Middleware
             */
            MiddlewareCollector::class => null,
            MiddlewareStorage::class => null,

            /* ---------------------------------------------------------
             * Module
             */
            ModuleCollector::class => null,
            ModuleStorage::class => null,

            /* ---------------------------------------------------------
             * Json API
             */
            ApiCreator::class => null,
            DefaultExceptionFactory::class => [ExceptionFactoryInterface::class],
            EncodeDecode::class => [
                DeserializerInterface::class,
                JsonDeserializer::class,
            ],
            JsonApiRequestInterface::class => [
                'callback' => fn () => $container->get(ApiCreator::class)->createJsonApiRequest(
                    $container->get(ServerRequestInterface::class),
                    $container->get(ExceptionFactoryInterface::class)
                ),
                'alias' => [
                    JsonApiRequest::class,
                ]
            ],
        ];
        foreach ($factories as $factory => $alias) {
            if (!$container->has($factory)) {
                if ($alias instanceof Closure) {
                    $container->setProtect($factory, $alias);
                } else {
                    if (is_array($alias) && isset($alias['callback'])) {
                        $callback = $alias['callback'];
                        $alias = $alias['alias']??[];
                        $container->setProtect($factory, $callback);
                    } else {
                        $container->setProtect($factory);
                    }
                }
            }

            $name = lcfirst(substr(strrchr($factory, '\\'), 1));
            $container->setGlobalAliases($name, $factory);
            if (!empty($alias)) {
                foreach ((array) $alias as $al) {
                    if (is_string($al)) {
                        $name = lcfirst(substr(strrchr($factory, '\\'), 1));
                        $container
                            ->setGlobalAliases($al, $factory)
                            ->setGlobalAliases($name, $factory);
                    }
                }
            }
        }

        $container->get(Translator::class)->setLocale($language);

        /* ---------------------------------------------------------
         * Server Request
         */
        if (!$container->has(ServerRequestInterface::class)) {
            $container->set(
                ServerRequestInterface::class,
                fn() => $container
                    ->get(ServerRequestCreatorInterface::class)
                    ->createServerRequestFromGlobals()
            );
        }

        /* ---------------------------------------------------------
         * Resolve Proxy
         */
        $serverRequest = $container->get(ServerRequestInterface::class);
        if (($serverRequest->getServerParams()['HTTP_X_FORWARDED_PROTO']??null) === 'https') {
            $uri = $serverRequest->getUri();
            if ($uri->getScheme() == 'http') {
                $serverRequest = $serverRequest->withUri($uri->withScheme('https'));
                $container->remove(ServerRequestInterface::class);
                $container->set(ServerRequestInterface::class, fn () => $serverRequest);
            }
        }

        if (Validator::isCli()) {
            $app = $this->getContainer(App::class);
            global $argv;
            $serverRequest = $serverRequest->withAttribute(
                RouteContext::ROUTING_RESULTS,
                new RoutingResults(
                    new Dispatcher($app->getRouteCollector()),
                    'CLI',
                    'CLI',
                    RoutingResults::FOUND,
                    null,
                    $argv
                )
            );
        }

        $container
            ->setGlobalAliases(ServerRequest::class, ServerRequestInterface::class)
            ->setGlobalAliases('ServerRequest', ServerRequestInterface::class)
            ->setGlobalAliases('Request', ServerRequestInterface::class);

        $serverParams = $serverRequest->getServerParams();
        $documentUri = $serverParams['DOCUMENT_URI']??(
            $_SERVER['SCRIPT_NAME']??(
                $_SERVER['PHP_SELF']??null
            )
        );

        // resolve document uri
        $documentUri = $documentUri ? dirname($documentUri) : '/';
        $documentUri = str_replace('\\', '/', $documentUri);
        $documentUri = substr($documentUri, 0, -1);
        $container->get(App::class)->setBasePath($documentUri);

        /* ADD STOP */
        $this->addStop('Application:container');

        // dispatch container factory
        $events->dispatch('Application:factory', $this);

        /* ADD STOP */
        $this->addStop('Application:factory');

        return $container;
    }


    public function dispatchModule() : static
    {
        if ($this->moduleDispatched) {
            return $this;
        }

        $this->moduleDispatched = true;
        $events = $this->getContainer(Events::class);
        $events->dispatch('Module:start', $this);
        $this->getContainer(ModuleStorage::class)->start();
        $events->dispatch('Module:end', $this);
        /* ADD STOP */
        $this->addStop('Application:module');
        return $this;
    }

    public function attachMiddleware() : static
    {
        if ($this->middlewareAttached) {
            return $this;
        }
        $events                   = $this->getContainer(Events::class);
        if ($events->dispatch('Dispatch:module', true) === true) {
            $this->dispatchModule();
        }
        $this->middlewareAttached = true;
        $events->dispatch('Middleware:start', $this);
        $this->getContainer(MiddlewareStorage::class)->start();
        $events->dispatch('Middleware:end', $this);
        /* ADD STOP */
        $this->addStop('Application:middleware');
        return $this;
    }

    public function attachController() : static
    {
        if ($this->controllerAttached) {
            return $this;
        }
        $events                   = $this->getContainer(Events::class);
        if ($events->dispatch('Attach:middleware', true) === true) {
            $this->attachMiddleware();
        } elseif ($events->dispatch('Dispatch:module', true) === true) {
            $this->dispatchModule();
        }

        $this->controllerAttached = true;
        $events->dispatch('Controller:start', $this);
        $this->getContainer(ControllerStorage::class)->start();
        $events->dispatch('Controller:end', $this);
        /* ADD STOP */
        $this->addStop('Application:controller');
        return $this;
    }

    public function run(bool $emit = true, bool $forceDispatch = false): ResponseInterface
    {
        if ($this->response) {
            return $this->response;
        }

        $events = $this->getContainer(Events::class);
        $isCli = Validator::isCli();
        if (Validator::isCli()) {
            $events->add('Buffer:memory', fn () => true);
        }

        $dispatchModule     = !$isCli || $events->dispatch('Dispatch:module', true) === true;
        $attachMiddleware   = !$isCli || $events->dispatch('Attach:middleware', true) === true;
        $attachController   = !$isCli || $events->dispatch('Attach:controller', false) === true;
        $dispatchHandle     = !$isCli || $events->dispatch('Dispatch:handle', false) === true;

        /* ---------------------------------------------------------
         * Dispatch Module
         */
        $dispatchModule && $this->dispatchModule();

        /* ---------------------------------------------------------
         * Dispatch Middleware
         */
        $attachMiddleware && $this->attachMiddleware();

        /* ---------------------------------------------------------
         * Dispatch Route
         */
        $attachController && $this->attachController();

        /* ---------------------------------------------------------
         * Request Events
         */
        $request = $events->dispatch(
            'Request:handle',
            $this->getContainer(ServerRequestInterface::class),
            $this
        );

        /* ADD STOP */
        $this->addStop('Application:request');
        $this->response = $dispatchHandle || $forceDispatch
            ? $events->dispatch(
                'Response:handle',
                $this->handle($request),
                $this
            ) : $this
                ->getContainer(ResponseFactoryInterface::class)
                ->createResponse();

        /* ADD STOP */
        $this->addStop('Application:handle');

        /* ---------------------------------------------------------
         * Dispatching Response
         */
        $emit = $events->dispatch(
            'Dispatch:response',
            $emit,
            $this->response,
            $this
        );
        $this->response = $events->dispatch(
            'Response:final',
            $this->response,
            $this
        );
        /* ADD STOP */
        $this->addStop('Application:dispatch');

        if ($dispatchHandle && $emit) {
            $this->getContainer(ResponseEmitter::class)->emit($this->response);
            $this->response = $events->dispatch(
                'Dispatch:emit',
                $this->response,
                $this
            );
            $this->addStop('Application:emit');
        }

        return $this->response;
    }

    /**
     * @return bool
     */
    public function isProduction() : bool
    {
        return !$this->isDevelopment();
    }

    /**
     * @return string
     */
    public function getDeploymentEnvironment() : string
    {
        return $this->deploymentEnvironment;
    }

    public function isDevelopment() : bool
    {
        return $this->isDebug() || preg_match(
            '~^(?:dev(?:elopment)?|stag(?:e|ing))$~i',
            $this->getDeploymentEnvironment()
        );
    }

    #[Pure] public function isDebug() : bool
    {
        return $this->getDeploymentEnvironment() === 'debug';
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->getContainer(Events::class)->dispatch(
            'Application:version',
            $this->version
        );
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getContainer(Events::class)->dispatch(
            'Application:name',
            $this->name
        );
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->getContainer(App::class), $name], $arguments);
    }
}
