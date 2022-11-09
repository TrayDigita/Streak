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
use TrayDigita\Streak\Source\Views\ExceptionsRenderView;
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
     * @var ?string
     */
    private ?string $defaultBufferMode = null;

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
     * @param ?int $maxMemoryBytes only affected when using temporary
     *
     * @return StreamInterface
     */
    public function createStream(?int $maxMemoryBytes = null) : StreamInterface
    {
        if (!$this->defaultBufferMode) {
            $mode = $this->getContainer(Configurations::class)->get('application');
            $mode = $mode instanceof Collections ? $mode->get('bufferMode') : 'storage';
            $mode = !is_string($mode) || trim($mode) === '' ? 'storage' : strtolower($mode);
            $mode = match ($mode) {
                'memory', 'mem' => 'memory',
                'temp', 'temporary', 'tempfile' => 'temporary',
                default => 'storage'
            };
            $this->defaultBufferMode = $mode;
        }

        // createStorageFileResource
        if ($this->eventDispatch('Buffer:memory', $this->defaultBufferMode === 'memory') === true) {
            return StreamCreator::createMemoryStream();
        }

        if ($this->eventDispatch('Buffer:temp', $this->defaultBufferMode === 'temporary') === true) {
            if ($maxMemoryBytes !== null) {
                $maxMemoryBytes = $this->eventDispatch(
                    'Buffer:maxmemory:stream',
                    StreamCreator::DEFAULT_MAX_MEMORY
                );
                if (!is_int($maxMemoryBytes)) {
                    $maxMemoryBytes = StreamCreator::DEFAULT_MAX_MEMORY;
                }
            }
            return StreamCreator::createTemporaryFileStream($maxMemoryBytes);
        }

        return StreamCreator::createStorageFileStream($this->container);
    }

    /**
     * @return ?Throwable
     */
    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * @param string $path
     *
     * @return bool
     * @internal
     */
    private function recursiveRemoveDirectoryStorage(string $path, $first = true): bool
    {
        if (is_dir($path)) {
            $dir = @opendir($path);
            $succeed = null;
            if ($dir) {
                while ($file = readdir($dir)) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    if (!$this->recursiveRemoveDirectoryStorage("$path/$file", false)) {
                        $succeed = (bool)$first;
                    } elseif ($succeed !== false) {
                        $succeed  = true;
                    }
                }
                closedir($dir);
            }

            // safe
            if (!$first && $succeed !== false) {
                @rmdir($path);
            }
            return true;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return true;
        }
        return false;
    }

    /**
     * Shutdown Handler
     */
    private function handleShutdown()
    {
        try {
            $error         = error_get_last();
            $type          = $error['type'] ?? null;
            $this->handled = true;
            // if contains error
            if ($type === E_COMPILE_ERROR || $type === E_ERROR) {
                ob_get_length() && ob_get_level() > 0 && ob_end_clean();
                $this->previousStream = $this->getStream();
                // reset stream
                $this->stream = null;
                ob_start([$this, 'handleBuffer']);
                $exception = $this->exception ?? new ErrorException(
                    $error['message'],
                    $error['type'],
                    1,
                    $error['file'],
                    $error['line']
                );
                try {
                    $this->handleException($exception);
                } catch (Throwable $e) {
                    $this->exception = $e;
                    $exceptionView   = new ExceptionsRenderView($e, $this->getContainer());
                    $response        = $this
                        ->getContainer(ResponseFactoryInterface::class)
                        ->createResponse(500);
                    $this
                        ->getContainer(ResponseEmitter::class)
                        ->emit($exceptionView->render($response, 500));
                }
            }

            $this
                ->getContainer(Benchmark::class)
                ->addStop('Application:shutdown');
            $this->eventDispatch('Shutdown:handler', $this);
        } finally {
            // remove directory stream cache
            $resourceDir = StreamCreator::getStreamResourceDirectory();
            if (is_string($resourceDir) && is_dir($resourceDir)) {
                // randomize 0 - 5
                $random = rand(0, 5);
                // possible to random 0-5 and when use 1 it will remove
                if ($random === 1) {
                    $this->recursiveRemoveDirectoryStorage(dirname($resourceDir));
                }
            }
        }
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
                break;
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
     * @return ResponseInterface
     */
    public function getCreateLastResponseStream() : ResponseInterface
    {
        $responseFactory = $this
            ->getContainer(ResponseFactoryInterface::class);
        if (property_exists($responseFactory, 'lastResponse')
            && $responseFactory->lastResponse instanceof ResponseInterface
        ) {
            if ($responseFactory->lastResponse->getBody()->getSize() === 0) {
                $response = $responseFactory->lastResponse;
            }
        }

        // reuse that do not use too many resource
        return $response??$this
                ->getContainer(ResponseFactoryInterface::class)
                ->createResponse();
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
            }

            ob_get_length() && ob_get_level() > 0 && ob_end_clean();
            $this->previousStream = $this->getStream();
            // reset stream
            $this->stream = null;
            ob_start([$this, 'handleBuffer']);

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
