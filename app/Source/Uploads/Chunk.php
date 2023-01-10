<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads;

use DirectoryIterator;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\StoragePath;
use TrayDigita\Streak\Source\SystemInitialHandler;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class Chunk extends AbstractContainerization
{
    use TranslationMethods,
        EventsMethods;

    protected ?UploadedFileInterface $file;

    /**
     * 5 Hours
     */
    const MAX_AGE_FILE = 18000;

    /**
     * Maximum size for unlink
     */
    const DEFAULT_MAX_DELETE_COUNT = 50;

    /**
     * @var string
     */
    public readonly string $uploadCacheStorageDirectory;

    /**
     * @var string
     */
    public readonly string $partialExtension;

    /**
     * @var int
     */
    protected int $maxDeletionCount = self::DEFAULT_MAX_DELETE_COUNT;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->partialExtension = 'part';
        $this->uploadCacheStorageDirectory = $container
            ->get(StoragePath::class)
            ->getCacheUploadDirectory();
    }

    /**
     * @return string
     */
    public function getUploadCacheStorageDirectory(): string
    {
        return $this->uploadCacheStorageDirectory;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function serverWithResponseHeader(ResponseInterface $response) : ResponseInterface
    {
        return $this->getContainer(SystemInitialHandler::class)
            ->noCacheResponse(
                $response->withHeader('Accept-Ranges', 'bytes')
            );
    }

    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string $identity
     * @param int $maxAge
     *
     * @return Handler
     */
    #[Pure] public function createHandler(
        UploadedFileInterface $uploadedFile,
        string $identity,
        int $maxAge = Handler::DEFAULT_MAX_AGE
    ) : Handler {
        return new Handler(
            $this,
            $uploadedFile,
            $identity,
            $maxAge
        );
    }

    /**
     * @param ?int $max
     *
     * @return int
     * @see Chunk::DEFAULT_MAX_DELETE_COUNT
     */
    public function clean(?int $max = null) : int
    {
        $max ??= $this->maxDeletionCount;
        if (!is_dir($this->uploadCacheStorageDirectory) || $max <= 0) {
            return 0;
        }

        $deleted = 0;
        foreach (new DirectoryIterator($this->uploadCacheStorageDirectory) as $item) {
            if (!$item->isFile()
                || $item->isLink()
                || $item->getExtension() !== $this->partialExtension
            ) {
                continue;
            }
            if ($item->getMTime() > (time() - self::MAX_AGE_FILE)) {
                continue;
            }
            if (!$item->isWritable()) {
                continue;
            }
            if ($max-- < 0) {
                break;
            }
            $deleted++;
            unlink($item->getRealPath());
        }

        return $deleted;
    }

    public function __destruct()
    {
        $this->clean();
    }
}
