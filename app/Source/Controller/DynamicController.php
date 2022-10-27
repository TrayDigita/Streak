<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use RuntimeException;
use Throwable;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route;

class DynamicController extends AbstractController
{
    private static int $priority = 10;

    private ?Route $routeAnnotation = null;

    #[Pure] public function getDefaultResultContentType(): string
    {
        return $this->routeAnnotation->getReturnType();
    }

    #[Pure] public function getRouteArguments(): array
    {
        return $this->routeAnnotation->getArguments();
    }

    /**
     * @return ?Route
     */
    public function getRouteAnnotation(): ?Route
    {
        return $this->routeAnnotation;
    }

    public static function thePriority(): int
    {
        return self::$priority;
    }

    public function getRouteMethods(): array
    {
        return $this->routeAnnotation->getMethods();
    }

    #[Pure] public function getGroupRoutePattern(): string
    {
        return $this->routeAnnotation->getPrefix()?:'';
    }

    public function getRoutePattern(): string
    {
        return $this->routeAnnotation->getRoutePattern();
    }

    public function doRouting(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = []
    ): ResponseInterface {
        return (new ($this->routeAnnotation->getController())($this->getContainer()))
            ->{$this->routeAnnotation->getControllerMethod()}(
                $request,
                $response,
                $params,
                $this
            );
    }

    /**
     * @param Route $route
     * @param Container $container
     *
     * @return static
     */
    public static function createFromRouteAnnotation(Route $route, Container $container): static
    {
        if (!$route->getController()) {
            throw new RuntimeException(
                sprintf(
                    $container
                    ->get(Translator::class)
                    ->translate(
                        'Route %s does not have controller.'
                    ),
                    $route->getName()?:''
                )
            );
        }

        if (!$route->getControllerMethod()) {
            throw new RuntimeException(
                sprintf(
                    $container
                        ->get(Translator::class)
                        ->translate(
                            'Route controller %s does not have fallback method.'
                        ),
                    $route->getController()
                )
            );
        }
        if (!method_exists($route->getController(), $route->getControllerMethod())) {
            throw new RuntimeException(
                sprintf(
                    $container
                        ->get(Translator::class)
                        ->translate(
                            'Route controller %s does not have fallback method %s.'
                        ),
                    $route->getController(),
                    $route->getControllerMethod()
                )
            );
        }
        try {
            $ref = new ReflectionMethod($route->getController(), $route->getControllerMethod());
            $returnType = $ref->getReturnType();
            if ($returnType) {
                $name = $returnType->getName();
                if ($name !== ResponseInterface::class && is_a($name, ResponseInterface::class, true)) {
                    throw new RuntimeException(
                        sprintf(
                            $container
                                ->get(Translator::class)
                                ->translate(
                                    'Route controller method does not returning %s.'
                                ),
                            ResponseInterface::class
                        )
                    );
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $obj = new static($container);
        $obj->routeAnnotation = $route;
        $obj::$priority = $route->getPriority();
        return $obj;
    }
}
