<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations\Annotation;

use BadMethodCallException;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\RouteAnnotations\Abstracts\AnnotationController;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Route implements JsonSerializable
{
    private string $path = '';
    private array $localizedPaths = [];
    private ?string $name = '';
    private array $requirements = [];
    private array $options = [];
    private array $defaults = [];
    private ?string $host = null;
    /**
     * @var string[]
     */
    private array $methods = [];
    private array $schemes = [];
    private ?string $condition = null;
    /**
     * @var array<string, ?string>
     */
    private array $arguments = [];
    private ?string $controller = null;
    private ?string $controllerMethod = null;
    private ?string $prefix = null;
    private int $priority = 10;
    private string $returnType = 'text/html';

    /**
     * @param array $data An array of key/value parameters
     *
     * @throws BadMethodCallException
     */
    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['path'] = $data['value'];
            unset($data['value']);
        }

        if (isset($data['path'])) {
            if (is_numeric($data['path'])
                || is_object($data['path']) && method_exists($data['path'], '__toString')
            ) {
                $data['path'] = (string) $data['path'];
            }

            if (!is_string($data['path'])) {
                throw new InvalidArgumentException(
                    sprintf('Argument Path must be as a string %s given', gettype($data['path']))
                );
            }
        }

        if (isset($data['utf8'])) {
            $data['options']['utf8'] = (bool) $data['utf8'];
            unset($data['utf8']);
        }
        if (isset($data['arguments'])) {
            $data['arguments'] = is_array($data['arguments']) ? $data['arguments'] : [$data['arguments']];
        }
        if (isset($data['priority'])) {
            $data['priority'] = is_numeric($data['priority']) ? (int) $data['priority'] : 10;
        }
        if (!is_string($data['condition']??null)) {
            unset($data['condition']);
        }

        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                continue;
            }
            $this->$method($value);
        }
    }

    /**
     * @return string
     */
    public function getReturnType(): string
    {
        return $this->returnType;
    }

    /**
     * @param string $returnType
     */
    public function setReturnType(string $returnType): void
    {
        $this->returnType = $returnType;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function setPath(string $path)
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setLocalizedPaths(array $localizedPaths)
    {
        $this->localizedPaths = $localizedPaths;
    }

    public function getLocalizedPaths(): array
    {
        return $this->localizedPaths;
    }

    public function setHost($pattern)
    {
        $this->host = $pattern;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setName($name)
    {
        $this->name = (string) $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setRequirements(array $requirements)
    {
        $this->requirements = $requirements;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function setSchemes(array|string $schemes)
    {
        $this->schemes = is_array($schemes) ? $schemes : [$schemes];
    }

    public function getSchemes() : array
    {
        return $this->schemes;
    }

    public function setMethod(string $method)
    {
        if (str_contains($method, '|')) {
            $method = explode('|', $method);
        }
        $this->setMethods($method);
    }

    /**
     * @param array|string $methods
     */
    public function setMethods(array|string $methods)
    {
        if (is_string($methods)) {
            $methods = trim($methods);
            if (str_contains($methods, '|')) {
                $methods = explode('|', $methods);
            }
        }

        if (empty($methods)) {
            $methods = ["GET"];
        }
        if (!is_array($methods)) {
            $methods = [$methods];
        }
        $methods = array_filter($methods, 'is_string');
        $methods = array_filter(array_map('trim', $methods));
        $methods = array_map('strtoupper', $methods);
        // if METHODS ANY || ANY has exists
        if (in_array('ANY', $methods) || in_array('ALL', $methods)) {
            $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        }

        $this->methods = array_values(array_unique($methods));
    }

    /**
     * @return array
     */
    public function getMethods() : array
    {
        return $this->methods;
    }

    public function setCondition(string $condition)
    {
        $this->condition = $condition;
    }

    public function getCondition() : ?string
    {
        return $this->condition;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function setArgument(string $name, string $value)
    {
        $this->arguments[$name] = $value;
    }

    public function getArgument(string $name, ?string $default = null) : ?string
    {
        return array_key_exists($name, $this->arguments)
            ? $this->arguments[$name]
            : $default;
    }

    /**
     * @return null|class-string<AnnotationController>
     */
    public function getController() : ?string
    {
        return $this->controller;
    }

    /**
     * @param class-string<AnnotationController> $controller
     */
    public function setController(string $controller): void
    {
        $this->controller = $controller;
    }

    /**
     * @return ?string
     */
    public function getControllerMethod() : ?string
    {
        return $this->controllerMethod;
    }

    /**
     * @param ?string $controllerMethod
     */
    public function setControllerMethod(?string $controllerMethod): void
    {
        $this->controllerMethod = $controllerMethod;
    }

    /**
     * @return ?string
     */
    public function getPrefix() : ?string
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return ?string
     */
    public function getRoutePattern(): ?string
    {
        $path = $this->getPath();
        if (!$path) {
            return $path;
        }

        if (!is_array($requirements = $this->getRequirements())
            || empty($requirements)
        ) {
            return $path;
        }

        $matches = [];
        return preg_replace_callback(
            '~[{]([^}:]+)[}]~',
            function ($e) use (&$matches, $requirements) {
                $e[1] = trim($e[1]);
                if (!isset($requirements[$e[1]])
                    || !is_string($requirements[$e[1]])
                    || !preg_match('~^[A-Za-z_][A-Za-z_0-9]*$~', $e[1])
                ) {
                    return $e[0];
                }

                $value  = $requirements[$e[1]];
                $regexP = "#$value#";
                if (!Validator::isValidRegExP($regexP)) {
                    return $value;
                }
                if ($this->regexHasCapturingGroups($value)) {
                    preg_match('~\(([^)]+)\)~', $value, $m);
                    if (empty($m[1])) {
                        return $e[0];
                    }
                    if ($this->regexHasCapturingGroups($m[1])) {
                        return $e[0];
                    }
                    $value = $m[1];
                }
                $count = count($matches[$e[1]]??[]);
                $name = $count > 0 ? "$e[1]_$count" : "$e[1]";
                $matches[$e[1]] = true;
                return sprintf('{%s: (?:%s)}', $name, $value);
            },
            $path
        );
    }

    private function regexHasCapturingGroups($regex): bool
    {
        if (!str_contains($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }
        $skipFail = '(*SKIP)(*FAIL)';
        return (bool) preg_match(
            "~
                (?:
                    \(\?\(
                  | \[ [^]\\\\]* (?: \\\\ . [^]\\\\]* )* ]
                  | \\\\ .
                ) $skipFail |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | ' )
                  | \*
                )
            ~x",
            $regex
        );
    }

    #[ArrayShape([
        'condition' => "null|string",
        'controller' => "null|class-string<AnnotationController>",
        'controller_method' => "null|string",
        'name' => "string",
        'options' => "array",
        'prefix' => "null|string",
        'path' => "string",
        'pattern' => "null|string",
        'arguments' => "array<string>",
        'requirements' => "array<string>",
        'methods' => "array<string>"
    ])] public function toArray() : array
    {
        return [
            'condition' => $this->getCondition(),
            'controller' => $this->getController(),
            'controller_method' => $this->getControllerMethod(),
            'name' => $this->getName(),
            'options' => $this->getOptions(),
            'prefix' => $this->getPrefix(),
            'path' => $this->getPath(),
            'pattern' => $this->getRoutePattern(),
            'arguments' => $this->getArguments(),
            'requirements' => $this->getRequirements(),
            'methods' => $this->getMethods(),
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
