<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views;

use JetBrains\PhpStorm\ArrayShape;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface;
use TrayDigita\Streak\Source\Views\Html\AbstractRenderer;
use TrayDigita\Streak\Source\Views\Html\Renderer;

class MultiRenderView extends AbstractRenderer
{
    protected AbstractRenderer $renderer;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->renderer = $this
            ->getContainer(Renderer::class)
            ->createDefaultRenderView();
        parent::__construct($container);
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->renderer->getArguments();
    }

    public function setArguments(array $arguments): static
    {
        $this->renderer->setArguments($arguments);
        return $this;
    }

    public function setArgument(float|int|string $name, $value): static
    {
        $this->renderer->setArgument($name, $value);
        return $this;
    }

    public function getArgument(float|int|string $name, mixed $default = null, &$found = null): mixed
    {
        return $this->renderer->getArgument($name, $found, $found);
    }

    public function setTitle(string $title): static
    {
        $this->renderer->setTitle($title);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->renderer->getTitle();
    }

    public function setCharset(string $charset): static
    {
        $this->renderer->setCharset($charset);
        return $this;
    }

    public function getCharset(): string
    {
        return $this->renderer->getCharset();
    }

    public function setBodyAttributes(array $attributes): static
    {
        $this->renderer->setBodyAttributes($attributes);
        return $this;
    }

    public function addBodyAttribute(AttributeInterface $attribute): static
    {
        $this->renderer->addBodyAttribute($attribute);
        return $this;
    }

    public function getBodyAttribute(string $name): ?AttributeInterface
    {
        return $this->renderer->getBodyAttribute($name);
    }

    public function getBodyAttributes(): array
    {
        return $this->renderer->getBodyAttributes();
    }

    public function removeBodyAttribute(string|AttributeInterface $attribute): static
    {
        $this->renderer->removeBodyAttribute($attribute);
        return $this;
    }

    public function setHtmlAttributes(array $attributes): static
    {
        $this->renderer->setHtmlAttributes($attributes);
        return $this;
    }

    public function getHtmlAttributes(): array
    {
        return $this->renderer->getHtmlAttributes();
    }

    public function addHtmlAttribute(AttributeInterface $attribute): static
    {
        $this->renderer->addHtmlAttribute($attribute);
        return $this;
    }

    public function getHtmlAttribute(string $name): ?AttributeInterface
    {
        return $this->renderer->getHtmlAttribute($name);
    }

    public function removeHtmlAttribute(string|AttributeInterface $attribute): static
    {
        $this->renderer->removeHtmlAttribute($attribute);
        return $this;
    }

    public function setBodyContent(string $content): static
    {
        $this->renderer->setBodyContent($content);
        return $this;
    }

    public function getBodyContent(): string
    {
        return $this->renderer->getBodyContent();
    }

    public function setHeaderContent(string $content): static
    {
        $this->renderer->setHeaderContent($content);
        return $this;
    }

    public function getHeaderContent(): string
    {
        return $this->renderer->getHeaderContent();
    }

    protected function buildFromAttributes(array $attributes): string
    {
        return $this->renderer->buildFromAttributes($attributes);
    }

    public function __toString(): string
    {
        return $this->renderer->__toString();
    }

    public function render(ResponseInterface $response, int $httpCode = null): ResponseInterface
    {
        return $this->renderer->render($response, $httpCode);
    }

    public function clear(): void
    {
        $this->renderer->clear();
    }

    #[ArrayShape([
        'exceptions' => "\Throwable|\Slim\Exception\HttpSpecializedException",
        'title' => "string",
        'charset' => "string",
        'headerContent' => "string",
        'bodyContent' => "string",
        'htmlAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'bodyAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'arguments' => "array"
    ])] public function toArray(): array
    {
        return $this->renderer->toArray();
    }

    /**
     * @param array $array
     * @param mixed ...$constructorArgument
     *
     * @return static
     */
    public static function fromArray(array $array = [], ...$constructorArgument): static
    {
        return parent::fromArray($array, $constructorArgument);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->renderer->__destruct();
    }

    /**
     * @param array $array
     */
    private function appendRenderArray(array $array)
    {
        foreach ($array as $key => $v) {
            if (method_exists($this->renderer, "set$key")) {
                $this->renderer->{"set$key"}($v, "");
            }
        }
    }

    /**
     * @param Throwable $exception
     *
     * @return $this
     */
    public function toExceptionRenderView(Throwable $exception) : static
    {
        if (!$this->renderer instanceof ExceptionsRenderView) {
            $array = $this->renderer->toArray();
            $this->renderer = $this
                ->getContainer(Renderer::class)
                ->createExceptionRenderView($exception);
            $this->appendRenderArray($array);
        }

        $this->renderer->setExceptions($exception);
        return $this;
    }

    /**
     * @return $this
     */
    public function toStandardRenderView(): static
    {
        if (!$this->renderer instanceof DefaultRenderView) {
            $array = $this->renderer->toArray();
            $this->renderer = $this
                ->getContainer(Renderer::class)
                ->createDefaultRenderView();
            $this->appendRenderArray($array);
        }

        return $this;
    }

    /**
     * @return string
     */
    protected function buildStructure(): string
    {
        return $this->renderer->buildStructure();
    }
}
