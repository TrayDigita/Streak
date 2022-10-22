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
     * @return resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createMemoryResource()
    {
        return self::createResource('php://memory', 'wb+');
    }

    /**
     * @return resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function createTemporaryFileResource()
    {
        return self::createResource('php://temp', 'wb+');
    }

    public static function createStream(string $target, string $mode) : StreamInterface
    {
        return new Stream(self::createResource($target, $mode));
    }

    public static function createMemoryStream() : StreamInterface
    {
        return new Stream(self::createMemoryResource());
    }

    public static function createTemporaryFileStream() : StreamInterface
    {
        return new Stream(self::createTemporaryFileResource());
    }

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
