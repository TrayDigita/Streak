<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Middleware;

use Composer\Autoload\ClassLoader;
use DirectoryIterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;
use TrayDigita\Streak\Source\Scheduler\Scheduler;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class SchedulerRegistrationMiddleware extends AbstractMiddleware
{
    private bool $inException = false;

    public static function thePriority(): int
    {
        return PHP_INT_MIN + 10;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $target = dirname(__DIR__).'/Scheduler';
        if (!is_dir($target)) {
            return $handler->handle($request);
        }
        $scheduler = $this->getContainer(Scheduler::class);
        $namespace = "TrayDigita\\Streak\\Scheduler";
        foreach (new DirectoryIterator($target) as $iterator) {
            if (!$iterator->isFile() && $iterator->getExtension() !== 'php') {
                continue;
            }
            $baseName = $iterator->getBasename();
            $baseName = substr($baseName, 0, -4);
            if (!Validator::isValidClassName($baseName)) {
                continue;
            }
            $className = "$namespace\\$baseName";
            if (!class_exists($className)) {
                require_once $iterator->getRealPath();
            }
            if (!is_a($className, AbstractTask::class, true)) {
                continue;
            }
            try {
                $scheduler->register($className);
            } catch (Throwable $e) {
                // pass
            }
        }

        return $handler->handle($request);
    }
}
