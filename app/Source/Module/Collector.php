<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module;

use Closure;
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

    public function scan(): static
    {
        if ($this->scanned) {
            return $this;
        }


        $this->scanned = true;
        $modules = [];
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
            /**
             * @var AbstractModule $foundClassName
             */
            $modules[$foundClassName::thePriority()][$foundClassName] = $foundClassName;
        }
        ksort($this->modules, SORT_ASC);
        foreach ($modules as $classNames) {
            foreach ($classNames as $className => $class) {
                $className                     = trim(strtolower($className));
                $this->modules[$className] = $class;
            }
        }

        return $this;
    }

    public function scanned(): bool
    {
        return $this->scanned;
    }

    /**
     * @return array<string, class-string<AbstractModule>>
     */
    public function getModulesKey(): array
    {
        return array_keys($this->scan()->modules);
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
        if (!isset($this->modules[$name])) {
            return null;
        }
        if (is_string($this->modules[$name])) {
            $this->modules[$name] = new $this->modules[$name](
                $this->getContainer(),
                $this
            );
        }
        return $this->modules[$name];
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
        return true;
    }

    /**
     * @param T $module
     * @param Storage $Storage
     * @param string $moduleName
     * @param Closure $callback
     *
     * @template T AbstractController
     * @return T
     */
    public function load(
        Storage $Storage,
        AbstractModule $module,
        string $moduleName,
        Closure $callback
    ) : AbstractModule {
        // always scan first
        if (!$this->scanned()) {
            return $module;
        }
        $middlewareClass = get_class($module);
        $name = strtolower($middlewareClass);
        if (!isset($this->loadedModules[$name])) {
            $currents = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $storageModule = next($currents)['class']??null;
            // validate
            if ((
                $storageModule == Storage::class
                 || !$storageModule instanceof Storage
            )) {
                $this->alreadyLoaded            = true;
                $this->loadedModules[$name] = $middlewareClass;
                $callback->call($Storage, $module, $moduleName);
            }
        }

        return $module;
    }
}
