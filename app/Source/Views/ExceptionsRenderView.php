<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views;

use JetBrains\PhpStorm\ArrayShape;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Views\Html\AbstractRenderer;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class ExceptionsRenderView extends AbstractRenderer
{
    use TranslationMethods;

    /**
     * @var string
     */
    public readonly string $defaultTitle;

    /**
     * @var Throwable|HttpSpecializedException
     */
    protected Throwable|HttpSpecializedException $exceptions;

    /**
     * @var string
     */
    protected string $title;

    /**
     * @var int
     */
    protected int $code = 500;

    /**
     * @param Throwable|HttpSpecializedException $exception
     * @param Container $container
     */
    public function __construct(Throwable|HttpSpecializedException $exception, Container $container)
    {
        $this->defaultTitle = $this->translate('500 Internal Server Error');
        $this->title = $this->defaultTitle;
        $this->exceptions = $exception;
        if ($exception instanceof HttpSpecializedException) {
            $this->code = $exception->getCode();
            $this->setTitle($exception->getTitle());
        }
        parent::__construct($container);
    }

    /**
     * @return HttpSpecializedException|Throwable
     */
    public function getExceptions(): Throwable|HttpSpecializedException
    {
        return $this->exceptions;
    }

    /**
     * @param HttpSpecializedException|Throwable $exceptions
     */
    public function setExceptions(Throwable|HttpSpecializedException $exceptions): void
    {
        $this->exceptions = $exceptions;
    }

    /**
     * @return string
     */
    protected function buildStructure(): string
    {
        $application = $this->getContainer(Application::class);
        if ($application->isDevelopment()) {
            $body = sprintf('<h1>%s</h1>', htmlentities($this->getTitle()));
            $body .= $this->renderExceptionFragment($this->exceptions);
        } else {
            if ($this->exceptions instanceof HttpSpecializedException) {
                $code = $this->exceptions->getCode();
                $desc = $this->exceptions->getMessage();
                $message = $this->exceptions->getDescription();
            } else {
                $desc = $this->translate('Internal server error.');
                $message = $this->translate(
                    'Unexpected condition encountered preventing server from fulfilling request.'
                );
                $code = 500;
            }
            $body = sprintf('<h1 class="hero">%d</h1>', $code);
            $body .= sprintf('<h3 class="description">%s</h3>', $desc);
            $message && $body .= sprintf('<p>%s</p>', $message);
        }
        $this->setHeaderContent(<<<HTML
<style>
body {
    padding:0;
    margin:0;
    color: #333;
    font-size: 14px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial,
     "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji",
     "Segoe UI Symbol", "Noto Color Emoji";
}
div > strong {
    min-width: 100px;
    display: inline-block;
}
h2, h1 {
    margin-top: 1em;
    margin-bottom: 0.5rem;
    font-weight: 500;
    line-height: 1.2;
}
h1 {
    font-size: 3em;
}
h3 {
    font-size: 1.4em;
    margin-bottom: .3em;
}
pre {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: .9em;
    padding: 1rem;
    overflow: auto;
    margin: 0 auto;
    background: #f1f1f1;
    border-left: 3px solid;
    max-height: 90vh;
    min-height: 200px;
}
.wrap {
    width: 800px;
    max-width: 90%;
    margin: 0 auto;
}
</style>
HTML
        );

        $this->setBodyContent(sprintf('<div class="wrap">%s</div>', $body));
        unset($body);
        return parent::buildStructure();
    }

    /**
     * @param Throwable $exception
     *
     * @return string
     */
    private function renderExceptionFragment(Throwable $exception): string
    {
        $html = sprintf(
            '<div><strong>%s</strong>: %s</div>',
            $this->translate('Type'),
            get_class($exception)
        );
        /** @var int|string $code */
        $code = $exception->getCode();
        $html .= sprintf(
            '<div><strong>%s</strong>: %s</div>',
            $this->translate('Code'),
            $code
        );
        $html .= sprintf(
            '<div><strong>%s</strong>: %s</div>',
            $this->translate('Message'),
            htmlentities($exception->getMessage())
        );
        $html .= sprintf(
            '<div><strong>%s</strong>: %s</div>',
            $this->translate('File'),
            $exception->getFile()
        );
        $html .= sprintf(
            '<div><strong>%s</strong>: %s</div>',
            $this->translate('Line'),
            $exception->getLine()
        );

        $html .= sprintf('<h2>%s</h2>', $this->translate('Trace'));
        $html .= sprintf('<pre>%s</pre>', htmlentities($exception->getTraceAsString()));
        return $html;
    }

    /**
     * @param ResponseInterface $response
     * @param int|null $httpCode
     *
     * @return ResponseInterface
     */
    public function render(ResponseInterface $response, ?int $httpCode = null): ResponseInterface
    {
        $httpCode = $httpCode??$this->code;
        return parent::render($response->withStatus($httpCode));
    }

    #[ArrayShape([
        'exceptions' => 'Throwable|\Slim\Exception\HttpSpecializedException',
        'title' => "string",
        'charset' => "string",
        'headerContent' => "string",
        'bodyContent' => "string",
        'htmlAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'bodyAttributes' => "\TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface[]",
        'arguments' => "array"
    ])] public function toArray(): array
    {
        return [
            'exceptions' => $this->getExceptions()
        ] + parent::toArray();
    }
}
