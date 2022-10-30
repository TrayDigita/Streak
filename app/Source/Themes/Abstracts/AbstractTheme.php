<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Themes\Abstracts;

use BadMethodCallException;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use ReflectionClass;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Controller\Storage;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\SystemInitialHandler;
use TrayDigita\Streak\Source\Themes\ThemeReader;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use TrayDigita\Streak\Source\Views\Html\AbstractRenderer;
use TrayDigita\Streak\Source\Views\Html\Renderer;
use TrayDigita\Streak\Source\Views\MultiRenderView;

/**
 * @mixin AbstractRenderer
 */
abstract class AbstractTheme extends AbstractContainerization
{
    use EventsMethods,
        TranslationMethods;

    /**
     * @var string
     * @readonly
     */
    public readonly string $directory;

    /**
     * @var string
     * @readonly
     */
    public readonly string $path;

    /**
     * @var string
     * @readonly
     */
    public readonly string $directoryName;

    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var string
     */
    protected string $version = '';

    /**
     * @var string
     */
    protected string $description = '';

    /**
     * @var string
     */
    protected string $authorName = '';

    /**
     * @var string
     */
    protected string $authorURI = '';

    /**
     * @var ?ServerRequestInterface
     */
    private ?ServerRequestInterface $request = null;

    /**
     * @var ?ResponseInterface
     */
    private ?ResponseInterface $response = null;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $headerStream = null;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $bodyStream = null;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $footerStream = null;

    /**
     * @var ?Throwable
     */
    private ?Throwable $exception = null;

    /**
     * @var bool
     */
    private bool $rendered = false;

    /**
     * @var AbstractRenderer|MultiRenderView
     */
    public readonly AbstractRenderer|MultiRenderView $renderView;

    /**
     * @param Container $container
     */
    final public function __construct(Container $container)
    {
        parent::__construct($container);
        $ref = new ReflectionClass($this);
        $this->directory = dirname($ref->getFileName());
        $this->directoryName = basename($this->directory);
        $path = substr($this->directory, strlen(Consolidation::publicDirectory()) + 1);
        $this->path = str_replace('\\', '/', $path);

        if (!$this->name) {
            $this->name = ucwords($this->directoryName);
        }

        $this->renderView = $this->getContainer(Renderer::class)->createMultiRenderView();
    }

    /**
     * @return AbstractRenderer
     */
    public function getRenderView(): AbstractRenderer
    {
        return $this->renderView;
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
     * Get theme uri
     *
     * @param ?ServerRequest $request
     * @param ?string $afterURI
     *
     * @return UriInterface
     */
    public function getURI(
        ?ServerRequest $request = null,
        ?string $afterURI = null
    ): UriInterface {
        $request = $request??(
            $this->getRequest()??$this->getContainer(ServerRequestInterface::class)
        );
        $uri = $request
            ->getUri()
            ->withFragment('')
            ->withQuery('')
            ->withPath("/$this->path/");
        if ($afterURI) {
            $uri = new Uri("$uri$afterURI");
        }
        return $uri;
    }

    /**
     * @return StreamInterface
     */
    final public function getHeader() : StreamInterface
    {
        if ($this->headerStream) {
            return $this->headerStream;
        }

        $this->headerStream = $this->getContainer(SystemInitialHandler::class)->createStream();
        ob_start();
        (function () {
            include $this->getContainer(ThemeReader::class)->getHeaderFile();
        })();
        $this->headerStream->write(ob_get_clean()?:'');
        return $this->headerStream;
    }

    /**
     * @return StreamInterface
     */
    final public function getBody() : StreamInterface
    {
        if ($this->bodyStream) {
            return $this->bodyStream;
        }

        $this->bodyStream = $this->getContainer(SystemInitialHandler::class)->createStream();
        ob_start();
        (function () {
            include $this->getContainer(ThemeReader::class)->getBodyFile();
        })();
        $this->bodyStream->write(ob_get_clean()?:'');
        return $this->bodyStream;
    }

    /**
     * @return StreamInterface
     */
    final public function getFooter() : StreamInterface
    {
        if ($this->footerStream) {
            return $this->footerStream;
        }

        $this->footerStream = $this->getContainer(SystemInitialHandler::class)->createStream();
        ob_start();
        (function () {
            include $this->getContainer(ThemeReader::class)->getFooterFile();
        })();
        $this->footerStream->write(ob_get_clean()?:'');
        return $this->footerStream;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    /**
     * @return string
     */
    public function getAuthorURI(): string
    {
        return $this->authorURI;
    }

    /**
     * @return ?Throwable
     */
    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * @return bool
     */
    #[Pure] public function isErrorResponse() : bool
    {
        return $this->getException() !== null;
    }

    /**
     * @return bool
     */
    public function is404() : bool
    {
        return $this->getResponse()?->getStatusCode() === 404
            || $this->getException() instanceof HttpNotFoundException;
    }

    /**
     * @return bool
     */
    public function is500() : bool
    {
        if ($this->getResponse()?->getStatusCode() === 500) {
            return true;
        }
        $exception = $this->getException();
        if ($exception === null) {
            return false;
        }
        if ($exception instanceof HttpSpecializedException) {
            return $exception instanceof HttpInternalServerErrorException
                || ($exception->getCode() >= 500 && $exception->getCode() < 600);
        }
        return true;
    }

    /**
     * Render theme
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @param ?AbstractController $controller
     *
     * @return ResponseInterface
     */
    final public function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params,
        ?AbstractController $controller = null
    ) : ResponseInterface {
        // prevent loop back
        if ($this->rendered) {
            return $response;
        }
        // switch to standard
        $this->renderView->toStandardRenderView();
        $this->request    = $request;
        $this->response   =& $response;
        $this->rendered   = true;
        $this->eventDispatch(
            'Theme:render',
            $this,
            $request,
            $response,
            $params,
            $controller
        );

        return $this->doRender(
            $this->request,
            $this->response,
            $params,
            $controller
        );
    }

    /**
     * @param Throwable $exception
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     *
     * @return ResponseInterface
     */
    final public function renderException(
        Throwable $exception,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = []
    ) : ResponseInterface {
        if ($this->rendered) {
            return $response;
        }

        if ($exception instanceof HttpSpecializedException) {
            $code = $exception->getCode();
            $code = Code::statusMessage($code) ? $code :500;
            $response = $response->withStatus($code);
        }

        $this->request   = $request;
        $this->response  =& $response;
        $this->exception = $exception;
        $this->renderView->toExceptionRenderView($this->exception);

        $this->rendered = true;
        $controller = $this->getContainer(Storage::class)->getCurrentController();
        $this->eventDispatch(
            'Theme:renderException',
            $this,
            $request,
            $response,
            $params,
            $controller
        );
        return $this->doRenderException($exception, $request, $response, $params, $controller);
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    final public function __call(string $name, array $arguments)
    {
        if (!method_exists($this->getRenderView(), $name)) {
            throw new BadMethodCallException(
                sprintf(
                    $this->translate("Call to undefined Method %s."),
                    $name
                ),
                E_USER_ERROR
            );
        }

        return call_user_func_array([
            $this->getRenderView(),
            $name
        ], $arguments);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->headerStream && $this->headerStream->close();
        $this->bodyStream && $this->bodyStream->close();
        $this->footerStream && $this->footerStream->close();
        $this->headerStream = null;
        $this->bodyStream = null;
        $this->footerStream = null;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @param ?AbstractController $controller
     *
     * @return ResponseInterface
     */
    abstract protected function doRender(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = [],
        ?AbstractController $controller = null
    ) : ResponseInterface;

    /**
     * @param Throwable $exception
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @param AbstractController|null $controller
     *
     * @return ResponseInterface
     */
    abstract protected function doRenderException(
        Throwable $exception,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = [],
        ?AbstractController $controller = null
    ) : ResponseInterface;
}
