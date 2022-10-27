<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use Slim\Interfaces\RouteInterface;
use Throwable;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\PriorityCallableInterface;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\Traits\LoggingMethods;

abstract class AbstractController extends AbstractResponse implements PriorityCallableInterface, JsonSerializable
{
    use LoggingMethods;

    /**
     * @var array
     */
    private array $parsedRoute = [];

    /**
     * @var ?ServerRequestInterface
     */
    private ?ServerRequestInterface $request = null;

    /**
     * @var ?ResponseInterface
     */
    private ?ResponseInterface $response = null;

    /**
     * @var array
     */
    private array $routeParameters = [];

    /**
     * @var ?RouteInterface
     */
    private ?RouteInterface $routeInterface = null;

    /**
     * @param Container $container
     * @final
     */
    #[Pure] final public function __construct(Container $container)
    {
        parent::__construct($container);
    }

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
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
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

    /**
     * @return string
     */
    private function determineResponseContentType(): string
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
        $this->request  =& $request;
        $this->response =& $response;
        $this->routeParameters =& $params;
        $this->routeInterface = $routeInterface;
        $this->parsedRoute    = $parsedRoute;
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
            $this->response,
            $this->request,
            $this->getRouteParameters(),
            $this
        );

        // log debug
        $this->logDebug(
            $this->translate('Dispatching route'),
            [
                'controller' => get_class($this),
                'url'        => (string) $this->request->getUri(),
                'method'     => $this->request->getMethod(),
            ]
        );

        // dispatch events
        $this->response = $this->doRouting(
            $this->request,
            $this->response,
            $this->routeParameters
        );

        // stop
        $timer->stop($timerName);
        // dispatch event
        $response = $this->eventDispatch(
            'Controller:response',
            $this->response,
            $this->request,
            $this->getRouteParameters(),
            $this
        );

        if ($response instanceof ResponseInterface) {
            $this->response = $response;
        }

        $timer->addStop('dispatch:route');
        return $this->response;
    }

    #[ArrayShape(
        [
            'controller' => [
                'className' => 'string',
                'routePattern' => [
                    'group' => 'string',
                    'pattern' => 'string',
                ]
            ],
            'route' => [
                'name' => 'string',
                'methods' => 'string[]',
                'pattern' => 'string',
                'patterns' => 'array',
            ]
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
