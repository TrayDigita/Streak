<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Middleware;

use DirectoryIterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use TrayDigita\Streak\Source\ACL\Abstracts\AbstractAccess;
use TrayDigita\Streak\Source\ACL\Abstracts\AbstractIdentity;
use TrayDigita\Streak\Source\ACL\Lists;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;

class AclRegistrationMiddleware extends AbstractMiddleware
{
    public static function thePriority(): int
    {
        return PHP_INT_MIN + 11;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $appDir = Consolidation::appDirectory();
        $target = "$appDir/ACL";
        if (!is_dir($target)) {
            return $handler->handle($request);
        }

        $list = $this->getContainer(Lists::class);
        if (is_dir("$target/Access")) {
            $namespace = "TrayDigita\\Streak\\ACL\\Access";
            foreach (new DirectoryIterator("$target/Access") as $iterator) {
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

                if (!is_a($className, AbstractAccess::class, true)) {
                    continue;
                }
                try {
                    $list->addAccess(new $className($this->getContainer()));
                } catch (Throwable $e) {
                    // pass
                }
            }
        }

        if (is_dir("$target/Identity")) {
            $namespace = "TrayDigita\\Streak\\ACL\\Identity";
            foreach (new DirectoryIterator("$target/Identity") as $iterator) {
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
                if (!is_a($className, AbstractIdentity::class, true)) {
                    continue;
                }
                try {
                    $list->addIdentity(new $className($this->getContainer()));
                } catch (Throwable $e) {
                    // pass
                }
            }
        }

        return $handler->handle($request);
    }
}
