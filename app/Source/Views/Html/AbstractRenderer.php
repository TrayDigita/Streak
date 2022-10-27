<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views\Html;

use JetBrains\PhpStorm\Pure;
use Laminas\I18n\Translator\Translator;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Normalizer;
use TrayDigita\Streak\Source\Helper\Html\Attribute;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Interfaces\Abilities\Clearable;
use TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface;
use TrayDigita\Streak\Source\Interfaces\Html\RenderInterface;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use Wa72\HtmlPageDom\HtmlPageCrawler;

abstract class AbstractRenderer extends AbstractContainerization implements RenderInterface, Clearable
{
    use EventsMethods;

    protected string $title = '';
    protected string $charset = 'UTF-8';
    protected string $bodyContent = '';
    protected string $headerContent = '';

    /**
     * @var string[]
     */
    private array $reset;

    /**
     * @var array<string, AttributeInterface>
     */
    protected array $bodyAttributes = [];

    /**
     * @var array<string, AttributeInterface>
     */
    protected array $htmlAttributes = [];

    #[Pure] public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->reset = [
            'title' => $this->getTitle(),
            'charset' => $this->getCharset(),
        ];
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setCharset(string $charset): static
    {
        $this->charset = strtoupper(trim($charset));
        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setBodyAttributes(array $attributes): static
    {
        $this->bodyAttributes = [];
        foreach ($attributes as $attribute) {
            if ($attribute instanceof AttributeInterface) {
                $this->addBodyAttribute($attribute);
            }
        }
        return $this;
    }

    public function addBodyAttribute(AttributeInterface $attribute): static
    {
        $this->bodyAttributes[$attribute->getName()] = $attribute;
        return $this;
    }

    public function getBodyAttribute(string $name) : ?AttributeInterface
    {
        return $this->bodyAttributes[$name]??null;
    }

    public function getBodyAttributes(): array
    {
        return $this->bodyAttributes;
    }

    public function removeBodyAttribute(AttributeInterface|string $attribute): static
    {
        $attribute = is_string($attribute) ? $attribute : $attribute->getName();
        unset($this->bodyAttributes[$attribute]);
        return $this;
    }

    public function setHtmlAttributes(array $attributes): static
    {
        $this->htmlAttributes = [];
        foreach ($attributes as $attribute) {
            if ($attribute instanceof AttributeInterface) {
                $this->addHtmlAttribute($attribute);
            }
        }
        return $this;
    }

    public function getHtmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    public function addHtmlAttribute(AttributeInterface $attribute): static
    {
        $this->htmlAttributes[$attribute->getName()] = $attribute;
        return $this;
    }

    public function getHtmlAttribute(string $name) : ?AttributeInterface
    {
        return $this->htmlAttributes[$name]??null;
    }

    public function removeHtmlAttribute(AttributeInterface|string $attribute): static
    {
        $attribute = is_string($attribute) ? $attribute : $attribute->getName();
        unset($this->htmlAttributes[$attribute]);
        return $this;
    }

    public function setBodyContent(string $content): static
    {
        $this->bodyContent = $content;
        return $this;
    }

    public function getBodyContent(): string
    {
        return $this->bodyContent;
    }

    public function setHeaderContent(string $content): static
    {
        $this->headerContent = $content;
        return $this;
    }

    public function getHeaderContent(): string
    {
        return $this->headerContent;
    }

    protected function buildStructure() : string
    {
        $head = Normalizer::forceBalanceTags($this->getHeaderContent());
        $charset = $this->getCharset();
        if (!$this->getHtmlAttribute('lang')) {
            $this->addHtmlAttribute(
                new Attribute(
                    'lang',
                    $this->getContainer(Translator::class)->getLocale()
                )
            );
        }
        $html_attributes = $this->getHtmlAttributes();
        $body_attributes = $this->getBodyAttributes();

        $body = Normalizer::forceBalanceTags($this->getBodyContent());

        $html_attributes = $this->eventDispatch('Html:html_attributes', $html_attributes);
        $body_attributes = $this->eventDispatch('Html:body_attributes', $body_attributes);
        $body = $this->eventDispatch('Html:body', $body);
        $head = $this->eventDispatch('Html:head', $head);

        $html_attributes = $this->buildFromAttributes($html_attributes);
        $body_attributes = $this->buildFromAttributes($body_attributes);
        $html_attributes = $html_attributes ? " $html_attributes" : '';
        $body_attributes = $body_attributes ? " $body_attributes" : '';

        $content = '<!DOCTYPE html>';
        $content .= "<html$html_attributes>";
        $content .= "<head>";
        $content .= $head;
        $content .= "</head>";
        $content .= "<body$body_attributes>";
        $content .= $body;

        unset($body, $head);

        $content .= '</body>';
        $content .= '</html>';
        $content = HtmlPageCrawler::create($content);
        $head     = $content->filter('head');
        $titleH   = $head->filter('title');
        $charsetH = $head->filter('meta[charset]');
        $title = $this->getTitle();
        $title = $title ? htmlentities($title) : ($titleH->count() ? $titleH->getInnerHtml() : '');

        $title = (string) $this->eventDispatch('Html:title', $title, $this);
        $charset = (string) $this->eventDispatch('Html:charset', $charset, $this);

        if (!$charsetH->count() && $head->getDOMDocument()) {
            $charsetH = $head->getDOMDocument()->createElement('meta');
            $charsetH->setAttribute('charset', $charset);
            $head->prepend($charsetH);
        } else {
            $charsetH->setAttribute('charset', $charset);
        }

        if (!$titleH->count()) {
            $titleH = $head->getDOMDocument()->createElement('title');
            $titleH->append($title);
            $charsetH->after($titleH);
        } else {
            $titleH->setInnerHtml($title);
        }

        return (string) $this->eventDispatch('Html:content', $content, $this);
    }

    /**
     * @param array $attributes
     *
     * @return string
     */
    protected function buildFromAttributes(array $attributes) : string
    {
        $attr = [];
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof AttributeInterface) {
                continue;
            }
            $name = $attribute->getName();
            if (!$name) {
                continue;
            }
            $attr[] = $attribute->build();
        }
        return implode(' ', $attr);
    }

    public function __toString(): string
    {
        return $this->buildStructure();
    }

    public function render(ResponseInterface $response, int $httpCode = null): ResponseInterface
    {
        $response->getBody()->write((string) $this);
        $charset = $this->getCharset();
        if ($httpCode !== null) {
            $statusMessage = Code::statusMessage($httpCode);
            if ($statusMessage) {
                $response = $response->withStatus($httpCode, $statusMessage);
            }
        }
        return $response
            ->withHeader(
                'Content-Type',
                sprintf(
                    'text/html%s',
                    ($charset ? "; charset=$charset" : '')
                )
            );
    }

    public function clear() : void
    {
        $this->bodyContent = '';
        $this->title = $this->reset['title'];
        $this->charset = $this->reset['charset'];
        $this->headerContent = '';
        $this->htmlAttributes = [];
        $this->bodyAttributes = [];
    }

    public function __destruct()
    {
        $this->clear();
    }
}
