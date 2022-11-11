<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module;

use DirectoryIterator;
use ReflectionClass;
use RuntimeException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Interfaces\Abilities\Scannable;
use TrayDigita\Streak\Source\Module\Abstracts\AbstractModule;
use TrayDigita\Streak\Source\Traits\NamespacesArray;

class Collector extends AbstractContainerization implements Scannable
{
    use NamespacesArray;

    /**
     * @var array<string, AbstractModule>
     */
    protected array $modules = [];

    /**
     * @var array<string, string>
     */
    private array $loadedModules = [];

    /**
     * @var bool
     */
    private bool $scanned = false;

    /**
     * @var bool
     */
    private bool $alreadyLoaded = false;

    public function __construct(
        Container $container,
        string|array $moduleNamespaces,
        protected string $moduleDirectory
    ) {
        parent::__construct($container);
        $moduleNamespaces = (array) $moduleNamespaces;
        foreach ($moduleNamespaces as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $this->addNamespace($item);
        }
    }

    /**
     * @return string
     */
    public function getModuleDirectory(): string
    {
        return $this->moduleDirectory;
    }

    /**
     * @return $this
     */
    public function scan(): static
    {
        if ($this->scanned) {
            return $this;
        }

        $this->scanned = true;
        $directory = realpath($this->moduleDirectory)?:false;
        if (!$directory || !is_dir($directory)) {
            return $this;
        }

        foreach (new DirectoryIterator($this->moduleDirectory) as $info) {
            if (!$info->isDir() || $info->isDot()) {
                continue;
            }
            $name = $info->getBasename();
            if (!preg_match('~[a-zA-Z_][A-Za-z_0-9]*$~', $name)) {
                continue;
            }

            $alreadyInclude = false;
            $foundClassName = false;
            foreach ($this->getNamespaces() as $namespace) {
                $className = sprintf('%1$s\\%2$s\\%2$s', $namespace, $name);
                try {
                    if (!$alreadyInclude && !class_exists($className)) {
                        $alreadyInclude = true;
                        $file = $info->getRealPath() ."/$name.php";
                        if (is_file($file)) {
                            (function ($file) {
                                require_once $file;
                            })->bindTo(null)($file);
                        }
                    }
                    $ref = new ReflectionClass($className);
                    if (!$ref->isSubclassOf(AbstractModule::class)) {
                        continue;
                    }
                    $className = $ref->getName();
                    $foundClassName = $className;
                    break;
                } catch (Throwable) {
                    continue;
                }
            }
            if (!$foundClassName) {
                continue;
            }
            $this->modules[strtolower($foundClassName)] = new $foundClassName(
                $this->container,
                $this
            );
        }
        $this->sortCollections();
        return $this;
    }

    /**
     * Sort
     */
    private function sortCollections()
    {
        uasort($this->modules, fn ($a, $b) => $a->getPriority() > $b->getPriority());
    }

    /**
     * @return bool
     */
    public function scanned(): bool
    {
        return $this->scanned;
    }

    /**
     * @return array<string, class-string<AbstractModule>>
     * @noinspection PhpUnused
     */
    public function getModulesKey(): array
    {
        return array_keys($this->scan()->modules);
    }

    /**
     * @return array<string, AbstractModule>
     */
    public function getModules() : array
    {
        return $this->modules;
    }

    /**
     * @param class-string<T> $name
     * @template T
     * @return ?AbstractModule|T
     * @noinspection PhpDocSignatureInspection
     */
    public function getModule(string $name) : ?AbstractModule
    {
        $name = strtolower(ltrim($name, '\\'));
        return $this->modules[$name]??null;
    }

    /**
     * @param AbstractModule $module
     *
     * @return bool
     * @throws RuntimeException
     */
    public function add(AbstractModule $module) : bool
    {
        if (!$this->scanned()) {
            $this->scan();
        }
        if ($this->alreadyLoaded) {
            $translator = $this->getContainer(Translator::class);
            throw new RuntimeException(
                sprintf(
                    $translator->translate(
                        'Can not add %1$s while the %1$s already initiated to processing.',
                    ),
                    $translator->translate('module')
                )
            );
        }

        $className = strtolower(get_class($module));
        if (isset($this->modules[$className])) {
            return false;
        }

        $this->modules[$className] = $module;
        $this->sortCollections();
        return true;
    }
}
