<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\StoragePath;

final class StreamCreator
{
    const DEFAULT_MAX_MEMORY = 2097152;

    /**
     * @var ?string|false|null
     */
    private static string|false|null $streamResourceDirectory = null;

    /**
     * Create socket resource
     *
     * @param string $target
     * @param string $mode
     *
     * @return false|resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createResource(string $target, string $mode)
    {
        return fopen($target, $mode);
    }

    /**
     * Create resource with allocating memory
     *
     * @return resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createMemoryResource()
    {
        return self::createResource('php://memory', 'wb+');
    }

    /**
     * @return ?string
     */
    public static function getStreamResourceDirectory() : ?string
    {
        return self::$streamResourceDirectory?:null;
    }

    /**
     * Create resource with storage file
     *
     * @return resource|false
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createStorageFileResource(
        Container $container
    ) {
        if (self::$streamResourceDirectory === null) {
            if (!$container->has(StoragePath::class)
                ||!$container->get(Application::class)
            ) {
                return self::createTemporaryFileResource();
            }

            $streamPath    = $container->get(StoragePath::class)->getCacheStreamDirectory();
            // using application uuid as identifier
            $uuid          = $container->get(Application::class)->uuid;
            $fileDirectory = "$streamPath/$uuid";
            self::$streamResourceDirectory = is_dir($fileDirectory) ? $fileDirectory : false;
            if (!self::$streamResourceDirectory) {
                self::$streamResourceDirectory = @mkdir($fileDirectory, 0755, true)
                    ? $fileDirectory
                    : false;
            } else {
                self::$streamResourceDirectory = is_writable(self::$streamResourceDirectory)
                    ? self::$streamResourceDirectory
                    : false;
            }
            $name = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)[3];
            if (self::$streamResourceDirectory) {
                $parentDir = dirname(self::$streamResourceDirectory, 2);
                // add empty index file
                !file_exists("$parentDir/index.php")
                    && @file_put_contents("$parentDir/index.php", "<?php\n");
                // add .htaccess file
                !file_exists("$parentDir/.htaccess")
                    && @file_put_contents("$parentDir/.htaccess", "deny from all\n");
            }
        }

        if (!self::$streamResourceDirectory) {
            return self::createTemporaryFileResource();
        }

        $streamResourceDirectory = self::$streamResourceDirectory;
        return self::createResource(
            tempnam("$streamResourceDirectory/", 'socket_'),
            'wb+'
        );
    }

    /**
     * @param int|null $maxMemoryBytes
     *
     * @return false|resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createTemporaryFileResource(?int $maxMemoryBytes = null)
    {
        $uri = 'php://temp';
        if ($maxMemoryBytes !== null && $maxMemoryBytes !== self::DEFAULT_MAX_MEMORY) {
            $mebibyte = 1048576;
            if ($maxMemoryBytes !== 0 && $maxMemoryBytes < $mebibyte) {
                $maxMemoryBytes = $mebibyte;
            }
            $uri .= "/maxmemory:$maxMemoryBytes";
        }

        return self::createResource($uri, 'wb+');
    }

    /**
     * Create stream with certain target
     *
     * @param string $target
     * @param string $mode
     *
     * @return StreamInterface
     */
    public static function createStream(string $target, string $mode) : StreamInterface
    {
        return self::createStreamFromResource(self::createResource($target, $mode));
    }

    /**
     * Create stream with memory
     * @uses createMemoryResource()
     *
     * @return StreamInterface
     */
    public static function createMemoryStream() : StreamInterface
    {
        return self::createStreamFromResource(self::createMemoryResource());
    }

    /**
     * Create stream with temporary file
     *
     * @param ?int $maxMemoryBytes
     *
     * @return StreamInterface
     *@uses createTemporaryFileResource()
     */
    public static function createTemporaryFileStream(?int $maxMemoryBytes = null) : StreamInterface
    {
        return self::createStreamFromResource(self::createTemporaryFileResource($maxMemoryBytes));
    }

    /**
     * @param Container $container
     *
     * @return StreamInterface
     */
    public static function createStorageFileStream(Container $container) : StreamInterface
    {
        return self::createStreamFromResource(self::createStorageFileResource($container));
    }

    /**
     * Create stream from resource
     *
     * @param resource $resource
     *
     * @return StreamInterface
     */
    public static function createStreamFromResource($resource) : StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * Create console stream
     *
     * @param int|null $verbosity
     * @param bool $inMemory
     *
     * @return StreamOutput
     */
    public static function createStreamOutput(
        ?int $verbosity = null,
        bool $inMemory = false
    ): StreamOutput {
        $verbosity = $verbosity??OutputInterface::VERBOSITY_NORMAL;
        $decorated = Validator::isCli() ? true : null;
        return new StreamOutput(
            $inMemory
                ? self::createMemoryResource()
                : self::createTemporaryFileResource(),
            $verbosity,
            $decorated
        );
    }
}
