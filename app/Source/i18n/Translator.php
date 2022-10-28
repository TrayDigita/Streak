<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\i18n;

use Gettext\Languages\CldrData;
use JetBrains\PhpStorm\Pure;
use Laminas\I18n\Exception\ExceptionInterface;
use Laminas\I18n\Exception\InvalidArgumentException;
use Laminas\I18n\Translator\Loader\FileLoaderInterface;
use Laminas\I18n\Translator\Loader\Gettext;
use Laminas\I18n\Translator\Loader\Ini;
use Laminas\I18n\Translator\Loader\PhpArray;
use Laminas\I18n\Translator\TextDomain;
use Laminas\I18n\Translator\Translator as LaminasTranslator;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\i18n\Loader\Json;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class Translator extends LaminasTranslator
{
    use Containerize,
        EventsMethods;

    const DEFAULT_LOCALE = 'en';
    const DEFAULT_TEXTDOMAIN = 'default';

    /**
     * @var array<string, array<string, TextDomain>>
     */
    protected $messages = [];

    /**
     * @var string default text domain
     */
    protected string $textDomain = self::DEFAULT_TEXTDOMAIN;

    /**
     * @var string Default locale
     */
    protected $locale = self::DEFAULT_LOCALE;

    /**
     * @var string Default fallback locale
     */
    protected $fallbackLocale = null;

    /**
     * @var array<string>
     */
    protected array $directories = [];

    /**
     * @var bool
     */
    private bool $loadFoundOne = true;

    /**
     * @var array<string, array<string, array<string, bool|null>>
     */
    protected array $registeredDirectories = [];

    /**
     * @var Container
     * @readonly
     */
    public readonly Container $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->setTextDomain(static::DEFAULT_TEXTDOMAIN);
        $this->setLocale(static::DEFAULT_LOCALE);
    }

    /**
     * @return bool
     */
    public function isLoadFoundOne(): bool
    {
        return $this->loadFoundOne;
    }

    /**
     * @param bool $loadFoundOne
     *
     * @return $this
     */
    public function setLoadFoundOne(bool $loadFoundOne): static
    {
        $this->loadFoundOne = $loadFoundOne;
        return $this;
    }

    /**
     * @return string
     */
    public function getTextDomain(): string
    {
        return $this->textDomain;
    }

    /**
     * @param string $textDomain
     *
     * @return $this
     */
    public function setTextDomain(string $textDomain): static
    {
        $this->textDomain = $textDomain;
        return $this;
    }

    public function normalizeLocale(string $locale) : string
    {
        if ('' === $locale || '.' === $locale[0]) {
            return $this->getLocale();
        }

        $availableLanguages = CldrData::getLanguageNames();
        if (!preg_match('/^([a-z]{2})(?:[-_]([a-z]{2}))?(?:([a-z]{2})(?:[-_]([a-z]{2}))?)?(?:\..*)?$/i', $locale, $m)) {
            return $this->getLocale();
        }

        if (!empty($m[4])) {
            $currentLocale = strtolower($m[1]).'_'.ucfirst(strtolower($m[2].$m[3])).'_'.strtoupper($m[4]);
            if (isset($availableLanguages[$currentLocale])) {
                return $currentLocale;
            }
        }

        if (!empty($m[3])) {
            $currentLocale = strtolower($m[1]).'_'.ucfirst(strtolower($m[2].$m[3]));
            if (isset($availableLanguages[$currentLocale])) {
                return $currentLocale;
            }
        }

        if (!empty($m[2])) {
            $currentLocale = strtolower($m[1]) . '_' . strtoupper($m[2]);
            if (isset($availableLanguages[$currentLocale])) {
                return $currentLocale;
            }
        }
        $currentLocale = strtolower($m[1]);
        return isset($availableLanguages[$currentLocale])
            ? $currentLocale
            : $this->getLocale();
    }

    public function setLocale($locale) : static
    {
        if (!is_string($locale)) {
            return $this;
        }

        return parent::setLocale($this->normalizeLocale($locale));
    }

    public function filterLocale($locale) : string
    {
        return !is_string($locale) || trim($locale) === ''
            ? $this->getLocale()
            : $locale;
    }

    #[Pure] public function filterTextDomain($textDomain) : string
    {
        return !is_string($textDomain) || trim($textDomain) === ''
            ? $this->getTextDomain()
            : $textDomain;
    }

    /**
     * @param string $languageDirectory
     * @param string $textDomain
     *
     * @return bool
     */
    public function addDirectory(
        string $languageDirectory,
        string $textDomain
    ) : bool {

        $textDomain = $this->filterTextDomain($textDomain??static::DEFAULT_TEXTDOMAIN);
        $languageDirectory = realpath($languageDirectory)?:false;
        if (!is_dir($languageDirectory)) {
            return false;
        }
        $this->directories[$textDomain][$languageDirectory] = true;
        return true;
    }

    /**
     * @param string $textDomain
     * @param string $locale
     * @internal
     * @return ?array
     */
    protected function loadTranslationDirectory(string $textDomain, string $locale): ?array
    {
        $currentDir = $this->directories[$textDomain]??null;
        if (!$currentDir) {
            return null;
        }

        $types = [
            Gettext::class => 'mo',
            Json::class => 'json',
            PhpArray::class => 'php',
            Ini::class => 'ini',
        ];

        $theTypes = $this->eventDispatch(
            'Translator:loader:list',
            $types,
            $textDomain,
            $locale
        );

        $theTypes = !is_array($theTypes) ? $types : $theTypes;
        foreach ($theTypes as $loader => $type) {
            if (!is_string($type) || !is_string($loader) || !$loader instanceof FileLoaderInterface) {
                unset($theTypes[$loader]);
            }
        }

        if (empty($theTypes)) {
            $theTypes = $types;
        }

        if (!isset($this->registeredDirectories[$textDomain])) {
            $this->registeredDirectories[$textDomain] = [];
        }

        $currents =& $this->registeredDirectories[$textDomain];
        foreach ($currentDir as $directory => $status) {
            if (!isset($currents[$directory])) {
                $currents[$directory] = [];
            }
            foreach ($theTypes as $loader => $type) {
                $currentFile = "$locale.$type";
                $file        = "$directory/$currentFile";
                if (!is_file($file) || !is_readable($file)
                    || filesize($file) < 100
                ) {
                    continue;
                }
                $currents[$directory][$currentFile] = false;
                $this->addTranslationFile(
                    $loader,
                    $file,
                    $textDomain,
                    $locale
                );
            }
        }
        unset($currents);

        return $this->registeredDirectories[$textDomain];
    }

    /**
     * Override load message
     *
     * @param string $textDomain
     * @param string $locale
     *
     * @return bool
     */
    protected function loadMessagesFromFiles($textDomain, $locale): bool
    {
        $messagesLoaded = false;

        $onlyOne = $this->isLoadFoundOne();
        foreach ([$locale, '*'] as $currentLocale) {
            if (! isset($this->files[$textDomain][$currentLocale])) {
                continue;
            }
            foreach ($this->files[$textDomain][$currentLocale] as $file) {
                $loader = $this->getPluginManager()->get($file['type']);
                $baseName = basename($file['filename']);
                $dirName = dirname($file['filename']);
                $hasDir = isset(
                    $this->registeredDirectories[$textDomain],
                    $this->registeredDirectories[$textDomain][$dirName],
                ) && array_key_exists($baseName, $this->registeredDirectories[$textDomain][$dirName]);
                if ($hasDir) {
                    $this->registeredDirectories[$textDomain][$dirName][$baseName] = false;
                }
                if (! $loader instanceof FileLoaderInterface) {
                    continue;
                }
                try {
                    if (isset($this->messages[$textDomain][$locale])) {
                        $this->messages[$textDomain][$locale]->merge($loader->load($locale, $file['filename']));
                    } else {
                        $this->messages[$textDomain][$locale] = $loader->load($locale, $file['filename']);
                    }
                    $messagesLoaded = true;
                    if ($hasDir) {
                        $this->registeredDirectories[$textDomain][$dirName][$baseName] = true;
                    }
                    if ($this->eventDispatch(
                        'Translator:loader:loadOnce',
                        $onlyOne,
                        $this->messages[$textDomain][$locale],
                        $loader,
                        $textDomain,
                        $locale,
                        $file
                    ) === true && !empty($this->messages[$textDomain][$locale])) {
                        break;
                    }
                } catch (InvalidArgumentException|ExceptionInterface) {
                    // pass
                }
            }
            unset($this->files[$textDomain][$currentLocale]);
        }

        return $messagesLoaded;
    }

    /**
     * Adding simple Events
     *
     * @param string $textDomain
     * @param string $locale
     */
    protected function loadMessages($textDomain, $locale)
    {
        parent::loadMessages($textDomain, $locale);
        $this->eventDispatch('Translator:loadMessages', $this);
    }

    public function translate($message, $textDomain = null, $locale = null) : string
    {
        $textDomain = $this->filterTextDomain($textDomain);
        $locale = $this->filterLocale($locale);
        $this->loadTranslationDirectory($textDomain, $locale);
        $translate = parent::translate($message, $textDomain, $locale);
        return is_array($translate) ? ($translate[0]??$message) : $translate;
    }

    /**
     * @param TextDomain $translations
     * @param string $textDomain
     * @param string $locale
     *
     * @return $this|Translator
     */
    public function addTranslations(
        TextDomain $translations,
        string $textDomain,
        string $locale
    ) : static {
        $textDomain = $this->filterTextDomain($textDomain);
        $locale = $this->filterLocale($locale);

        if (isset($this->messages[$textDomain])) {
            $this->messages[$textDomain] = [];
        }
        if (!isset($this->messages[$textDomain][$locale])) {
            $this->messages[$textDomain][$locale] = $translations;
        } else {
            $this->messages[$textDomain][$locale]->merge($translations);
        }
        return $this;
    }

    public function translatePlural(
        $singular,
        $plural,
        $number,
        $textDomain = null,
        $locale = null
    ): string {
        if (is_string($plural) && is_numeric(trim($plural))) {
            $plural = trim($plural);
        }
        $number = is_numeric($number)
            ? ($number < 1 && $number > 0 ? 1 : (int) $number)
            : 1;

        $singular = (string) $singular;
        $plural = (string) $plural;
        $textDomain = $this->filterTextDomain($textDomain);
        $locale = $this->filterLocale($locale);
        // load first
        $this->loadTranslationDirectory($textDomain, $locale);

        return parent::translatePlural(
            $singular,
            $plural,
            $number,
            $textDomain,
            $locale
        );
    }
}
