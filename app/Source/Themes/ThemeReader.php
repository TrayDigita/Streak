<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Themes;

use Countable;
use DateInterval;
use DirectoryIterator;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Cache;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Collector\ResultParser;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\ObjectFileReader;
use TrayDigita\Streak\Source\Interfaces\Abilities\Scannable;
use TrayDigita\Streak\Source\Themes\Abstracts\AbstractTheme;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class ThemeReader extends AbstractContainerization implements Scannable, Countable
{
    use TranslationMethods,
        EventsMethods;

    const CACHE_EXPIRED_AFTER = 180;

    final const NONE_EXISTENCE = 1;
    final const UNREADABLE = 2;
    final const ERROR = 3;
    final const CLASS_NOT_FOUND = 4;
    final const INVALID_CLASS = 5;
    final const INVALID_FILE = 6;

    /**
     * @var string
     * @readonly
     */
    public readonly string $themesDirectory;

    /**
     * @var bool
     */
    private bool $scanned = false;

    /**
     * @var ?string
     */
    private ?string $activeTheme = null;

    /**
     * @var array<string, AbstractTheme>
     */
    protected array $themes = [];

    /**
     * @var array
     */
    protected array $invalidThemes = [];

    /**
     * @param Container $container
     * @param string $themesDirectoryName
     */
    public function __construct(Container $container, string $themesDirectoryName = 'themes')
    {
        parent::__construct($container);
        $themesDirectoryName   = trim($themesDirectoryName, '\\/');
        $themeDir              = Consolidation::publicDirectory() . "/$themesDirectoryName";
        $this->themesDirectory = realpath($themeDir)?:$themeDir;
    }

    /**
     * Return themes directory
     *
     * @return string
     */
    public function getThemesDirectory(): string
    {
        return $this->themesDirectory;
    }

    public function scan()
    {
        if ($this->scanned()) {
            return;
        }
        $themeDirectory = $this->getThemesDirectory();
        if (!is_dir($themeDirectory)) {
            return;
        }

        $this->scanned = true;
        $cache = $this->getContainer(Cache::class);
        foreach (new DirectoryIterator($this->getThemesDirectory()) as $theme) {
            if (!$theme->isDir() || $theme->isDot()) {
                continue;
            }
            $themeFile = sprintf('%1$s%2$sTheme.php', $theme->getRealPath(), DIRECTORY_SEPARATOR);
            $baseName = $theme->getBasename();
            $is_readable = true;
            if (!is_file($themeFile) || !($is_readable = is_readable($themeFile))) {
                $this->invalidThemes[$themeFile] = !$is_readable
                    ? self::UNREADABLE
                    : self::NONE_EXISTENCE;
                continue;
            }
            $cacheName = sprintf('resultParser%s', md5($themeFile));
            $mTime = $theme->getMTime();
            try {
                $item = $cache->getItem($cacheName);
                $result = $item->get();
                if (is_array($result)
                    && isset($result['time'], $result['parser'])
                    && $result instanceof ResultParser
                    && $result['time'] === $mTime
                ) {
                    $resultParser = $result;
                }
                unset($result);
            } catch (InvalidArgumentException $e) {
            }
            if (!isset($resultParser)) {
                try {
                    $resultParser = $this
                        ->getContainer(ObjectFileReader::class)
                        ->fromFile($themeFile);
                } catch (Throwable) {
                    unset($data);
                    $this->invalidThemes[$themeFile] = self::ERROR;
                    continue;
                }
                if (empty($item)) {
                    try {
                        $item = $cache->getItem($cacheName);
                        $expiredAfter = $this->eventDispatch(
                            'ThemeReader:expireAfter',
                            static::CACHE_EXPIRED_AFTER,
                            $item,
                            $resultParser,
                            $this
                        );
                        $expiredAfter = !is_int($expiredAfter) && $expiredAfter instanceof DateInterval
                            ? static::CACHE_EXPIRED_AFTER
                            : $expiredAfter;
                        $item
                            ->set(['time' => $mTime, 'parser' => $resultParser])
                            ->expiresAfter($expiredAfter);
                        $cache->save($item);
                    } catch (Throwable) {
                    }
                }
                $className = $resultParser->getFullClassName();
                if (!$className) {
                    $this->invalidThemes[$themeFile] = self::CLASS_NOT_FOUND;
                    continue;
                }
                if (!$resultParser->isSubClassOf(AbstractTheme::class)) {
                    $this->invalidThemes[$themeFile] = self::INVALID_CLASS;
                }
                unset($resultParser);
                try {
                    if (!class_exists($className)) {
                        require_once $themeFile;
                    }
                } catch (Throwable $e) {
                    $this->invalidThemes[$themeFile] = self::ERROR;
                    continue;
                }
                try {
                    $ref = new ReflectionClass($className);
                    // if class name is not as theme file
                    if ($ref->getFileName() !== $themeFile) {
                        $this->invalidThemes[$themeFile] = self::INVALID_FILE;
                        continue;
                    }
                } catch (Throwable $e) {
                    $this->invalidThemes[$themeFile] = self::ERROR;
                    continue;
                }
                $this->themes[$baseName] = new $themeFile($this->getContainer());
            }
        }

        reset($this->themes);
        if (!empty($this->themes)) {
            $this->activeTheme = key($this->themes);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function scanned(): bool
    {
        return $this->scanned;
    }

    public function count() : int
    {
        return count($this->themes);
    }

    /**
     * @return AbstractTheme[]
     */
    public function getThemes(): array
    {
        return $this->themes;
    }

    /**
     * @return array<string, int>
     */
    public function getInvalidThemes(): array
    {
        return $this->invalidThemes;
    }

    /**
     * @return ?AbstractTheme
     */
    public function getActiveTheme() : ?AbstractTheme
    {
        return $this->activeTheme
            ? ($this->themes[$this->activeTheme]??null)
            : null;
    }

    /**
     * @param string $activeTheme
     *
     * @return bool
     */
    public function setActiveTheme(string $activeTheme) : bool
    {
        $exist = isset($this->themes[$activeTheme]);
        if ($exist) {
            $this->activeTheme = $activeTheme;
        }
        return $exist;
    }
}
