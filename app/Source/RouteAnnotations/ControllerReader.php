<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations;

use DateInterval;
use DirectoryIterator;
use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Cache\InvalidArgumentException;
use SplFileInfo;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Cache;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Collector\ResultParser;
use TrayDigita\Streak\Source\Helper\Util\ObjectFileReader;
use TrayDigita\Streak\Source\Interfaces\Abilities\Clearable;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\RouteAnnotations\Abstracts\AnnotationController;
use TrayDigita\Streak\Source\RouteAnnotations\Interfaces\AnnotationRequirementsInterface;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class ControllerReader extends AbstractContainerization implements Clearable, Startable
{
    use TranslationMethods,
        EventsMethods;

    const CACHE_EXPIRED_AFTER = 180;

    /**
     * @var ?string
     */
    protected ?string $className = null;

    /**
     * @var ?string
     */
    protected ?string $error = null;

    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * @var bool
     */
    protected static bool $hasLoadAnnotation = false;

    /**
     * @param Container $container
     * @param string $file
     * @param ?string $fileSuffix
     */
    public function __construct(
        Container $container,
        protected string $file,
        protected ?string $fileSuffix = null
    ) {
        parent::__construct($container);

        // load All Annotation
        if (self::$hasLoadAnnotation === false) {
            self::$hasLoadAnnotation = true;
            $nameSpace = __NAMESPACE__ .'\\Annotation';
            // ignore
            AnnotationReader::addGlobalIgnoredName(AnnotationController::class);
            AnnotationReader::addGlobalIgnoredName(AnnotationRequirementsInterface::class);
            foreach (new DirectoryIterator(__DIR__.'/Annotation') as $di) {
                if ($di->isDot() || !$di->isFile() || $di->getExtension() !== 'php') {
                    continue;
                }
                $baseName = $di->getBasename();
                $className = sprintf('%s\\%s', $nameSpace, substr($baseName, 0, -4));
                if (!class_exists($className)) {
                    require_once $di->getRealPath();
                }
                AnnotationReader::addGlobalIgnoredName($className);
            }
        }
    }

    /**
     * @return string
     */
    public function getFile() : string
    {
        return $this->file;
    }

    /**
     * @return string
     */
    public function getPath() : string
    {
        return dirname($this->file);
    }

    /**
     * @return ?string
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @return ?string
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return ?string
     */
    public function getControllerFileSuffix(): ?string
    {
        return $this->fileSuffix;
    }

    /**
     * Read File
     *
     * @return ControllerReader
     */
    public function start() : ControllerReader
    {
        if ($this->started) {
            return $this;
        }
        $this->started = true;
        $spl = new SplFileInfo($this->getFile());
        if (!$spl->getRealPath()) {
            $this->error = sprintf(
                $this->translate('%s does not exists.'),
                $this->file
            );
            return $this;
        }

        $suffix = $this->getControllerFileSuffix();
        $this->file = $spl->getRealPath();
        if ($spl->isDir()) {
            $this->error = sprintf(
                $this->translate('%s is a directory.'),
                $this->file
            );
            return $this;
        }
        if (!$spl->isReadable()) {
            $this->error = sprintf(
                $this->translate('%s is not readable.'),
                $this->file
            );
            return $this;
        }
        if ($spl->getExtension() !== 'php') {
            $this->error = sprintf(
                $this->translate('%s is not php file.'),
                $this->file
            );
            return $this;
        }

        if ($suffix) {
            $oldSuffix = $suffix;
            if (!preg_match('~\.php$~', $suffix)) {
                $suffix .= '.php';
            }
            $baseName = $spl->getBasename();
            $regex = sprintf("~%s$~i", preg_quote($suffix, '~'));
            if (!preg_match($regex, $baseName)) {
                $this->error = sprintf(
                    $this->translate('Suffix %s is not match with basename %s.'),
                    $oldSuffix,
                    substr($baseName, 0, -4)
                );
                return $this;
            }
        }
        unset($spl);
        /**
         * @var Cache $cache
         */
        $cache = $this->getContainer(Cache::class);
        $cacheName = sprintf('resultParser%s', md5($this->file));
        try {
            $item = $cache->getItem($cacheName);
            $result = $item->get();
            if ($result instanceof ResultParser) {
                $resultParser = $result;
            }
        } catch (InvalidArgumentException $e) {
        }
        if (!isset($resultParser)) {
            try {
                $resultParser = $this
                    ->getContainer(ObjectFileReader::class)
                    ->fromFile($this->file);
            } catch (Throwable $e) {
                unset($data);
                $this->error = $e->getMessage();

                return $this;
            }
            if (empty($item)) {
                try {
                    $item = $cache->getItem($cacheName);
                    $expiredAfter = $this->eventDispatch(
                        'ControllerReader:expireAfter',
                        static::CACHE_EXPIRED_AFTER,
                        $item,
                        $resultParser,
                        $this
                    );
                    $expiredAfter = !is_int($expiredAfter) && $expiredAfter instanceof DateInterval
                        ? static::CACHE_EXPIRED_AFTER
                        : $expiredAfter;
                    $item
                        ->set($resultParser)
                        ->expiresAfter($expiredAfter);
                    $cache->save($item);
                } catch (Throwable) {
                }
            }
        }

        $className = $resultParser->getFullClassName();
        if (!$className) {
            $this->error = sprintf(
                $this->translate('Could not found object on file %s.'),
                $this->file
            );
            return $this;
        }
        unset($resultParser);
        if (!class_exists($className)) {
            require_once $this->file;
        }
        if (!class_exists($className)) {
            $this->error = sprintf(
                $this->translate(
                    'Could not found class %s on file %s.'
                ),
                $className,
                $this->file
            );
        }

        $this->className = $className;
        return $this;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function clear()
    {
        $this->error = null;
        $this->className = null;
        $this->started = false;
    }

    public function __destruct()
    {
        $this->clear();
    }
}
