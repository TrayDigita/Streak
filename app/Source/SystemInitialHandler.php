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
use TrayDigita\Streak\Source\Helper\Util\NumericConverter;
use TrayDigita\Streak\Source\Helper\Util\StreamCreator;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Records\Collections;
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
     * @var ?Throwable
     */
    private ?Throwable $exception = null;

    /**
     * @var array
     */
    private array $errors = [];

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
            $app = $this->getContainer(Configurations::class)->get('application');
            $handleBuffer = $app instanceof Collections
                ? $app->get('storeBuffer')
                : false;
            $this->handleBuffer = (bool) $this
                ->eventDispatch('Shutdown:storeBuffer', (bool) $handleBuffer);
        }
        if ($this->handleBuffer) {
            if ($this->stream === null) {
                $maxMemoryBuffer = NumericConverter::megaByteToBytes(10);
                $this->eventDispatch('Buffer:maxmemory:buffer', $maxMemoryBuffer);
                $maxMemoryBuffer = $maxMemoryBuffer < StreamCreator::DEFAULT_MAX_MEMORY
                    ? StreamCreator::DEFAULT_MAX_MEMORY
                    : $maxMemoryBuffer;
                $this->stream = $this->createStream($maxMemoryBuffer);
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
     * @param ?int $maxMemoryBytes
     *
     * @return StreamInterface
     */
    public function createStream(?int $maxMemoryBytes = null) : StreamInterface
    {
        if ($maxMemoryBytes !== null) {
            $maxMemoryBytes = $this->eventDispatch(
                'Buffer:maxmemory:stream',
                StreamCreator::DEFAULT_MAX_MEMORY
            );
            if (!is_int($maxMemoryBytes)) {
                $maxMemoryBytes = StreamCreator::DEFAULT_MAX_MEMORY;
            }
        }

        return $this->eventDispatch('Buffer:memory', false) === true
            ? StreamCreator::createMemoryStream()
            : StreamCreator::createTemporaryFileStream($maxMemoryBytes);
    }

    /**
     * @return ?Throwable
     */
    public function getException(): ?Throwable
    {
        return $this->exception;
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
            $exception = $this->exception??new ErrorException(
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

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    private function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ) {
        $err = [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line'  => $errline
        ];
        $this->errors[] = $err;
        switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
                $this->logWarning($errstr, $err);
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $this->logNotice($errstr, $err);
                break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $this->logInfo($errstr, $err);
                break;
            case E_COMPILE_ERROR:
            case E_PARSE:
                $this->logCritical($errstr, $err);
                break;
            default:
                $this->logError($errstr, $err);
        }
        $this
            ->eventDispatch(
                'Error:handleError',
                $errno,
                $errstr,
                $errfile,
                $errline,
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
            $response = $this
                ->getContainer(ResponseFactoryInterface::class)
                ->createResponse(500);
            $this->exception = $exception;
            $exceptionView = $this
                ->getContainer(Renderer::class)
                ->createExceptionRenderView($exception);
            // fallback if error
            if ($this->handled) {
                $exceptionView->setArgument(AbstractRenderer::SKIP_THEME, true);
            } else {
                ob_get_length() && ob_get_level() > 0 && ob_end_clean();
                $this->previousStream = $this->getStream();
                // reset stream
                $this->stream = null;
                ob_start([$this, 'handleBuffer']);
            }

            $response = $this->noCacheResponse($exceptionView->render($response));
            $this->getContainer(ResponseEmitter::class)->emit($response);
        }

        $this
            ->eventDispatch(
                'Exception:handleException',
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
            $this->logInfoException(
                $exception,
                [
                    'url'        => (string) $request->getUri(),
                    'method'     => $request->getMethod(),
                ]
            );
        } else {
            // log
            $this->logErrorException(
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

    /**
     * Add no cache
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function noCacheResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withoutHeader('Cache-Control')
            ->withHeader('Expires', 'Mon, 01 Jul 1970 00:00:00 GMT')
            ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T'))
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    }

    public function __destruct()
    {
        $this->exception = null;
        $this->previousStream = null;
        $this->stream = null;
    }
}
