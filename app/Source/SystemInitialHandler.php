<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\ResponseEmitter;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Helper\Util\StreamCreator;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\LoggingMethods;
use TrayDigita\Streak\Source\Views\Html\Renderer;

class SystemInitialHandler extends AbstractContainerization
{
    use EventsMethods,
        LoggingMethods;

    /**
     * @var bool
     */
    private bool $registered = false;

    /**
     * @var ?StreamInterface|false
     */
    private null|StreamInterface|bool $stream = null;

    public function register()
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        if (!Validator::isCli()) {
            $obHandler = ob_list_handlers();
            $content   = null;
            if (count($obHandler) === 1 && isset($obHandler[0]) && !is_callable($obHandler[0])) {
                if (ob_get_length()) {
                    $content = ob_get_clean();
                }

                ob_get_level() > 0 && ob_end_flush();
            }

            ob_start([$this, 'handleBuffer'], 4096);
            set_error_handler(fn (...$args) => $this->handleError(...$args));
            register_shutdown_function(fn () => $this->handleShutdown());
            set_exception_handler(fn (...$args) => $this->handleException(...$args));
            // do echo let previous buffer served
            if ($content) {
                echo $content;
            }
            unset($content);
        }
    }

    private function handleBuffer(string $content): string
    {
        if ($this->stream === null) {
            try {
                $this->stream = $this->getContainer(
                    ResponseFactoryInterface::class
                )->createResponse()->getBody();
            } catch (Throwable) {
                $this->stream = false;
            }
        }

        $this->stream && $this->stream->write($content);
        return $content;
    }

    /**
     * @return ?StreamInterface
     */
    public function getStream(): ?StreamInterface
    {
        return $this->stream;
    }

    private function handleShutdown()
    {
        $this
            ->getContainer(Benchmark::class)
            ->addStop('Application:shutdown');
        $this->eventDispatch('Shutdown:handler', $this);
    }

    private function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline,
        ?array $errcontext = null
    ) {
        $this
            ->eventDispatch(
                'Error:handler',
                $errno,
                $errstr,
                $errfile,
                $errline,
                $errcontext,
                $this
            );
    }

    public function handleException(Throwable $exception)
    {
        // handle
        if (!$this->eventHas('Exception:handler')) {
            $response = $this->renderError(
                $exception,
                $this->getContainer(ServerRequestInterface::class),
                $this
                    ->getContainer(ResponseFactoryInterface::class)
                    ->createResponse(500)
            );
            $this->getContainer(ResponseEmitter::class)->emit($response);
            return;
        }

        $this
            ->eventDispatch(
                'Exception:handler',
                $exception,
                $this
            );
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
        // log
        $this->logException(
            $exception,
            [
                'url'        => (string) $request->getUri(),
                'method'     => $request->getMethod(),
            ]
        );
        $defaultResponseType = $this
            ->getContainer(Application::class)
            ->getDefaultCurrentResponseType();
        $exception = $this->eventDispatch('Handle:exception', $exception);
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
