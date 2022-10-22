<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use FastRoute\RouteParser\Std;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteCollectorProxy;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route;

class Storage extends AbstractContainerization implements Startable
{
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

    public function start()
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $events = $this->getContainer(Events::class);
        $timeRecord = $this->getContainer(Benchmark::class);
        $timeRecord->start('StorageControllers:load');

        // events
        $events->dispatch('StorageControllers:controllers:start', $this);
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
                    $events,
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

                        $parsed = $parser->parse("$groupPattern{$controller->getRoutePattern()}");
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
                                $events,
                                $methods,
                                $parsed,
                                $identifier
                            ) {
                                $events->dispatch(
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
                                        &$route
                                    ) {
                                        $params['$controller'] = $controller;
                                        $params['$route'] = $route;
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

                                $events->dispatch(
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

        unset($routes_collections, $controllers);
        // events
        $events->dispatch('StorageControllers:controllers:registered', $this);
        $timeRecord->stop('StorageControllers:load');
    }

    /**
     * @return array
     * @noinspection PhpUnused
     */
    public function getDuplicateRoutes(): array
    {
        return $this->duplicateRoutes;
    }

    /**
     * @return Collector
     */
    public function getControllers(): Collector
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