<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Helper\Util\StreamCreator;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Views\Html\Renderer;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;

class ErrorMiddlewareHandler extends AbstractMiddleware
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $logger = $this->getContainer(LoggerInterface::class);
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

        // log
        $logger->error($exception);
        return $this->renderError($exception, $request, $response);
    }

    public static function thePriority(): int
    {
        return PHP_INT_MIN+1;
    }

    public function renderError(
        Throwable $exception,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $defaultResponseType = $this
            ->getContainer(Application::class)
            ->getDefaultCurrentResponseType();
        $exception = $this->eventDispatch('Middleware:exception', $exception);
        $defaultResponseType = strtolower($defaultResponseType);
        if (preg_match('~[+/]json~', $defaultResponseType)) {
            $apiCreator = $this->getContainer(ApiCreator::class);
            return $apiCreator
                ->responseError(
                    $exception,
                    $response,
                    $apiCreator->createJsonApiRequest($request)
                );
        }

        if (Validator::isCli()) {
            // console
            $streamOutput   = StreamCreator::createStreamOutput(null, true);
            $this->getContainer(Runner::class)->renderThrowable($exception, $streamOutput);
            return $response
                ->withBody(StreamCreator::createStreamFromResource($streamOutput->getStream()));
        }

        return $this
            ->getContainer(Renderer::class)
            ->exceptionView($exception)
            ->render($response);
    }
}
