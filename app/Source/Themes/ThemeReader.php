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
use TrayDigita\Streak\Source\Helper\Util\Collector\ClassDefinition;
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
    final const NO_HEADER = 7;
    final const NO_FOOTER = 8;
    final const NO_BODY = 9;

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
     * @var string
     */
    protected string $headerFilePath = 'header.php';

    /**
     * @var string
     */
    protected string $bodyFilePath = 'body.php';

    /**
     * @var string
     */
    protected string $footerFilePath = 'footer.php';

    /**
     * @var string
     */
    protected string $themeFileName = 'Theme.php';

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

    /**
     * @return string
     */
    public function getHeaderFilePath(): string
    {
        return $this->headerFilePath;
    }

    /**
     * Get Header file
     *
     * @return ?string
     */
    public function getHeaderFile() : ?string
    {
        $activeTheme = $this->getActiveTheme();
        if (!$activeTheme) {
            return null;
        }
        return sprintf(
            '%1$s%2$s%3$s',
            $activeTheme->directory,
            DIRECTORY_SEPARATOR,
            $this->getHeaderFilePath()
        );
    }

    /**
     * @return string
     */
    public function getBodyFilePath(): string
    {
        return $this->bodyFilePath;
    }

    /**
     * Get Header file
     *
     * @return ?string
     */
    public function getBodyFile() : ?string
    {
        $activeTheme = $this->getActiveTheme();
        if (!$activeTheme) {
            return null;
        }
        return sprintf(
            '%1$s%2$s%3$s',
            $activeTheme->directory,
            DIRECTORY_SEPARATOR,
            $this->getBodyFilePath()
        );
    }

    /**
     * @return string
     */
    public function getFooterFilePath(): string
    {
        return $this->footerFilePath;
    }

    /**
     * Get Header file
     *
     * @return ?string
     */
    public function getFooterFile() : ?string
    {
        $activeTheme = $this->getActiveTheme();
        if (!$activeTheme) {
            return null;
        }
        return sprintf(
            '%1$s%2$s%3$s',
            $activeTheme->directory,
            DIRECTORY_SEPARATOR,
            $this->getFooterFilePath()
        );
    }

    /**
     * Scan the themes
     */
    public function scan()
    {
        if ($this->scanned()) {
            return;
        }

        $this->scanned = true;
        $themeDirectory = $this->getThemesDirectory();
        if (!is_dir($themeDirectory)) {
            return;
        }

        $this->headerFilePath = $this->eventDispatch('ThemeReader:headerFile', $this->headerFilePath);
        $this->bodyFilePath   = $this->eventDispatch('ThemeReader:bodyFile', $this->bodyFilePath);
        $this->footerFilePath = $this->eventDispatch('ThemeReader:footerFile', $this->footerFilePath);
        $this->themeFileName = $this->eventDispatch('ThemeReader:themeFile', $this->themeFileName);

        $cache = $this->getContainer(Cache::class);
        foreach (new DirectoryIterator($themeDirectory) as $theme) {
            if (!$theme->isDir() || $theme->isDot()
                || str_starts_with($theme->getBasename(), '.')
            ) {
                continue;
            }
            $themeDir = $theme->getRealPath();
            $themeFile = sprintf(
                '%1$s%2$s%3$s',
                $theme->getRealPath(),
                DIRECTORY_SEPARATOR,
                $this->themeFileName
            );
            $baseName = $theme->getBasename();
            $is_readable = true;
            if (!is_file($themeFile) || !($is_readable = is_readable($themeFile))) {
                $this->invalidThemes[$themeFile] = !$is_readable
                    ? self::UNREADABLE
                    : self::NONE_EXISTENCE;
                continue;
            }
            if (!is_file("$themeDir/$this->headerFilePath")) {
                $this->invalidThemes[$themeDir] = self::NO_HEADER;
                continue;
            }
            if (!is_file("$themeDir/$this->bodyFilePath")) {
                $this->invalidThemes[$themeDir] = self::NO_BODY;
                continue;
            }
            if (!is_file("$themeDir/$this->footerFilePath")) {
                $this->invalidThemes[$themeDir] = self::NO_FOOTER;
                continue;
            }

            $cacheName = sprintf('resultClassDefinition%s', md5($themeFile));
            $mTime = $theme->getMTime();
            try {
                $item = $cache->getItem($cacheName);
                $result = $item->get();
                if (is_array($result)
                    && isset($result['time'], $result['parser'])
                    && $result instanceof ClassDefinition
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
                } catch (Throwable $e) {
                    unset($data);
                    $this->invalidThemes[$themeDir] = self::ERROR;
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
                $className = $resultParser->fullName;
                if (!$className) {
                    $this->invalidThemes[$themeDir] = self::CLASS_NOT_FOUND;
                    continue;
                }

                if (!$resultParser->isSubClassOf(AbstractTheme::class)) {
                    $this->invalidThemes[$themeDir] = self::INVALID_CLASS;
                    continue;
                }

                if (!$resultParser->methods->hasMethod('doRender')
                    || !$resultParser->methods->hasMethod('doRenderException')
                ) {
                    $this->invalidThemes[$themeDir] = self::INVALID_CLASS;
                    continue;
                }
                unset($resultParser);
                try {
                    if (!class_exists($className)) {
                        require_once $themeFile;
                    }
                } catch (Throwable $e) {
                    $this->invalidThemes[$themeDir] = self::ERROR;
                    continue;
                }
                try {
                    $ref = new ReflectionClass($className);
                    if (!$ref->isSubclassOf(AbstractTheme::class)) {
                        $this->invalidThemes[$themeDir] = self::INVALID_CLASS;
                        continue;
                    }
                    // if class name is not as theme file
                    if ($ref->getFileName() !== $themeFile) {
                        $this->invalidThemes[$themeDir] = self::INVALID_FILE;
                        continue;
                    }
                } catch (Throwable $e) {
                    $this->invalidThemes[$themeDir] = self::ERROR;
                    continue;
                }
                $this->set(new $className($this->getContainer()));
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
     * Add theme if not exists
     *
     * @param AbstractTheme $theme
     *
     * @return bool
     */
    public function add(AbstractTheme $theme): bool
    {
        if (isset($this->themes[$theme->directoryName])) {
            return false;
        }
        $this->themes[$theme->directoryName] = $theme;
        return true;
    }

    /**
     * @param AbstractTheme $theme
     */
    public function set(AbstractTheme $theme)
    {
        $this->themes[$theme->directoryName] = $theme;
    }

    /**
     * @param string|AbstractTheme $activeTheme
     *
     * @return bool
     */
    public function setActiveTheme(string|AbstractTheme $activeTheme) : bool
    {
        $activeTheme = is_string($activeTheme)
            ? $activeTheme
            : $activeTheme->directoryName;
        $exist = isset($this->themes[$activeTheme]);
        if ($exist) {
            $this->activeTheme = $activeTheme;
        }
        return $exist;
    }
}
