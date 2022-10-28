<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use FastRoute\RouteParser\Std;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteCollectorProxy;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class Storage extends AbstractContainerization implements Startable
{
    use EventsMethods;

    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * @var array<string, bool>
     */
    protected array $registered = [];

    /**
     * @var array<string, array<string, array>>
     */
    protected array $duplicateRoutes = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $invalidRoutes = [];

    /**
     * @var ?AbstractController
     */
    private ?AbstractController $currentController = null;

    /**
     * @var array
     */
    private array $matchedRouteParameters = [];

    /**
     * @param Container $container
     * @param Collector $controllers
     * @param RouteCollectorProxy $routeCollectorProxy
     */
    #[Pure] public function __construct(
        Container $container,
        protected Collector $controllers,
        protected RouteCollectorProxy $routeCollectorProxy
    ) {
        parent::__construct($container);
    }

    /**
     * @return ?AbstractController
     */
    public function getCurrentController(): ?AbstractController
    {
        return $this->currentController;
    }

    /**
     * @return array
     */
    public function getMatchedRouteParameters(): array
    {
        return $this->matchedRouteParameters;
    }

    public function start()
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $timeRecord = $this->getContainer(Benchmark::class);
        $timeRecord->start('StorageControllers:load');

        // events
        $this->eventDispatch('StorageControllers:controllers:start', $this);
        $routes = [];
        foreach ($this->controllers->getControllersKey() as $controllerName) {
            $controller = $this->controllers->getController($controllerName);
            $routes[$controller->getGroupRoutePattern()][$controllerName] = $controller;
        }
        $parser = new Std();
        $obj = $this;
        $routes_collections = [];
        foreach ($routes as $groupPattern => $controllers) {
            unset($routes[$groupPattern]);
            $this->routeCollectorProxy->group(
                $groupPattern,
                function (RouteCollectorProxy $routeCollectorProxy) use (
                    &$routes_collections,
                    $groupPattern,
                    $parser,
                    $obj,
                    $controllers,
                    $timeRecord
                ) {
                    /**
                     * @var AbstractController $controller
                     */
                    foreach ($controllers as $identifier => $controller) {
                        $classNameId = sprintf('StorageControllers:load[%s]', get_class($controller));
                        $timeRecord->start($classNameId);
                        $methods = $controller->getRouteMethods();
                        $methods = array_filter(
                            array_map(
                                'strtoupper',
                                array_filter($methods, 'is_string')
                            )
                        );
                        try {
                            $parsed = $parser->parse("$groupPattern{$controller->getRoutePattern()}");
                        } catch (Throwable $e) {
                            $obj->invalidRoutes[$identifier][$classNameId] = $e->getMessage();
                            $timeRecord->stop($classNameId);
                            continue;
                        }

                        $methods = array_unique($methods);
                        if (in_array('ANY', $methods, true)
                            || in_array('ALL', $methods, true)
                        ) {
                            $methods = array_merge($methods, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
                            $methods = array_unique($methods);
                            $keySearch = array_search('ANY', $methods);
                            if ($keySearch !== false) {
                                unset($methods[$keySearch]);
                            }
                            $keySearch = array_search('ALL', $methods);
                            if ($keySearch !== false) {
                                unset($methods[$keySearch]);
                            }
                            $methods = array_values($methods);
                        }
                        $temp_routes = [];
                        $noRegister = false;
                        foreach ($parsed as $item) {
                            $route = '';
                            foreach ($item as $path) {
                                $route .= is_string($path) ? $path : $path[1];
                            }
                            $temp_routes[$route] = true;
                            if (isset($routes_collections[$route])) {
                                $diff = array_intersect_assoc($routes_collections[$route], $methods);
                                if (!empty($diff)) {
                                    $noRegister = true;
                                    $obj->duplicateRoutes[$identifier][$route] = $diff;
                                }
                            }
                        }
                        if ($noRegister) {
                            $timeRecord->stop($classNameId);
                            continue;
                        }

                        foreach ($temp_routes as $route => $tmp) {
                            if (isset($routes_collections[$route])) {
                                $routes_collections[$route] = array_merge($routes_collections[$route], $methods);
                                $routes_collections[$route] = array_unique($routes_collections[$route]);
                                continue;
                            }
                            $routes_collections[$route] = $methods;
                        }

                        // add register
                        $obj->registered[$identifier] = true;
                        $obj->controllers->load(
                            $obj,
                            $controller,
                            $routeCollectorProxy,
                            function (
                                AbstractController $controller,
                                RouteCollectorProxy $routeCollectorProxy
                            ) use (
                                $obj,
                                $methods,
                                $parsed,
                                $identifier
                            ) {
                                $obj->eventDispatch(
                                    'StorageControllers:controller:prepare',
                                    $controller,
                                    $this
                                );
                                $annotate = method_exists($controller, 'getRouteAnnotation')
                                    ? $controller->getRouteAnnotation()
                                    : null;
                                $controllerName = $annotate instanceof Route
                                    ? ($annotate->getName()?:$identifier)
                                    : $identifier;
                                $route = $routeCollectorProxy->map(
                                    $methods,
                                    $controller->getRoutePattern(),
                                    function (
                                        ServerRequestInterface $request,
                                        ResponseInterface $response,
                                        array $params = []
                                    ) use (
                                        $parsed,
                                        $controller,
                                        &$route,
                                        $obj
                                    ) {
                                        $params['$controller'] = $controller;
                                        $params['$route'] = $route;
                                        $obj->currentController = $controller;
                                        $obj->matchedRouteParameters = $params;
                                        return $controller->doingRouting(
                                            $route,
                                            $parsed,
                                            $request,
                                            $response,
                                            $params
                                        );
                                    }
                                )->setName($controllerName);
                                foreach ($controller->getRouteArguments() as $key => $argument) {
                                    if (is_string($argument) && is_string($key)) {
                                        $route->setArgument($key, $argument);
                                    }
                                }

                                $obj->eventDispatch(
                                    'StorageControllers:controller:registered',
                                    $controller,
                                    $this,
                                    $route
                                );
                            }
                        );

                        $timeRecord->stop($classNameId);
                    }
                }
            );
        }

        // events
        $this->eventDispatch('StorageControllers:controllers:registered', $this);
        $timeRecord->stop('StorageControllers:load');
    }

    /**
     * @return array<string, array<string, array>>
     * @noinspection PhpUnused
     */
    public function getDuplicateRoutes(): array
    {
        return $this->duplicateRoutes;
    }

    /**
     * @return array<string, array<string, string>>
     * @noinspection PhpUnused
     */
    public function getInvalidRoutes(): array
    {
        return $this->invalidRoutes;
    }

    /**
     * @return Collector
     * @noinspection PhpUnused
     */
    public function getCollector(): Collector
    {
        return $this->controllers;
    }

    /**
     * @return bool
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * @return string[]
     */
    public function getRegistered(): array
    {
        return array_keys($this->registered);
    }
}
