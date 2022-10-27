<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Themes\Abstracts;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Laminas\Stdlib\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use ReflectionClass;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;

abstract class AbstractTheme extends AbstractContainerization
{
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
     * @param Container $container
     */
    final public function __construct(Container $container)
    {
        parent::__construct($container);
        $ref = new ReflectionClass($this);
        $this->directory = dirname($ref->getFileName());
        $this->directoryName = basename($this->directory);
        $this->path = substr($this->directory, strlen(Consolidation::publicDirectory()) + 1);
        if (!$this->name) {
            $this->name = ucwords($this->directoryName);
        }
    }

    /**
     * @param ServerRequest|null $request
     * @param string|null $after
     *
     * @return UriInterface
     */
    public function getURI(
        ?ServerRequest $request = null,
        ?string $after = null
    ): UriInterface {
        $request = $request??$this->getContainer(ServerRequestInterface::class);
        $uri = $request
            ->getUri()
            ->withFragment('')
            ->withQuery('')
            ->withPath("/$this->path/");
        if ($after) {
            $uri = new Uri("$uri$after");
        }
        return $uri;
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
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @param ?AbstractController $controller
     *
     * @return ResponseInterface
     */
    abstract public function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = [],
        ?AbstractController $controller = null
    ) : ResponseInterface;
}
