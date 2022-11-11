<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use FastRoute\BadRouteException;
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
        $this->eventDispatch('StorageControllers:controllers:prepare', $this);
        $obj = $this;
        $parser = new Std();
        $methodToRegexToRoutesMap = [];
        foreach ($this->controllers->scan()->getControllers() as $controllerName => $controller) {
            $groupPattern = $controller->getGroupRoutePattern();
            $routePattern = $controller->getRoutePattern();
            $classNameId = sprintf('StorageControllers:load[%s]', get_class($controller));
            $timeRecord->start($classNameId);
            $pattern = "$groupPattern$routePattern";
            try {
                $methods = $controller->getRouteMethods();
                $methods = array_filter(
                    array_map(
                        'strtoupper',
                        array_filter($methods, 'is_string')
                    )
                );
                $methods = array_unique($methods);
                if (in_array('ANY', $methods, true)
                    || in_array('ALL', $methods, true)
                ) {
                    $methods   = array_merge(
                        $methods,
                        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
                    );
                    $methods   = array_unique($methods);
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
                $noRegister  = false;
                $parsed = [];
                foreach ($parser->parse($pattern) as $routeData) {
                    $regex = $this->buildRegexForRoute($routeData);
                    $parsed[] = $regex;
                    foreach ((array)$methods as $method) {
                        if (!isset($methodToRegexToRoutesMap[$method][$regex])) {
                            $methodToRegexToRoutesMap[$method][$regex] = true;
                            continue;
                        }
                        $noRegister = true;
                        $this->duplicateRoutes[$controllerName][$regex][] = $method;
                    }
                }
                if ($noRegister) {
                    $timeRecord->stop($classNameId);
                    continue;
                }
                // add register
                $this->registered[$controllerName] = true;
                $obj->eventDispatch('Storage:controller:prepare', $controller, $this);
                $annotate       = method_exists($controller, 'getRouteAnnotation')
                    ? $controller->getRouteAnnotation()
                    : null;
                $controllerName = $annotate instanceof Route
                    ? ($annotate->getName() ?: $controllerName)
                    : $controllerName;
                $route = $this->routeCollectorProxy->map(
                    $methods,
                    $pattern,
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
                        $params['$controller']       = $controller;
                        $params['$route']            = $route;
                        $obj->currentController      = $controller;
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
                $obj->eventDispatch('Storage:controller:registered', $controller, $this, $route);
                $timeRecord->stop($classNameId);
                unset($route);
            } catch (Throwable $e) {
                $this->invalidRoutes[$controllerName][$classNameId] = $e->getMessage();
                $timeRecord->stop($classNameId);
                continue;
            }
        }
        // events
        $this->eventDispatch('Storage:controllers:registered', $this);
        $timeRecord->stop('StorageControllers:load');
    }

    /**
     * @param array $routeData
     *
     * @return string
     */
    private function buildRegexForRoute(array $routeData): string
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }
            [$varName, $regexPart] = $part;
            if (isset($variables[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice',
                    $varName
                ));
            }
            if (str_contains($regexPart, '(')) {
                $skipFail = "(*SKIP)(*FAIL)";
                if (preg_match(
                    "~
                (?:
                    \(\?\(
                  | \[ [^]\\\\]* (?: \\\\ . [^]\\\\]* )*]
                  | \\\\.) $skipFail |\((?!\?(?!<(?![!=])|P<|')| \*)
                ~x",
                    $regex
                )) {
                    throw new BadRouteException(sprintf(
                        'Regex "%s" for parameter "%s" contains a capturing group',
                        $regexPart,
                        $varName
                    ));
                }
            }
            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return $regex;
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
