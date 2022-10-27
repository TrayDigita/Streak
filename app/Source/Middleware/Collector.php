<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware;

use Closure;
use DirectoryIterator;
use ReflectionClass;
use RuntimeException;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use SplFileInfo;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Interfaces\Abilities\Scannable;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;
use TrayDigita\Streak\Source\Traits\NamespacesArray;

class Collector extends AbstractContainerization implements Scannable
{
    use NamespacesArray;

    /**
     * @var array<string, AbstractMiddleware>
     */
    protected array $middlewares = [];

    /**
     * @var array<string, string>
     */
    private array $loadedMiddlewares = [];

    /**
     * @var bool
     */
    private bool $scanned = false;

    /**
     * @var bool
     */
    private bool $alreadyLoaded = false;

    /**
     * @param Container $container
     * @param RouteCollectorProxyInterface $collectorProxy
     * @param string|array $middlewareNamespaces
     * @param string $middlewareDirectory
     */
    public function __construct(
        Container $container,
        protected RouteCollectorProxyInterface $collectorProxy,
        string|array $middlewareNamespaces,
        protected string $middlewareDirectory
    ) {
        parent::__construct($container);
        $middlewareNamespaces = (array) $middlewareNamespaces;
        foreach ($middlewareNamespaces as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $this->addNamespace($item);
        }
    }


    /**
     * @return RouteCollectorProxyInterface
     */
    public function getCollectorProxy(): RouteCollectorProxyInterface
    {
        return $this->collectorProxy;
    }

    /**
     * @return string
     */
    public function getMiddlewareDirectory(): string
    {
        return $this->middlewareDirectory;
    }

    public function scan() : static
    {
        if ($this->scanned) {
            return $this;
        }

        $this->scanned = true;
        $middlewares = [];
        $directory = realpath($this->middlewareDirectory)?:false;

        // append self middlewares
        // include core
        $sourceMiddlewareDir = realpath(dirname(__DIR__, 2).'/Middleware')?:null;
        $vendorDir = Consolidation::vendorDirectory();
        if ($sourceMiddlewareDir
            && $sourceMiddlewareDir !== $directory
            && str_starts_with(__DIR__, $vendorDir)
        ) {
            foreach (new DirectoryIterator($sourceMiddlewareDir) as $info) {
                if (!$info->isFile() || $info->getExtension() !== 'php') {
                    continue;
                }
                $this->readMiddlewares($info, $middlewares);
            }
        }

        if ($directory && is_dir($directory)) {
            foreach (new DirectoryIterator($directory) as $info) {
                if (!$info->isFile() || $info->getExtension() !== 'php') {
                    continue;
                }
                $this->readMiddlewares($info, $middlewares);
            }
        }

        ksort($this->middlewares, SORT_ASC);
        foreach ($middlewares as $classNames) {
            foreach ($classNames as $className => $class) {
                $className                     = trim(strtolower($className));
                $this->middlewares[$className] = $class;
            }
        }

        return $this;
    }

    private function readMiddlewares(SplFileInfo|DirectoryIterator $info, &$middlewares) : bool
    {
        $name = substr($info->getBasename(), 0, -4);
        if (!preg_match('~[a-zA-Z_][A-Za-z_0-9]*$~', $name)) {
            return false;
        }
        $alreadyInclude = false;
        $foundClassName = false;
        foreach ($this->getNamespaces() as $namespace) {
            $className = sprintf('%1$s\\%2$s', $namespace, $name);
            try {
                if (!$alreadyInclude && !class_exists($className)) {
                    $alreadyInclude = true;
                    (function ($info) {
                        require_once $info->getRealPath();
                    })->call(null, $info);
                }
                $ref = new ReflectionClass($className);
                if (!$ref->isSubclassOf(AbstractMiddleware::class)) {
                    continue;
                }
                $className = $ref->getName();
                $foundClassName = $className;
                break;
            } catch (Throwable) {
                continue;
            }
        }
        if (!$foundClassName) {
            return false;
        }

        /**
         * @var AbstractMiddleware $foundClassName
         */
        $middlewares[$foundClassName::thePriority()][$foundClassName] = $foundClassName;
        return true;
    }

    public function scanned(): bool
    {
        return $this->scanned;
    }

    /**
     * @return array<string, class-string<AbstractMiddleware>>
     */
    public function getMiddlewaresKey(): array
    {
        return array_keys($this->scan()->middlewares);
    }

    /**
     * @param string $name
     *
     * @return ?AbstractMiddleware
     */
    public function getMiddleware(string $name) : ?AbstractMiddleware
    {
        $name = strtolower(ltrim($name, '\\'));
        if (!isset($this->middlewares[$name])) {
            return null;
        }
        if (is_string($this->middlewares[$name])) {
            $this->middlewares[$name] = new $this->middlewares[$name](
                $this->getContainer(),
                $this->getCollectorProxy()
            );
        }

        return $this->middlewares[$name];
    }

    /**
     * @param AbstractMiddleware $middleware
     *
     * @return bool
     * @throws RuntimeException
     */
    public function add(AbstractMiddleware $middleware) : bool
    {
        if (!$this->scanned()) {
            $this->scan();
        }
        if ($this->alreadyLoaded) {
            $translator = $this->getContainer(Translator::class);
            throw new RuntimeException(
                sprintf(
                    $translator->translate(
                        'Can not add %1$s while the %1$s already initiated to processing.',
                    ),
                    $translator->translate('middleware')
                )
            );
        }

        $className = strtolower(get_class($middleware));
        if (isset($this->middlewares[$className])) {
            return false;
        }

        $this->middlewares[$className] = $middleware;
        return true;
    }

    /**
     * @param T $middleware
     * @param Storage $storageControllers
     * @param MiddlewareDispatcherInterface $middlewareDispatcher
     * @param Closure $callback
     *
     * @template T AbstractController
     * @return T
     */
    public function load(
        Storage $storageControllers,
        AbstractMiddleware $middleware,
        MiddlewareDispatcherInterface $middlewareDispatcher,
        Closure $callback
    ) : AbstractMiddleware {
        // always scan first
        if (!$this->scanned()) {
            return $middleware;
        }
        $middlewareClass = get_class($middleware);
        $name = strtolower($middlewareClass);
        if (!isset($this->loadedMiddlewares[$name])) {
            $currents = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $storageMiddleware = next($currents)['class']??null;
            // validate
            if (($storageMiddleware == Storage::class
                 || !$storageMiddleware instanceof Storage
                )
            ) {
                $this->alreadyLoaded            = true;
                $this->loadedMiddlewares[$name] = $middlewareClass;
                $callback->call($storageControllers, $middleware, $middlewareDispatcher);
            }
        }

        return $middleware;
    }
}
