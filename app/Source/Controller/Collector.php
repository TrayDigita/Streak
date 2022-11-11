<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use FilesystemIterator;
use Psr\Http\Message\ServerRequestInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Interfaces\Abilities\Scannable;
use TrayDigita\Streak\Source\RouteAnnotations\Abstracts\AnnotationController;
use TrayDigita\Streak\Source\RouteAnnotations\Collector as AnnotationCollector;
use TrayDigita\Streak\Source\Traits\LoggingMethods;
use TrayDigita\Streak\Source\Traits\NamespacesArray;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class Collector extends AbstractContainerization implements Scannable
{
    use LoggingMethods,
        NamespacesArray,
        TranslationMethods;

    protected int $maxDepth = 2;

    /**
     * @var array<string, AbstractController>
     */
    protected array $controllers = [];

    /**
     * @var array<string, string>
     */
    protected array $loadedControllers = [];

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
     * @param string|array $controllerNamespaces
     * @param string $controllerDirectory
     */
    public function __construct(
        Container $container,
        string|array $controllerNamespaces,
        protected string $controllerDirectory
    ) {
        parent::__construct($container);
        $controllerNamespaces = (array) $controllerNamespaces;
        foreach ($controllerNamespaces as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $this->addNamespace($item);
        }
    }

    /**
     * @return string
     */
    public function getControllerDirectory(): string
    {
        return $this->controllerDirectory;
    }

    /**
     * @return int
     */
    public function getMaxDepth() : int
    {
        return $this->maxDepth;
    }

    /**
     * @param int $maxDepth
     * @noinspection PhpUnused
     */
    public function setMaxDepth(int $maxDepth) : void
    {
        $this->maxDepth = $maxDepth < 0 ? 0 : $maxDepth;
    }

    /**
     * @return $this
     */
    public function scan() : static
    {
        if ($this->scanned) {
            return $this;
        }

        $this->scanned = true;
        $this->controllers = [];
        $directory = realpath($this->controllerDirectory)?:false;
        if (!$directory || !is_dir($directory)) {
            return $this;
        }

        $length = strlen($directory);
        $recursive = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->controllerDirectory,
                FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_SELF
                | FilesystemIterator::SKIP_DOTS
                | FilesystemIterator::KEY_AS_FILENAME
            )
        );

        /**
         * @var SplFileInfo $info
         */
        foreach ($recursive as $fileName => $info) {
            if (!$info->isFile()
                || $info->getExtension() !== 'php'
                || $recursive->getDepth() > $this->getMaxDepth()
            ) {
                continue;
            }

            $name = substr($fileName, 0, -4);
            if (!Validator::isValidClassName($name)) {
                continue;
            }

            $path = substr($info->getPath(), $length+1);
            $path = trim($path, DIRECTORY_SEPARATOR);
            $path = str_replace('/', '\\', $path);
            $alreadyInclude = false;
            $foundClassName = false;
            foreach ($this->getNamespaces() as $namespace) {
                $name_space = $namespace;
                if ($path) {
                    $name_space .= "\\$path";
                }
                $className = sprintf(
                    '%1$s\\%2$s',
                    $name_space,
                    $name
                );
                try {
                    if (!$alreadyInclude && !class_exists($className)) {
                        $alreadyInclude = true;
                        (function ($info) {
                            require_once $info->getRealPath();
                        })->bindTo(null)($info);
                    }
                    $ref = new ReflectionClass($className);
                    if (!$ref->isSubclassOf(AbstractController::class)) {
                        if ($ref->isSubclassOf(AnnotationController::class)) {
                            $this->createFromAnnotationController($ref->getName());
                        }
                        continue;
                    }
                    $foundClassName = $ref->getName();
                    break;
                } catch (Throwable) {
                    continue;
                }
            }

            if (!$foundClassName) {
                continue;
            }
            $this->controllers[strtolower($foundClassName)] = new $foundClassName($this->container);
        }

        $this->sortCollections();
        return $this;
    }

    /**
     * Sort
     */
    private function sortCollections()
    {
        uasort($this->controllers, fn ($a, $b) => $a->getPriority() > $b->getPriority());
    }

    /**
     * @param $className
     */
    private function createFromAnnotationController($className)
    {
        try {
            $annotation = $this
                ->getContainer(AnnotationCollector::class)
                ->register($className);
            $expression = $this->getContainer(ExpressionLanguage::class);
            foreach ($annotation->getRoutes() as $route) {
                $condition = $route->getCondition();
                try {
                    $controller = DynamicController::createFromRouteAnnotation(
                        $route,
                        $this->getContainer()
                    );
                    if (is_string($condition) && trim($condition) !== '') {
                        $matches = $expression->evaluate(
                            $condition,
                            [
                                'annotation' => $annotation,
                                'route' => $route,
                                'controller' => $controller,
                                'request' => $this->getContainer(ServerRequestInterface::class),
                                'container' => $this->getContainer(),
                            ]
                        );
                        if (!$matches) {
                            continue;
                        }
                    }

                    $name = sprintf('%s::%s', $route->getController(), $route->getControllerMethod());
                    $name = strtolower($name);
                    $this->controllers[$name] = $controller;
                } catch (Throwable $e) {
                    $this->logWarningException($e, ['annotation' => $className]);
                }
            }
        } catch (Throwable $e) {
            $this->logErrorException($e);
        }
    }

    public function scanned(): bool
    {
        return $this->scanned;
    }

    /**
     * @param AbstractController $controller
     *
     * @return bool
     * @throws RuntimeException
     */
    public function add(AbstractController $controller) : bool
    {
        if (!$this->scanned()) {
            $this->scan();
        }
        if ($this->alreadyLoaded) {
            throw new RuntimeException(
                sprintf(
                    $this->translate(
                        'Can not add %1$s while the %1$s already initiated to processing.',
                    ),
                    $this->translate('controller')
                )
            );
        }

        $className = strtolower(get_class($controller));
        if (isset($this->controllers[$className])) {
            return false;
        }
        $this->controllers[$className] = $controller;
        // sorting again
        $this->sortCollections();

        return true;
    }

    /**
     * @return array<string, class-string<AbstractController>>
     * @noinspection PhpUnused
     */
    public function getControllersKey(): array
    {
        return array_keys($this->scan()->controllers);
    }

    /**
     * @param class-string<T> $name
     *
     * @template T
     * @return ?AbstractController|T
     * @noinspection PhpDocSignatureInspection
     * @noinspection PhpUnused
     */
    public function getController(string $name) : ?AbstractController
    {
        $name = strtolower(ltrim($name, '\\'));
        return $this->controllers[$name]??null;
    }

    /**
     * @return array<string, AbstractController>
     */
    public function getControllers() : array
    {
        return $this->controllers;
    }

    /**
     * @return bool[]
     * @noinspection PhpUnused
     */
    public function getLoadedControllers(): array
    {
        return array_keys($this->loadedControllers);
    }
}
