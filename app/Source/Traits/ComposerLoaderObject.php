<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Composer\Autoload\ClassLoader;
use TrayDigita\Streak\Source\Helper\Util\Validator;

trait ComposerLoaderObject
{
    /**
     * @var class-string|null|bool
     */
    private static null|string|bool $composerLoaderClass = null;

    private static array $registeredPSR = [];

    /**
     * @return ?ClassLoader
     */
    public function getComposerClassLoader() : ?ClassLoader
    {
        if (self::$composerLoaderClass === null) {
            $grep = preg_grep('~^ComposerAutoloaderInit[a-f0-9]+$~', get_declared_classes());
            $grep = reset($grep);
            self::$composerLoaderClass = false;
            if ($grep && method_exists($grep, 'getLoader')) {
                self::$composerLoaderClass = $grep;
            }
        }

        if (self::$composerLoaderClass) {
            return self::$composerLoaderClass::getLoader();
        }
        return null;
    }

    public function registerNamespace(string $directory, string $namespace) : bool
    {
        $namespace = trim(trim($namespace), '\\');
        if (!Validator::isValidNamespace($namespace)) {
            return false;
        }

        $loader = $this->getComposerClassLoader();
        $directory = realpath($directory)??null;
        if (!$directory || !is_dir($directory)) {
            return false;
        }
        if (isset(self::$registeredPSR[$namespace][$directory])) {
            return true;
        }
        self::$registeredPSR[$namespace][$directory] = true;
        $namespace = "$namespace\\";
        $directory .= '/';
        if (!$loader) {
            spl_autoload_register(function ($className) use ($namespace, $directory) {
                if (!str_starts_with($className, $namespace)) {
                    return;
                }
                $file = substr($className, strlen($namespace));
                $file = $directory . str_replace('\\', '/', $file).".php";
                if (is_file($file)) {
                    require_once $file;
                }
            });
            return true;
        }
        $loader->addPsr4($namespace, $directory);
        $loader->add($namespace, $directory);
        return true;
    }
}
