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
    /**
     * @inheritDoc
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
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
            $code = $exception->getCode();
        } catch (Throwable $exception) {
            $code = 500;
        }

        $response = $this
            ->getContainer(SystemInitialHandler::class)
            ->getCreateLastResponseStream()
            ->withStatus($code);
        return $this->renderError($exception, $request, $response);
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
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
