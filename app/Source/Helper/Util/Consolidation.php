<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use Composer\Autoload\ClassLoader;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class Consolidation
{
    /**
     * @var class-string|null|bool
     */
    private static string|false|null $composerLoaderClass = null;

    /**
     * @var array<string, string>
     */
    #[ArrayShape([
        'root' => 'string',
        'vendor' => 'string',
        'app' => 'string',
    ])] private static ?array $directories = null;

    #[ArrayShape([
        'root' => 'string',
        'vendor' => 'string',
        'app' => 'string',
    ])] private static function readDirectoriesData(): array
    {
        if (self::$directories !== null) {
            return self::$directories;
        }

        $appPath = defined('APP_PATH') ? APP_PATH : 'app';
        $appPath = is_string($appPath) ? trim($appPath, '/\\') : $appPath;
        $loader = self::composerClassLoader();
        $app   = dirname(__DIR__, 3);
        if ($loader) {
            try {
                $loader = new ReflectionClass($loader);
                $vendor = dirname($loader->getFileName(), 2);
                $root   = dirname($vendor);
                if (str_starts_with($app, $vendor)) {
                    $app = "$root/$appPath";
                }

                self::$directories = [
                    'root'   => $root,
                    'vendor' => $vendor,
                    'app'    => $app,
                ];
                return self::$directories;
            } catch (Throwable) {
            }
        }

        $root = dirname(__DIR__, 4);
        self::$directories = [
            'root'   => $root,
            'vendor' => "$root/vendor",
            'app'    => $app,
        ];
        $composer_json = self::$directories['root'] .'/composer.json';
        if (is_file($composer_json) && is_readable($composer_json)) {
            $composer = (string) file_get_contents($composer_json);
            $composer = (array) json_decode($composer, true);
            $vendor = $composer['config']??[];
            $vendor = $vendor["vendor-dir"]??null;
            if ($vendor) {
                $vendor = preg_replace('~^[.]?/~', '', $vendor);
                self::$directories['vendor'] = "$root/$vendor";
            }
        }
        if (file_exists(self::$directories['root'] . '/composer/ClassLoader.php')) {
            self::$directories['vendor'] = self::$directories['root'];
            self::$directories['root']   = dirname(self::$directories['vendor']);
            self::$directories['app']    = self::$directories['root'] . "/$appPath";
        }
        return self::$directories;
    }

    /**
     * @return string
     */
    public static function rootDirectory() : string
    {
        return self::readDirectoriesData()['root'];
    }

    /**
     * @return string
     */
    public static function appDirectory() : string
    {
        return self::readDirectoriesData()['app'];
    }

    /**
     * @return string
     */
    public static function vendorDirectory() : string
    {
        return self::readDirectoriesData()['vendor'];
    }

    public static function composerClassLoader() : ?ClassLoader
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

    /**
     * Register namespace on composer loader
     *
     * @param string $directory
     * @param string $namespace
     *
     * @return bool
     */
    public static function registerComposerNamespace(string $directory, string $namespace) : bool
    {
        $namespace = trim(trim($namespace), '\\');
        if (!Validator::isValidNamespace($namespace)) {
            return false;
        }

        $loader = self::composerClassLoader();
        $directory = realpath($directory)??null;
        if (!$directory || !is_dir($directory)) {
            return false;
        }
        $namespace = "$namespace\\";
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

        if (in_array($directory, $loader->getPrefixes()[$namespace]??[])) {
            return true;
        }
        $loader->addPsr4($namespace, $directory);
        $loader->add($namespace, $directory);
        return true;
    }

    public static function callbackReduceError(
        callable $callback,
        &$errNo = null,
        &$errStr = null,
        &$errFile = null,
        &$errline = null,
        &$errcontext = null
    ) {
        set_error_handler(function (
            $no,
            $str,
            $file,
            $line,
            $c = null
        ) use (
            &$errNo,
            &$errStr,
            &$errFile,
            &$errline,
            &$errcontext
        ) {
            $errNo = $no;
            $errStr = $str;
            $errFile = $file;
            $errline = $line;
            $errcontext = $c;
        });
        $result = $callback();
        restore_error_handler();
        return $result;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     *
     * @return SymfonyStyle
     */
    public static function createSymfonyConsoleOutput(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ) : SymfonyStyle {
        $input = $input??new ArgvInput();
        $output = $output??StreamCreator::createStreamOutput(null, true);
        return new SymfonyStyle(
            $input,
            $output
        );
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public static function getNameSpace(string $className) : string
    {
        $className = ltrim($className, '\\');
        return preg_replace('~^(.+)?[\\\][^\\\]+$~', '$1', $className);
    }

    /**
     * @param string $fullClassName
     *
     * @return string
     */
    public static function getBaseClassName(string $fullClassName) : string
    {
        $fullClassName = ltrim($fullClassName, '\\');
        return preg_replace('~^(?:.+[\\\])?([^\\\]+)$~', '$1', $fullClassName);
    }
}
