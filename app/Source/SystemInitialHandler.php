<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use ErrorException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Exception\HttpSpecializedException;
use Slim\ResponseEmitter;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Controller\Storage;
use TrayDigita\Streak\Source\Helper\Util\StreamCreator;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Themes\ThemeReader;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\LoggingMethods;
use TrayDigita\Streak\Source\Views\Html\AbstractRenderer;
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
     * @var ?StreamInterface
     */
    private ?StreamInterface $stream = null;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $previousStream = null;

    /**
     * @var bool
     */
    private bool $handled = false;

    /**
     * @var ?bool
     */
    private ?bool $handleBuffer = null;

    /**
     * Register shutdown
     */
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

            ob_start([$this, 'handleBuffer']);
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

    /**
     * @param string $content
     *
     * @return string
     */
    private function handleBuffer(string $content): string
    {
        if ($this->handleBuffer === null) {
            $this->handleBuffer = (bool) $this->eventDispatch('Shutdown:handleBuffer', true);
        }
        if ($this->handleBuffer) {
            if ($this->stream === null) {
                $this->stream = $this->createStream();
            }
            $this->stream->write($content);
        }
        return $content;
    }

    /**
     * @return ?StreamInterface
     * @noinspection PhpUnused
     */
    public function getPreviousStream(): ?StreamInterface
    {
        return $this->previousStream;
    }

    /**
     * @return ?StreamInterface
     */
    public function getStream(): ?StreamInterface
    {
        return $this->stream;
    }

    /**
     * @return StreamInterface
     */
    public function createStream() : StreamInterface
    {
        return $this->eventDispatch('Buffer:memory', false) === true
            ? StreamCreator::createMemoryStream()
            : StreamCreator::createTemporaryFileStream();
    }

    /**
     * Shutdown Handler
     */
    private function handleShutdown()
    {
        $error = error_get_last();
        $type = $error['type']??null;
        $this->handled = true;
        // if contains error
        if ($type === E_COMPILE_ERROR || $type === E_ERROR) {
            ob_get_length() && ob_get_level() > 0 && ob_end_clean();
            $this->previousStream = $this->getStream();
            // reset stream
            $this->stream = null;
            ob_start([$this, 'handleBuffer']);
            $exception = new ErrorException(
                $error['message'],
                $error['type'],
                1,
                $error['file'],
                $error['line']
            );
            $this->handleException($exception);
        }

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

    /**
     * @param Throwable $exception
     */
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
        if ($exception instanceof HttpSpecializedException) {
            // log debug if http specialized
            $this->logDebugException(
                $exception,
                [
                    'url'        => (string) $request->getUri(),
                    'method'     => $request->getMethod(),
                ]
            );
        } else {
            // log
            $this->logException(
                $exception,
                [
                    'url'        => (string) $request->getUri(),
                    'method'     => $request->getMethod(),
                ]
            );
        }

        $defaultResponseType = $this
            ->getContainer(Application::class)
            ->getDefaultCurrentResponseType();
        $exception = $this->eventDispatch('Handler:exception', $exception);
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

        // if content text html
        if (!$this->handled && preg_match('~/html~i', $defaultResponseType)) {
            $activeTheme = $this->getContainer(ThemeReader::class)->getActiveTheme();
            if ($activeTheme) {
                $params = $this->getContainer(Storage::class)->getMatchedRouteParameters();
                $params = (array)$this->eventDispatch('Handler:themeParams', $params);
                return $activeTheme->renderException(
                    $exception,
                    $request,
                    $response,
                    $params
                );
            }
        }

        $exceptionView = $this
            ->getContainer(Renderer::class)
            ->createExceptionRenderView($exception);
        // fallback if error
        if ($this->handled) {
            $exceptionView->setArgument(AbstractRenderer::SKIP_THEME, true);
        }

        return $exceptionView->render($response);
    }
}
