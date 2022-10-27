<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\SystemInitialHandler;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;

class ErrorMiddlewareHandler extends AbstractMiddleware
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        ob_start();
        try {
            return $handler->handle($request);
        } catch (HttpSpecializedException $exception) {
            if (empty($exception->translated)) {
                // try to converting data
                $exception = $this->convertTranslationException($exception);
            }
            $response = $this
                ->getRouteCollectorProxy()
                ->getResponseFactory()
                ->createResponse(
                    $exception->getCode()
                );
        } catch (Throwable $exception) {
            $response = $this
                ->getRouteCollectorProxy()
                ->getResponseFactory()
                ->createResponse(500);
        }

        return $this->renderError($exception, $request, $response);
    }

    public static function thePriority(): int
    {
        return PHP_INT_MIN+1;
    }

    /**
     * @param Throwable $exception
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function renderError(
        Throwable $exception,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        return $this->getContainer(SystemInitialHandler::class)->renderError(
            $exception,
            $request,
            $response
        );
    }
}
