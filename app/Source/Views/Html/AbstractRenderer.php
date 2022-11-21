<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views\Html;

use DOMAttr;
use JetBrains\PhpStorm\ArrayShape;
use Laminas\I18n\Translator\Translator;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Html\Attribute;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Interfaces\Abilities\Clearable;
use TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface;
use TrayDigita\Streak\Source\Interfaces\Html\RenderInterface;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\SystemInitialHandler;
use TrayDigita\Streak\Source\Themes\ThemeReader;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use Wa72\HtmlPageDom\HtmlPageCrawler;

abstract class AbstractRenderer extends AbstractContainerization implements RenderInterface, Clearable
{
    use EventsMethods;

    final const SKIP_THEME = 'skipTheme';
    final const SKIP_HTML_ATTRIBUTES = 'skipHTMLAttribute';
    final const SKIP_BODY_ATTRIBUTES = 'skipBodyAttribute';

    /**
     * @var string
     */
    protected string $title = '';

    /**
     * @var string
     */
    protected string $charset = 'UTF-8';

    /**
     * @var string
     */
    protected string $bodyContent = '';

    /**
     * @var string
     */
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

    /**
     * @var array
     */
    protected array $arguments = [];

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->reset = [
            'title'   => $this->getTitle(),
            'charset' => $this->getCharset(),
        ];
        parent::__construct($container);
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     *
     * @return $this
     */
    public function setArguments(array $arguments) : static
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @param string|float|int $name
     * @param $value
     *
     * @return $this
     */
    public function setArgument(string|float|int $name, $value) : static
    {
        $this->arguments[$name] = $value;
        return $this;
    }

    /**
     * @param string|float|int $name
     * @param mixed|null $default
     * @param null $found
     *
     * @return mixed
     */
    public function getArgument(string|float|int $name, mixed $default = null, &$found = null) : mixed
    {
        $found = array_key_exists($name, $this->arguments);
        return $found ? $this->arguments[$name] : $default;
    }

    /**
     * @param string $title
     *
     * @return $this
     */
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

    /**
     * @param array $attributes
     *
     * @return $this
     */
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

    /**
     * @param AttributeInterface $attribute
     *
     * @return $this
     */
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

    /**
     * @return string
     */
    public function getHeaderContent(): string
    {
        return $this->headerContent;
    }

    /**
     * @return string
     * @noinspection HtmlRequiredLangAttribute
     */
    protected function buildStructure() : string
    {
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

        $html_attributes = $this->eventDispatch('Html:html_attributes', $html_attributes);
        $body_attributes = $this->eventDispatch('Html:body_attributes', $body_attributes);

        // $html_attributes = $this->buildFromAttributes($html_attributes);
        $this->eventAdd('html:attribute', fn () => $html_attributes);

        // $body_attributes = $this->buildFromAttributes($body_attributes);
        $this->eventAdd('body:attribute', fn () => $body_attributes);

        if ($this->getArgument(self::SKIP_THEME) !== true) {
            $activeTheme = $this->getContainer(ThemeReader::class)->getActiveTheme();
            if ($activeTheme) {
                // render body first -> then header
                $body = $activeTheme->getBody();
                $body->rewind();
                $header = $activeTheme->getHeader();
                $header->rewind();
                $footer = $activeTheme->getFooter();
                $footer->rewind();
                $footer = (string)$footer;
                $header = (string)$header;
                $body   = (string)$body;
                $body   .= $footer;
                unset($footer);
                $this->setHeaderContent($header);
                $this->setBodyContent($body);
                $header = $body = null;
                unset($header, $body);
            }
        }

        $head    = $this->getHeaderContent();
        $content = $this->getBodyContent();
        $skipFailComments = '(?:<!--.*(*SKIP)(*FAIL))';
        if (!preg_match("~$skipFailComments|<!DOCTYPE[^>]*>~i", $head)) {
            $head = "<!DOCTYPE html>\n".ltrim($head);
        }

        if (!preg_match("~$skipFailComments|<html[^>]*>~i", $head)) {
            $head = preg_replace(
                "~$skipFailComments|(<!DOCTYPE[^>]*>)~i",
                "\$1\n<html>",
                $head
            );
        }

        if (!preg_match("~$skipFailComments|<head[^>]*>~i", $head)) {
            $head = preg_replace(
                "~$skipFailComments|(<html[^>]*>)~i",
                "\$1\n<head>",
                $head
            );
        }

        if (!preg_match("~$skipFailComments|</head[^>]*>~i", $head)) {
            $head = preg_match("~$skipFailComments|(<body[^>]*>)~i", $head)
                ? preg_replace("~$skipFailComments|(<body[^>]*>)~i", "\n</head>\$1", $head)
                : "$head\n</head>\n";
        }

        if (!preg_match("~$skipFailComments|<body[^>]*>~i", $head.$content)) {
            $head = preg_match("~$skipFailComments|(</head[^>]*>)~i", $head)
                ? preg_replace("~$skipFailComments|(</head[^>]*>)~i", "\$1\n<body>", $head)
                : "$head\n<body>\n";
        }

        if (!preg_match("~$skipFailComments|</body[^>]*>~i", $head.$content)) {
            $content = "$content\n</body>\n";
        }

        if (!preg_match("~$skipFailComments|</html>~", $content)) {
            $content .= '</html>';
        }

        if (!preg_match("~$skipFailComments|</body>~i", $content)) {
            $content = preg_replace(
                "~$skipFailComments|(</html>)~i",
                "\n</body>\n$1",
                $content
            );
        }

        $head    = $this->eventDispatch('Html:head', $head);
        $content = $this->eventDispatch('Html:body', $content);
        $app = $this->getContainer(Configurations::class)->get('application');
        $filterHtml = $app instanceof Collections ? $app->get('filterHtml') : false;
        $filterHtml = (bool) $filterHtml;
        $filterHtml = $this->eventDispatch('Html:filter_html', $filterHtml) === true;

        $content = $head . $content;
        unset($body, $head);
        if ($filterHtml) {
            $content = HtmlPageCrawler::create($content);
            $html    = $content->filter('html');
            $body    = $content->filter('body');
            if ($html->count()) {
                /**
                 * @var AttributeInterface[] $html_attributes
                 */
                foreach ((array)$html_attributes as $v) {
                    if (!$v instanceof AttributeInterface || $v->getName() === '') {
                        continue;
                    }
                    $html->setAttribute($v->getName(), $v->getValue());
                }
            }

            if ($body->count()) {
                /**
                 * @var AttributeInterface[] $body_attributes
                 */
                foreach ((array)$body_attributes as $v) {
                    if (!$v instanceof AttributeInterface || $v->getName() === '') {
                        continue;
                    }
                    $body->setAttribute($v->getName(), $v->getValue());
                }
            }

            $head         = $content->filter('head');
            $titleH       = $head->filter('title');
            $charsetH     = $head->filter('meta[charset]');
            $title        = $this->getTitle();
            $titleContent = ($titleH->count() ? $titleH->getInnerHtml() : '');
            $titleContent = trim($titleContent) ?: '';
            $title        = $titleContent ?: htmlentities($title);
            $title        = html_entity_decode($title);
            $title        = (string)$this->eventDispatch('head:title', $title, $this);
            $charset      = (string)$this->eventDispatch('head:charset', $charset, $this);

            unset($titleContent);
            if (!$charsetH->count() && $head->getDOMDocument()) {
                $charsetH = $head->getDOMDocument()->createElement('meta');
                $charsetH->setAttribute('charset', $charset);
                $head->prepend($charsetH);
            } else {
                $charsetH->setAttribute('charset', $charset);
            }

            if (!$titleH->count() && $head->getDOMDocument()) {
                $titleH = $head->getDOMDocument()->createElement('title');
                $titleH->append($title);
                $charsetH->after($titleH);
            } else {
                $titleH->setInnerHtml($title);
            }
            $head->prepend("\n");
            $charsetH->before("    ");
            $titleH->before("    ");
            $charsetH->after("\n");
        } else {
            $content = preg_replace_callback(
                '~<(head)>(.*)</\1>~ims',
                function ($head) use ($charset, $skipFailComments) {
                    $head = $head[2];
                    $title  = $this->getTitle();
                    $title = html_entity_decode($title);
                    $title = (string)$this->eventDispatch('head:title', $title, $this);
                    $charset = (string)$this->eventDispatch('head:charset', $charset, $this);
                    $head   = trim($head, "\n");
                    $header = HtmlPageCrawler::create($head);
                    $domTitle = $header->filter('title');
                    if ($domTitle->count()) {
                        if ($title !== '' && trim($domTitle->getInnerHtml()) === '') {
                            $head = preg_replace_callback(
                                "~$skipFailComments|<title>\s*</title>~",
                                function () use ($title) {
                                    $title = htmlentities($title);
                                    return "    <title>$title</title>";
                                },
                                $head
                            );
                        }
                    } else {
                        $head = sprintf(
                            "    <title>%s</title>\n%s",
                            htmlentities($title),
                            $head
                        );
                    }
                    if (!preg_match("~$skipFailComments|<meta\s+charset=[^>]+>~i", $head)) {
                        $head = sprintf(
                            "    <meta charset=\"%s\">\n%s",
                            htmlspecialchars($charset),
                            $head
                        );
                    }

                    return sprintf("<head>\n%s\n</head>", $head);
                },
                $content
            );

            $html = HtmlPageCrawler::create($content)->filter('html');
            if ($html->count()) {
                if (!empty($html_attributes)
                    && $this->getArgument(self::SKIP_HTML_ATTRIBUTES) !== true
                ) {
                    foreach ((array)$html_attributes as $v) {
                        if (!$v instanceof AttributeInterface || $v->getName() === '') {
                            continue;
                        }
                        $html->setAttribute($v->getName(), $v->getValue());
                    }
                    /**
                     * @var DOMAttr $attribute
                     */
                    $html_attributes = [];
                    foreach ($html->getNode(0)->attributes as $attribute) {
                        $html_attributes[$attribute->name] = new Attribute(
                            $attribute->name,
                            $attribute->value
                        );
                    }
                    $html_attributes = $this->buildFromAttributes($html_attributes);
                    $content         = preg_replace_callback(
                        "~$skipFailComments|<html[^>]*>~i",
                        fn() => "<html $html_attributes>",
                        $content
                    );
                }
                $body = $html->filter('body');
                if ($body->count()
                    && !empty($body_attributes)
                    && $this->getArgument(self::SKIP_BODY_ATTRIBUTES) !== true
                ) {
                    foreach ((array)$body_attributes as $v) {
                        if (!$v instanceof AttributeInterface || $v->getName() === '') {
                            continue;
                        }
                        $body->setAttribute($v->getName(), $v->getValue());
                    }
                    /**
                     * @var DOMAttr $attribute
                     */
                    $body_attributes = [];
                    foreach ($body->getNode(0)->attributes as $attribute) {
                        $body_attributes[$attribute->name] = new Attribute(
                            $attribute->name,
                            $attribute->value
                        );
                    }
                    $body_attributes = $this->buildFromAttributes($body_attributes);
                    $content         = preg_replace_callback(
                        "~$skipFailComments|<body[^>]*>~i",
                        fn() => "<body $body_attributes>",
                        $content
                    );
                }
            }
        }

        return (string) $this->eventDispatch('Html:content', (string) $content, $this);
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

    /**
     * @param ResponseInterface $response
     * @param int|null $httpCode
     *
     * @return ResponseInterface
     */
    public function render(ResponseInterface $response, int $httpCode = null): ResponseInterface
    {
        $body = $response->getBody();
        if ($body->getSize() > 0) {
            $body->close();
            $body = $this->getContainer(SystemInitialHandler::class)->createStream();
        }

        $response = $response->withBody($body);
        $body->write((string) $this);
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
        $this->arguments = [];
    }

    #[ArrayShape([
        'title' => "string",
        'charset' => "string",
        'headerContent' => "string",
        'bodyContent' => "string",
        'htmlAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'bodyAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'arguments' => "array"
    ])] public function toArray() : array
    {
        return [
            'title' => $this->getTitle(),
            'charset' => $this->getCharset(),
            'headerContent' => $this->getHeaderContent(),
            'bodyContent' => $this->getBodyContent(),
            'htmlAttributes' => $this->getHtmlAttributes(),
            'bodyAttributes' => $this->getBodyAttributes(),
            'arguments' => $this->getArguments(),
        ];
    }

    /**
     * @param array $array
     * @param ...$constructorArgument
     * @return static
     */
    public static function fromArray(array $array = [], ...$constructorArgument): static
    {
        $object = new static(...$constructorArgument);
        foreach ($array as $key => $v) {
            if (method_exists($object, "set$key")) {
                $object->{"set$key"}($v, "");
            }
        }
        return $object;
    }

    public function __destruct()
    {
        $this->clear();
    }
}
