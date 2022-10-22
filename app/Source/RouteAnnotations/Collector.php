<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations;

use DirectoryIterator;
use Doctrine\Common\Annotations\AnnotationReader;
use InvalidArgumentException;
use SplFileInfo;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

final class Collector extends AbstractContainerization
{
    use TranslationMethods;

    /**
     * @var RoutesAnnotations[]
     */
    protected array $collections = [];

    /**
     * @return RoutesAnnotations[]
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * @param string $path
     * @param string|null $suffix
     *
     * @return Collector
     */
    public function readController(string $path, ?string $suffix = null) : self
    {
        $obj = new self($this->getContainer());
        if (!$path) {
            throw new InvalidArgumentException(
                $this->translate('Controller path is not defined.')
            );
        }

        $spl = new SplFileInfo($path);
        if (!is_dir($newPath = $spl->getRealPath())) {
            throw new InvalidArgumentException(
                sprintf(
                    $this->translate('Controller path %s is not exists.'),
                    $path
                )
            );
        }

        foreach (new DirectoryIterator($newPath) as $directoryIterator) {
            if ($directoryIterator->isDot()) {
                continue;
            }

            $obj->nestedPath($directoryIterator, $newPath, $suffix);
        }

        return $obj;
    }

    public function merge(Collector $routes) : self
    {
        foreach ($routes->getCollections() as $keyName => $collection) {
            $this->collections[$keyName] = $collection;
        }

        return $this;
    }

    /**
     * @return ?RoutesAnnotations
     * @noinspection PhpUnused
     */
    public function getLastCollection() : ?RoutesAnnotations
    {
        return $this->getCollection();
    }

    /**
     * @param ?string $path
     * @return ?RoutesAnnotations
     */
    public function getCollection(?string $path = null) : ?RoutesAnnotations
    {
        if ($path === null) {
            end($this->collections);
            $path = key($this->collections);
        }

        if (!$path) {
            return null;
        }

        $newPath = (new SplFileInfo($path))->getRealPath()?:$path;
        return $this->collections[$newPath]??null;
    }

    /**
     * @param ControllerReader $controllerReader
     * @return RoutesAnnotations|null
     */
    public function registerController(ControllerReader $controllerReader) : ?RoutesAnnotations
    {
        if ($controllerReader->start()->getError()) {
            return null;
        }

        return $this->registerRoutesAnnotations(
            $this->createRouteAnnotation(
                $controllerReader->getClassName()
            )
        );
    }

    /**
     * @param string $className
     *
     * @return RoutesAnnotations
     */
    public function createRouteAnnotation(string $className) : RoutesAnnotations
    {
        return new RoutesAnnotations(
            $this->getContainer(),
            $this->getAnnotationReader(),
            $className
        );
    }

    /**
     * @return AnnotationReader
     */
    public function getAnnotationReader(): AnnotationReader
    {
        return $this->getContainer(AnnotationReader::class);
    }

    /**
     * @param RoutesAnnotations $routesAnnotations
     *
     * @return RoutesAnnotations
     */
    public function registerRoutesAnnotations(RoutesAnnotations $routesAnnotations) : RoutesAnnotations
    {
        $path = $routesAnnotations->start()->getFilePath();
        $this->collections[$path] = $routesAnnotations;
        return $routesAnnotations;
    }

    /**
     * @param class-string $class
     * @return RoutesAnnotations
     * @throws Throwable
     */
    public function register(string $class) : RoutesAnnotations
    {
        return $this->registerRoutesAnnotations(
            $this->createRouteAnnotation($class)
        );
    }

    /**
     * @param string $file
     * @param string|null $suffix
     * @return RoutesAnnotations|null
     */
    public function registerFile(string $file, ?string $suffix) : ?RoutesAnnotations
    {
        return $this->registerController(
            new ControllerReader(
                $this->getContainer(),
                $file,
                $suffix
            )
        );
    }

    /**
     * @param DirectoryIterator $directoryIterator
     * @param string $path
     * @param string|null $suffix
     */
    private function nestedPath(
        DirectoryIterator $directoryIterator,
        string $path,
        ?string $suffix = null
    ) : void {
        if ($directoryIterator->isDot()) {
            return;
        }

        if ($directoryIterator->isDir()) {
            foreach (new DirectoryIterator($directoryIterator->getRealPath()) as $di) {
                if ($di->isDot()) {
                    continue;
                }
                $this->nestedPath($di, $path);
            }
            return;
        }

        $this->registerFile($directoryIterator->getRealPath(), $suffix);
    }
}
