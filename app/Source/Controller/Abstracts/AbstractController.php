<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use Slim\Interfaces\RouteInterface;
use Throwable;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Interfaces\PriorityCallableInterface;
use TrayDigita\Streak\Source\Benchmark;

abstract class AbstractController extends AbstractResponse implements PriorityCallableInterface, JsonSerializable
{
    private array $parsedRoute = [];

    /**
     * @var ?RouteInterface
     */
    private ?RouteInterface $routeInterface = null;

    /**
     * @return array
     */
    public function getParsedRoute(): array
    {
        return $this->parsedRoute;
    }

    /**
     * @return array
     */
    public function getRouteArguments() : array
    {
        return [];
    }

    /**
     * @return RouteInterface|null
     */
    public function getRouteInterface(): ?RouteInterface
    {
        return $this->routeInterface;
    }

    /**
     * @return string
     */
    public function getDefaultResultContentType() : string
    {
        return $this->htmlContentType;
    }

    public static function thePriority() : int
    {
        return 10;
    }

    /**
     * @return array<string>
     */
    public function getRouteMethods() : array
    {
        return ['ANY'];
    }

    /**
     * Group pattern, default empty
     *
     * @return string
     */
    public function getGroupRoutePattern() : string
    {
        return '';
    }

    /**
     * Route pattern
     *
     * @return string
     */
    abstract public function getRoutePattern() : string;

    private function determineResponseContentType()
    {
        $matched = [
            '~[+]\s*json|vnd\.api~i' => $this->jsonApiContentType,
            '~[/]?\s*json~i' => $this->jsonContentType,
            '~xml~i' => $this->xmlContentType
        ];
        foreach ($matched as $regex => $contentType) {
            if (preg_match($regex, $this->getDefaultResultContentType())) {
                return $contentType;
            }
        }
        return $this->htmlContentType;
    }

    /**
     * @param RouteInterface $routeInterface
     * @param array $parsedRoute
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     *
     * @return ResponseInterface
     */
    final public function doingRouting(
        RouteInterface $routeInterface,
        array $parsedRoute,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = [],
    ) : ResponseInterface {
        $this->routeInterface = $routeInterface;
        $this->parsedRoute = $parsedRoute;
        $isProto = false;
        try {
            $reflector = new ReflectionMethod($this, 'getDefaultResultContentType');
            $isProto   = ($reflector->getDeclaringClass()->getName() === __CLASS__);
        } catch (Throwable) {
        }

        $responseContentType = $this->determineResponseContentType();
        if (! $isProto || !$response->hasHeader('Content-Type')) {
            $response = $response->withHeader(
                'Content-Type',
                $responseContentType
            );
        }
        $timer = $this->getContainer(Benchmark::class);
        $timerName = sprintf('Controller:routing[%s]', get_class($this));

        // set response type
        $this
            ->getContainer(Application::class)
            ->setDefaultCurrentResponseType($responseContentType);

        $timer->start($timerName);
        // dispatch events
        $this->eventDispatch(
            'Controller:doRouting',
            $response,
            $request,
            $this
        );
        // dispatch events
        $response = $this->doRouting($request, $response, $params);

        $timer->stop($timerName);

        $response = $this->eventDispatch(
            'Controller:response',
            $response,
            $request,
            $this
        );
        $timer->addStop('dispatch:route');
        return $response;
    }

    #[ArrayShape(
        [
            'controller' => "array",
            'route' => "array"
        ]
    )] public function jsonSerialize() : array
    {
        $route = $this->getRouteInterface();
        return [
            'controller' => [
                'className' => get_class($this),
                'routePattern' => [
                    'group' => $this->getGroupRoutePattern(),
                    'pattern' => $this->getRoutePattern(),
                ]
            ],
            'route' => [
                'name' => $route->getName(),
                'methods' => $route->getMethods(),
                'pattern' => $route->getPattern(),
                'patterns' => $this->getParsedRoute(),
            ]
        ];
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     *
     * @return ResponseInterface
     */
    abstract public function doRouting(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = []
    ) : ResponseInterface;
}
