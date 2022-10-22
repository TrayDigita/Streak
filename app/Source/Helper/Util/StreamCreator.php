<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class StreamCreator
{
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
     * Create resource with temporary file
     *
     * @return resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createTemporaryFileResource()
    {
        return self::createResource('php://temp', 'wb+');
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
     * @uses createTemporaryFileResource()
     *
     * @return StreamInterface
     */
    public static function createTemporaryFileStream() : StreamInterface
    {
        return self::createStreamFromResource(self::createTemporaryFileResource());
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
