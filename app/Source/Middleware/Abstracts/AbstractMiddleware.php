<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware\Abstracts;

use JetBrains\PhpStorm\Pure;
use Slim\Interfaces\RouteCollectorProxyInterface;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractResponse;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Middleware\Interfaces\CallableMiddleware;

abstract class AbstractMiddleware extends AbstractResponse implements CallableMiddleware
{
    /**
     * @param Container $container
     * @param RouteCollectorProxyInterface $routeCollectorProxy
     */
    #[Pure] final public function __construct(
        Container $container,
        private RouteCollectorProxyInterface $routeCollectorProxy
    ) {
        parent::__construct($container);
    }

    /**
     * @return RouteCollectorProxyInterface
     */
    public function getRouteCollectorProxy(): RouteCollectorProxyInterface
    {
        return $this->routeCollectorProxy;
    }
}
