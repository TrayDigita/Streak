<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use ArrayAccess;
use Closure;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\I18n\Translator\Translator as LaminasTranslator;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionUnionType;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Routing\RouteCollectorProxy;
use Throwable;
use TrayDigita\Streak\Source\Exceptions\ContainerFrozenException;
use TrayDigita\Streak\Source\Exceptions\ContainerNotFoundException;
use TrayDigita\Streak\Source\i18n\Translation;
use TrayDigita\Streak\Source\i18n\Translator;
use function method_exists;

/**
 * @property-read Translation $translation
 * @property-read Translator $translator
 * @property-read App $app
 * @property-read App $slim
 * @property-read Events $events
 * @property-read Container $container
 * @property-read SharedEventManager $sharedEventManager
 * @property-read EventManager $eventManager
 * @property-read Benchmark $timeRecord
 * -> static
 * @method static Benchmark TimeRecord()
 * @method static Translator Translator()
 * @method static App App()
 * @method static Events Events()
 * @method static Container Container()
 * @method static SharedEventManager SharedEventManager()
 * @method static EventManager EventManager()
 * @method static Translation Translation()
 */
class Container implements ContainerInterface, ArrayAccess
{
    private array $values = [];
    private array $frozen = [];
    private array $raw = [];
    private array $keys = [];

    /**
     * @var array|bool[]
     */
    protected array $protectedContainers = [];
    private array $protectedAliases = [];

    /* --------------------------------------------------
     * Aliases
     */
    private array $aliases = [];
    private array $aliasesName = [];
    private array $lowerCaseAliases = [];

    /* --------------------------------------------------
     * Statuses
     */
    private ?bool $lastAliasStatus = null;
    private ?bool $lastAliasProtectedStatus = null;
    private ?string $last = null;

    /* --------------------------------------------------
     * Parameters
     */
    private array $parameters = [];

    /**
     * @var Translator
     */
    public readonly Translator $translator;

    /**
     * @var ?Container
     */
    private static ?Container $instance = null;

    final public function __construct()
    {
        self::$instance = $this;
        $this->translator = new Translator($this);

        $this->buildFactory();
        $this->afterConstruct();
    }

    /**
     * @return Container
     */
    public static function getInstance(): Container
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        $instance = static::getInstance();
        if (method_exists($instance, $name)) {
            if ((new ReflectionMethod($instance, $name))->isPublic()) {
                return call_user_func_array([$instance, $name], $arguments);
            }
        }

        return $instance->get($name);
    }

    /**
     * @override
     */
    protected function afterConstruct()
    {
        // @enc
    }

    private function buildFactory()
    {
        $this
            ->setProtect(__CLASS__, fn ($c) => $c)
            ->setGlobalAliases(__CLASS__)
            ->setGlobalAliases(ContainerInterface::class)
            ->setGlobalAliases('Container')
            ->setGlobalAliases('ContainerInterface')
            ->setProtect(Events::class)
            ->setGlobalAliases('Events')
            ->setProtect(Benchmark::class)
            ->setGlobalAliases('TimeRecord')
            ->setProtect(SystemInitialHandler::class)
            ->setGlobalAliases('SystemErrorHandler')

            /* ---------------------------------------------------------
             * Translator
             */
            ->setProtect(Translator::class, fn() => $this->translator)
            ->setGlobalAliases(TranslatorInterface::class)
            ->setGlobalAliases(LaminasTranslator::class)
            ->setGlobalAliases('Translator')
            ->setGlobalAliases('TranslatorInterface')

            /* ---------------------------------------------------------
             * Translation
             */
            ->setProtect(Translation::class)
            ->setGlobalAliases('Translation')

            /* ---------------------------------------------------------
             * Shared Event Manager
             */
            ->setProtect(SharedEventManager::class)
            ->setProtectAlias(SharedEventManagerInterface::class)
            ->setGlobalAliases('SharedEventManager')
            ->setGlobalAliases('SharedEventManagerInterface')
            /* ---------------------------------------------------------
             * Event Manager
             */
            ->setProtect(EventManager::class, fn ($c) => $c->get(SharedEventManagerInterface::class))
            ->setGlobalAliases(EventManagerInterface::class)
            ->setGlobalAliases('EventManager')
            ->setGlobalAliases('EventManagerInterface')

            /* ---------------------------------------------------------
             * Slim\App
             */
            ->setProtect(App::class, fn() => AppFactory::createFromContainer($this))
            ->setGlobalAliases(RouteCollectorProxy::class)
            ->setGlobalAliases(RouteCollectorProxyInterface::class)
            ->setGlobalAliases('App')
            ->setGlobalAliases('Slim')
            ->setGlobalAliases('RouteCollectorProxy')
            ->setGlobalAliases('RouteCollectorProxyInterface');
        // register handler
        $this->get(SystemInitialHandler::class)->register();
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return $this
     */
    public function setParameter(string $name, $value) : static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * @param array $values
     *
     * @return $this
     */
    public function setParameters(array $values) : static
    {
        foreach ($values as $key => $value) {
            if (is_numeric($key)) {
                throw new InvalidArgumentException(
                    $this->translation->translate('Parameters value contains invalid key name.'),
                    E_USER_ERROR
                );
            }
            $key = (string) $key;
            $this->setParameter($key, $value);
        }

        return $this;
    }

    public function hasParameter(string $name) : bool
    {
        return array_key_exists($name, $this->parameters);
    }

    public function getParameter(string $name) : mixed
    {
        if (!$this->hasParameter($name)) {
            throw new InvalidArgumentException(
                sprintf(
                    $this->translation->translate(
                        'Parameter %s is not exists.'
                    ),
                    $name
                )
            );
        }
        return $this->parameters[$name];
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function removeParameters(string $name) : static
    {
        unset($this->parameters[$name]);
        return $this;
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return $this
     */
    public function addParameters(string $name, $value) : static
    {
        if ($this->hasParameter($name)) {
            return $this;
        }
        return $this->setParameter($name, $value);
    }

    /**
     * Once aliases set it can not be change until dependency removed
     *
     * @param string $alias
     * @param ?string $id
     *
     * @return $this
     */
    public function setAlias(
        string $alias,
        ?string $id = null
    ): static {
        $id = $id??$this->last;
        $this->lastAliasStatus = true;
        if ($id && isset($this->keys[$id]) && !isset($this->keys[$alias])) {
            if (!isset($this->aliases[$alias]) || ! $this->isAliasProtected($alias)) {
                $this->aliases[$alias]    = $id;
                $this->aliasesName[$id][] = $alias;
                return $this;
            }
        }

        $this->lastAliasStatus = false;
        return $this;
    }

    /**
     * @param string $alias
     * @param ?string $id
     *
     * @return $this
     */
    public function setGlobalAliases(string $alias, ?string $id = null) : static
    {
        $id = $id??$this->last;
        if (!$id) {
            return $this;
        }

        if (!isset($this->aliases[$id])) {
            $this->setProtectAlias($alias, $id);
        }
        $aliasValue = $this->getAlias($alias);
        if ($aliasValue) {
            $this->protect($alias);
            $this->lowerCaseAliases[strtolower($alias)] = $aliasValue;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * @param string $name
     *
     * @return ?string
     */
    public function getAlias(string $name): ?string
    {
        return $this->aliases[$name]??null;
    }

    /**
     * @param string $alias
     * @param ?string $id
     *
     * @return $this
     */
    public function setProtectAlias(
        string $alias,
        ?string $id = null
    ): static {
        return $this->setAlias($alias, $id)->protectAlias($alias);
    }

    public function removeAliases(string $alias) : bool
    {
        $this->lastAliasStatus = true;
        if ($this->isAliasProtected($alias)) {
            $this->lastAliasStatus = false;
            return false;
        }

        unset($this->aliases[$alias], $this->lowerCaseAliases[strtolower($alias)]);
        foreach ($this->aliases as $id => $list) {
            foreach ($list as $key => $aliasName) {
                unset($this->aliasesName[$id][$key]);
            }
            $this->aliasesName[$id] = array_values($this->aliasesName[$id]);
        }

        return true;
    }

    /**
     * @return ?bool
     */
    public function getLastAliasStatus(): ?bool
    {
        return $this->lastAliasStatus;
    }

    public function isAliasProtected(string $name) : bool
    {
        if (isset($this->protectedAliases[$name]) || isset($this->lowerCaseAliases[strtolower($name)])) {
            return true;
        }
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($this->aliasesName as $id => $aliases) {
            if (in_array($name, $aliases, true) && isset($this->protectedAliases[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function protectAlias(string $name) : static
    {
        $this->lastAliasProtectedStatus = false;
        if (isset($this->aliases[$name])) {
            $this->protectedAliases[$name] = true;
        }

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getLastAliasProtectedStatus(): ?bool
    {
        return $this->lastAliasProtectedStatus;
    }

    /**
     * @return string[]
     */
    public function getProtectedContainers(): array
    {
        return array_keys($this->protectedContainers);
    }

    public function protect(?string $name = null) : static
    {
        $name = $name??$this->last;
        if ($name !== null && isset($this->keys[$name])) {
            $this->protectedContainers[$name] = true;
        }

        return $this;
    }

    /**
     * @param string|class-string $id
     * @param callable|object|null $value
     * @param bool $protect
     *
     * @return $this
     */
    public function set(
        string $id,
        callable|object|null $value = null,
        bool $protect = false
    ) : static {
        if (isset($this->frozen[$id])) {
            throw new ContainerFrozenException(
                sprintf(
                    $this->translation->translate('Cannot override frozen service "%s".'),
                    $id
                )
            );
        }

        if (isset($this->keys[$id]) && $this->isAliasProtected($id)) {
            return $this;
        }

        if ($value === null) {
            $value = $this->createInstanceObject($id);
        } elseif (!is_callable($value)) {
            $value = fn() => $value;
        }

        $this->last = $id;
        $this->values[$id] = $value;
        $this->keys[$id] = true;
        $protect && $this->protect($id);
        return $this;
    }

    /**
     * @param string $name
     * @param callable|object|null $value
     *
     * @return $this
     */
    public function setProtect(string $name, callable|object|null $value = null) : static
    {
        return $this->set($name, $value, true);
    }

    /**
     * @param class-string<T>|string $id
     *
     * @template T
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        $lowerId = strtolower($id);
        if (!isset($this->keys[$id]) || isset($this->lowerCaseAliases[$lowerId])) {
            $id = $this->aliases[$id]??($this->lowerCaseAliases[$lowerId]??$id);
        }

        if (!isset($this->keys[$id])) {
            throw new ContainerNotFoundException(
                sprintf(
                    $this->translation->translate('Identifier "%s" is not defined.'),
                    $id
                )
            );
        }

        if (isset($this->raw[$id])
            || !is_object($this->values[$id])
            || !method_exists($this->values[$id], '__invoke')
        ) {
            return $this->values[$id];
        }

        $raw = $this->values[$id];
        $val = $this->values[$id] = $raw($this);
        $this->raw[$id] = $raw;
        $this->frozen[$id] = true;

        return $val;
    }

    public function remove(string $id) : static
    {
        if (!isset($this->keys[$id])) {
            $id = $this->aliases[$id]??$id;
        }
        if (isset($this->protectedContainers[$id])) {
            return $this;
        }

        if (isset($this->keys[$id])) {
            foreach (($this->aliasesName[$id]??[]) as $alias) {
                unset($this->aliases[$alias]);
            }

            unset(
                $this->values[$id],
                $this->frozen[$id],
                $this->raw[$id],
                $this->keys[$id],
                $this->aliasesName[$id]
            );
        }
        return $this;
    }

    public function has(string $id) : bool
    {
        if (!isset($this->keys[$id])) {
            $id = $this->aliases[$id]??($this->lowerCaseAliases[strtolower($id)]??$id);
        }
        return isset($this->keys[$id]);
    }

    public function createFromObject(object $object) : static
    {
        if ($object instanceof Closure) {
            throw new InvalidArgumentException(
                $this->translation->translate('Argument object closure is not allowed.'),
                E_USER_ERROR
            );
        }

        $this->set(get_class($object), fn() => $object);
        return $this;
    }

    public function createFromObjectClassName(string $className) : static
    {
        return $this->set($className);
    }

    /**
     * @param class-string<T> $className
     * @template T
     * @return closure<T>
     */
    public function createInstanceObject(string $className) : object
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                sprintf(
                    $this->translation->translate('Class name %s is not exists.'),
                    $className
                ),
                E_USER_ERROR
            );
        }

        return fn () => $this->createObjectClassNameObject($className);
    }

    /**
     * @param string|class-string<T> $className
     * @param int $called
     * @template T
     * @return mixed|T
     * @throws ReflectionException
     */
    private function createObjectClassNameObject(string $className, int $called = 0): object
    {
        // $isConsole = $className === Console::class;
        $ref = new ReflectionClass($className);
        if (!$ref->isInstantiable()) {
            throw new InvalidArgumentException(
                sprintf(
                    $this->translation->translate('Class name %s is not instantiable.'),
                    $className
                ),
                E_USER_ERROR
            );
        }

        $isSubclass = $ref->isSubclassOf(ContainerInterface::class);
        if ($isSubclass) {
            return $this;
        }
        $arguments = [];
        $constructor = $ref->getConstructor();
        if (!$constructor) {
            return new $className;
        }
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $paramValue = $this->hasParameter($name) ? $this->getParameter($name) : null;
            // call closure
            if ($paramValue instanceof Closure) {
                try {
                    $refClosure = new ReflectionFunction($paramValue);
                    $numParam = $refClosure->getNumberOfParameters();
                    if ($numParam === 0) {
                        $paramValue = $paramValue($this);
                    } elseif ($numParam === 1) {
                        $paramRef = $refClosure->getParameters()[0];
                        $refType = $paramRef->getType();
                        $parameterValues = null;
                        if ($refType && ($refTypeName = $refType->getName())) {
                            if ($refTypeName === ContainerInterface::class
                                || is_subclass_of($refTypeName, ContainerInterface::class)
                            ) {
                                $paramValue = $paramValue($this);
                            } else {
                                $parameterValues = $this->hasParameter($refTypeName)
                                    ? $this->getParameter($refTypeName)
                                    : ($this->has($refTypeName)
                                        ? $this->get($refTypeName)
                                        : (
                                        $paramRef->isDefaultValueAvailable()
                                            ? $paramRef->getDefaultValue()
                                            : null
                                        )
                                );
                                if ($parameterValues !== null
                                    || $paramRef->allowsNull()
                                ) {
                                    $paramValue = $paramValue($parameterValues);
                                }
                            }
                        } elseif ($paramRef->isDefaultValueAvailable()) {
                            $parameterValues = $paramRef->getDefaultValue();
                            $paramValue = $paramValue($parameterValues);
                        }
                    }
                } catch (ReflectionException) {
                    // pass
                }
            }

            $typeParam  = gettype($paramValue);
            $arguments[$name] = null;

            if ($parameter->hasType()) {
                $types     = $parameter->getType();
                if ($types instanceof ReflectionUnionType) {
                    $typeNames = array_map(fn ($e) => $e->getName(), $types->getTypes());
                    $types = $types->getTypes();
                } else {
                    $typeNames = [$types->getName()];
                    $types = [$types];
                }
                $skip = false;
                foreach ($types as $type) {
                    if ($type->isBuiltin()) {
                        if (!$parameter->isDefaultValueAvailable()) {
                            if (!in_array($typeParam, $typeNames)) {
                                throw new InvalidArgumentException(
                                    sprintf(
                                        $this->translation->translate(
                                            'Class name %s contain required parameters (%s).'
                                        ),
                                        $className,
                                        sprintf('$%s', $name)
                                    ),
                                    E_USER_ERROR
                                );
                            }
                            $arguments[$name] = $paramValue;
                            continue;
                        }

                        $arguments[$name] = $paramValue;
                        if ((
                                $typeParam !== 'boolean' || !in_array('bool', $typeNames)
                            )
                            && !in_array($typeParam, $typeNames)
                        ) {
                            $arguments[$name] = $parameter->getDefaultValue();
                        }
                        $skip = true;
                        break;
                    }
                }

                if ($skip) {
                    continue;
                }
                $isObjectParam = is_object($paramValue);
                $tempClass = $isObjectParam ? get_class($paramValue) : gettype($paramValue);
                $containType = false;
                foreach ($typeNames as $typeName) {
                    if ($tempClass === $typeName
                        || ($tempClass === 'boolean' && $typeName === 'bool')
                        || $isObjectParam && is_subclass_of($paramValue, $typeName)
                    ) {
                        $containType = true;
                        break;
                    }
                }
                if ($containType) {
                    $arguments[$name] = $paramValue;
                    continue;
                }

                $hasTempVal = false;
                $tempVal = null;
                $skip = false;
                foreach ($typeNames as $keyName => $typeName) {
                    if ($types[$keyName]->isBuiltin()) {
                        continue;
                    }
                    if ($this->has($typeName)) {
                        $tempVal    = $this->get($typeName);
                        $hasTempVal = true;
                        break;
                    } else {
                        if ($called === 0) {
                            try {
                                $ref = new ReflectionClass($typeName);
                                if ($ref->isInstantiable()) {
                                    $tempVal = $this->createObjectClassNameObject(
                                        $typeName,
                                        $called + 1
                                    );
                                } elseif ($parameter->isDefaultValueAvailable()) {
                                    $tempVal = $parameter->getDefaultValue();
                                } elseif ($parameter->allowsNull()) {
                                    $tempVal = null;
                                }
                                $hasTempVal = true;
                                break;
                            } catch (Throwable) {
                            }
                        } elseif ($parameter->isDefaultValueAvailable()) {
                            $arguments[$name] = $parameter->getDefaultValue();
                            $skip = true;
                            break;
                        }
                    }
                }
                if ($skip) {
                    continue;
                }

                if ($hasTempVal && $tempVal) {
                    $containType = false;
                    $tempClass = get_class($tempVal);
                    foreach ($typeNames as $typeName) {
                        if ($tempClass === $typeName || is_subclass_of($tempVal, $typeName)) {
                            $containType = true;
                            break;
                        }
                    }
                    if ($containType) {
                        $arguments[$name] = $tempVal;
                        continue;
                    }
                }
                $skip = false;
                foreach ($types as $type) {
                    if ($type->allowsNull()) {
                        $arguments[$name] = null;
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }
                throw new InvalidArgumentException(
                    sprintf(
                        $this->translation->translate(
                            'Class name %s contain required parameter type: (%s) that could not to be resolved.'
                        ),
                        $className,
                        implode(', ', $typeNames)
                    ),
                    E_USER_ERROR
                );
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();
                continue;
            }
            if ($parameter->allowsNull()) {
                $arguments[$name] = null;
                continue;
            }
            throw new InvalidArgumentException(
                sprintf(
                    $this->translation->translate(
                        'Class name %s contain required parameters.'
                    ),
                    $className
                ),
                E_USER_ERROR
            );
        }

        return new $className(...$arguments);
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys($this->keys);
    }

    /**
     * @return array
     */
    public function getFrozenKeys(): array
    {
        return array_keys($this->frozen);
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset) : mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value) : void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset) : void
    {
        if (!isset($this->protectedContainers[$offset])) {
            $this->remove($offset);
        }
    }

    /**
     * Magic method setter
     *
     * @param string $name
     * @param callable|null|mixed $value
     */
    public function __set(string $name, mixed $value)
    {
        if ($value === null || !is_callable($value)) {
            if (!class_exists($name)) {
                $value = fn() => $value;
            }
        }

        $this->set($name, $value);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($this->has($name)) {
            return $this->get($name);
        }

        throw new RuntimeException(sprintf(
            $this->translator->translate('Call to undefined property %s.'),
            $name
        ));
    }
}
