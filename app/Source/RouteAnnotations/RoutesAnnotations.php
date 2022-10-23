<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations;

use Doctrine\Common\Annotations\AnnotationReader;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\Abilities\Clearable;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\RouteAnnotations\Abstracts\AnnotationController;
use TrayDigita\Streak\Source\RouteAnnotations\Annotation\Route;
use TrayDigita\Streak\Source\RouteAnnotations\Interfaces\AnnotationRequirementsInterface;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

final class RoutesAnnotations extends AbstractContainerization implements Startable, Clearable
{
    use TranslationMethods;

    /**
     * @var string
     */
    protected string $groupPath = '';

    /**
     * @var ?string
     */
    protected ?string $fileName = null;

    /**
     * @var Route[]
     */
    protected array $routes = [];

    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * RoutesAnnotations constructor.
     * @param Container $container
     * @param AnnotationReader $annotationReader
     * @param class-string<AnnotationController> $className
     */
    #[Pure] public function __construct(
        Container $container,
        protected AnnotationReader $annotationReader,
        protected string $className
    ) {
        parent::__construct($container);
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getGroupPath(): string
    {
        return $this->groupPath;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return AnnotationReader
     */
    public function getAnnotationReader(): AnnotationReader
    {
        return $this->annotationReader;
    }

    /**
     * @param class-string<AnnotationController> $className
     * @throws RuntimeException
     */
    private function process(string $className): self
    {
        try {
            $ref = new ReflectionClass($className);
            if (!$ref->isSubclassOf(AnnotationController::class)) {
                throw new RuntimeException(
                    sprintf(
                        $this->translate('Class %s is not instance of %s.'),
                        $className,
                        AnnotationController::class
                    )
                );
            }
        } catch (Throwable) {
            throw new RuntimeException(
                sprintf(
                    $this->translate(
                        'Class name %s is not valid.'
                    ),
                    $className
                )
            );
        }

        $this->fileName = $ref->getFileName();
        $this->className = $ref->getName();
        /**
         * @var ?Route|AnnotationRequirementsInterface $annotation
         */
        $annotation = $this->annotationReader->getClassAnnotations($ref)[0]??null;
        $groupPath = $annotation ? $annotation->getRoutePattern() : '';
        if (!is_string($groupPath??'')) {
            return $this;
        }

        // checking condition
        $expression = $this->getContainer(ExpressionLanguage::class);
        $condition = $annotation?->getCondition();
        if ($condition) {
            try {
                $matches = $expression->evaluate(
                    $condition,
                    [
                        'annotation' => $annotation,
                        'route' => null,
                        'controller' => null,
                        'request' => $this->getContainer(ServerRequestInterface::class),
                        'container' => $this->getContainer(),
                    ]
                );
                if (!$matches) {
                    return $this;
                }
            } catch (Throwable) {
                return $this;
            }
        }

        $this->groupPath = $groupPath;
        if (is_array($requirements = $annotation->getRequirements())) {
            $placeholder = '_'.mt_rand().'_';
            $this->groupPath = preg_replace('~([{][^}]+[}])~', sprintf('%s$1', $placeholder), $this->groupPath);
            foreach ($requirements as $key => $v) {
                $this->groupPath = str_replace(sprintf('%s{%s}', $placeholder, $key), $v, $this->groupPath);
            }
            $this->groupPath = str_replace($placeholder, '', $this->groupPath);
        }

        foreach ($ref->getMethods() as $method) {
            $name = $method->getName();
            $route = $this
                    ->annotationReader
                    ->getMethodAnnotations($method)[0]??null;
            if ($route instanceof Route) {
                $route->setController($this->getClassName());
                $route->setPrefix($this->getGroupPath());
                $route->setControllerMethod($name);
                $this->routes[$name] = $route;
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->start()->fileName;
    }

    /**
     * @return ?string
     */
    public function getFilePath(): ?string
    {
        return $this->fileName ? dirname($this->fileName) : null;
    }

    /**
     * @return $this
     */
    public function start() : self
    {
        if ($this->started) {
            return $this;
        }

        $this->started = true;
        $this->process($this->className);

        return $this;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function clear()
    {
        $this->started = false;
        $this->fileName = null;
        $this->routes = [];
    }

    public function __destruct()
    {
        $this->clear();
    }
}
