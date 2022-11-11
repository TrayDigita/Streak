<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Middleware;

use Composer\Autoload\ClassLoader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use Throwable;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;
use TrayDigita\Streak\Source\Records\Collections;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class SafePathMiddlewareDebugHandler extends AbstractMiddleware
{
    private bool $inException = false;
    private ?string $quoted = null;

    public function getPriority(): int
    {
        return PHP_INT_MIN;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $error = $this->getContainer(Configurations::class)->get('error');
        $hide = true;
        if ($error instanceof Collections) {
            $hidePath = $error->get('hidePath');
            $hide = is_bool($hidePath) ? $hidePath : $hide;
        }
        if ($hide) {
            $this->eventAddOnce('Middleware:exception', [$this, 'handleExceptions']);
        }
        return $handler->handle($request);
    }

    public function handleExceptions(Throwable $exception): Throwable
    {
        if (!$this->eventIn('Middleware:exception')) {
            return $exception;
        }

        $this->inException = true;
        $this->eventAddOnce('Html:content', [$this, 'handleSafeExceptionResponseHtml']);
        $this->eventAddOnce('Json:data', [$this, 'handleSafeExceptionResponseJson']);
        return $exception;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    private function doReplaceJson(mixed $data) : mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                $data[$key] = $this->doReplaceJson($item);
            }
            return $data;
        }

        if (is_object($data)) {
            // clone to prevent replace old data
            $data = clone $data;
            $ref = new ReflectionObject($data);
            foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                /** @noinspection PhpUndefinedMethodInspection */
                if ($prop->isPublic() && $prop->isReadOnly() !== true) {
                    $prop->setValue(
                        $data,
                        $this->doReplaceJson($prop->getValue($data))
                    );
                }
            }
            return $data;
        }

        if (!is_string($data)) {
            return $data;
        }

        return preg_replace(
            "~$this->quoted~",
            '[ROOT]/',
            $data,
        );
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function handleSafeExceptionResponseJson(array $data): array
    {
        if (!is_array($data['errors']??null)) {
            return $data;
        }

        try {
            $ref  = new ReflectionClass(ClassLoader::class);
            $path = dirname($ref->getFileName(), 3);
        } catch (Throwable) {
            return $data;
        }

        $this->quoted = preg_quote($path . DIRECTORY_SEPARATOR, '~');
        $data['errors'] = $this->doReplaceJson($data['errors']);
        return $data;
    }

    public function handleSafeExceptionResponseHtml(string $content): string
    {
        $event = $this->getContainer(Events::class);
        if (!$this->inException || !$event->inEvent('Html:content')) {
            return $content;
        }

        try {
            $ref  = new ReflectionClass(ClassLoader::class);
            $path = dirname($ref->getFileName(), 3);
        } catch (Throwable) {
            return $content;
        }

        $content = HtmlPageCrawler::create($content);
        $path = preg_quote($path . DIRECTORY_SEPARATOR, '~');
        $body = $content->filter('body');
        // REQUEST_TIME_FLOAT
        $body->setInnerHtml(
            preg_replace(
                "~$path~",
                '[ROOT]/',
                $body->getInnerHtml(),
            )
        );

        return (string) $content;
    }
}
