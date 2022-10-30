<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use SplFileInfo;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Uploads\Exceptions\DirectoryUnWritAbleException;
use TrayDigita\Streak\Source\Uploads\Exceptions\FileLockedException;
use TrayDigita\Streak\Source\Uploads\Exceptions\FileResourceFailException;
use TrayDigita\Streak\Source\Uploads\Exceptions\FileUnWritAbleException;
use TrayDigita\Streak\Source\Uploads\Exceptions\InvalidOffsetPosition;
use TrayDigita\Streak\Source\Uploads\Exceptions\SourceFileInvalid;

class Handler
{
    const STATUS_WAITING = 0;
    const STATUS_CHECKING = 1;
    const STATUS_NOT_READY = 2;
    const STATUS_READY = 3;
    const STATUS_BEGIN = 4;
    const STATUS_RESUME = 5;
    const STATUS_FAIL = 6;

    /**
     * Default 2 Hours
     */
    const DEFAULT_MAX_AGE = 7200;

    /**
     * @var int
     */
    private int $maxAge;

    /**
     * @var string
     */
    public string $targetCacheFile;

    /**
     * @var int
     */
    private int $status = self::STATUS_WAITING;

    /**
     * @var resource
     */
    private $cacheResource = null;

    /**
     * @var int
     */
    private int $written = 0;

    /**
     * @var int
     */
    private int $size = 0;

    /**
     * @var ?string
     */
    private ?string $movedFile = null;

    /**
     * @var ?string
     */
    private ?string $lastTarget = null;

    /**
     * @param Chunk $chunk
     * @param UploadedFileInterface $uploadedFile
     * @param string $identity
     * @param int $maxAge
     */
    #[Pure] public function __construct(
        private Chunk $chunk,
        private UploadedFileInterface $uploadedFile,
        private string $identity,
        int $maxAge = self::DEFAULT_MAX_AGE
    ) {
        $this->maxAge = $maxAge;
        $this->targetCacheFile = sprintf(
            '%1$s%2$s%3$s.%4$s',
            $this->chunk->getUploadCacheStorageDirectory(),
            DIRECTORY_SEPARATOR,
            sha1($this->identity),
            $this->chunk->partialExtension
        );
    }

    /**
     * @return ?string
     */
    public function getLastTarget(): ?string
    {
        return $this->lastTarget;
    }

    /**
     * @return string|null
     */
    public function getMovedFile(): ?string
    {
        return $this->movedFile;
    }

    /**
     * @return string
     */
    public function getTargetCacheFile(): string
    {
        return $this->targetCacheFile;
    }

    /**
     * @return Chunk
     */
    public function getChunk(): Chunk
    {
        return $this->chunk;
    }

    /**
     * @return int
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * @param int $maxAge
     */
    public function setMaxAge(int $maxAge): void
    {
        $this->maxAge = $maxAge;
    }

    /**
     * @return UploadedFileInterface
     */
    public function getUploadedFile() : UploadedFileInterface
    {
        return $this->uploadedFile;
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return int
     */
    protected function check() : int
    {
        if ($this->status !== self::STATUS_WAITING) {
            return $this->status;
        }

        $this->status = self::STATUS_CHECKING;
        $uploadDirectory = $this->chunk->getUploadCacheStorageDirectory();
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        if (!is_dir($uploadDirectory)) {
            $this->status = self::STATUS_NOT_READY;
            throw new RuntimeException(
                $this->chunk->translate(
                    'Cache upload storage does not exist.'
                )
            );
        }

        if (!is_writable($uploadDirectory)) {
            $this->status = self::STATUS_NOT_READY;
            throw new RuntimeException(
                $this->chunk->translate(
                    'Cache upload storage is not writable.'
                )
            );
        }
        $this->chunk->eventDispatch(
            'Chunk:uploadReady',
            $this
        );
        $this->status = self::STATUS_READY;
        $this->size = is_file($this->targetCacheFile)
            ? filesize($this->targetCacheFile)
            : 0;
        return $this->status;
    }

    /**
     * @return int
     */
    public function getWritten(): int
    {
        return $this->written;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param string $mode
     *
     * @return int
     */
    private function writeResource(string $mode) : int
    {
        if (file_exists($this->targetCacheFile) && !is_writable($this->targetCacheFile)) {
            $this->status = self::STATUS_FAIL;
            throw new FileUnWritAbleException(
                $this->targetCacheFile,
                $this->chunk->translate(
                    'Upload cache file is not writable.'
                )
            );
        }

        $this->cacheResource = Consolidation::callbackReduceError(
            fn() => @fopen($this->targetCacheFile, $mode)
        );

        if (!is_resource($this->cacheResource)) {
            $this->status = self::STATUS_FAIL;
            throw new FileResourceFailException(
                $this->chunk->translate(
                    'Can not create cached stream.'
                )
            );
        }

        flock($this->cacheResource, LOCK_EX|LOCK_NB, $wouldBlock);
        if ($wouldBlock) {
            throw new FileLockedException(
                $this->targetCacheFile,
                $this->chunk->translate('Cache file has been locked.')
            );
        }

        $uploadedStream      = $this->uploadedFile->getStream();
        if ($uploadedStream->isSeekable()) {
            $uploadedStream->rewind();
        }

        $this->written = 0;
        while (!$uploadedStream->eof()) {
            $this->written += (int) fwrite($this->cacheResource, $uploadedStream->read(2048));
        }

        $stat = @fstat($this->cacheResource);
        $this->size = $stat ? (int) ($stat['size']??$this->written) : $this->written;
        flock($this->cacheResource, LOCK_EX);
        return $this->written;
    }

    /**
     * @param int $position
     *
     * @return int
     */
    public function start(int $position = 0): int
    {
        if ($this->status === self::STATUS_WAITING) {
            $this->check();
        }

        if ($this->status !== self::STATUS_READY) {
            return $this->written;
        }

        $mode = 'ab+';
        if ($position === 0) {
            $mode = 'wb+';
        } elseif ($position <> $this->size) {
            throw new InvalidOffsetPosition(
                $position,
                $this->size,
                $this->chunk->translate(
                    'Offset upload position is invalid.'
                )
            );
        }

        $this->status = self::STATUS_RESUME;
        return $this->writeResource($mode);
    }

    /**
     * @param string $target
     * @param bool $overrideIfExists
     * @param bool $increment
     *
     * @return bool|string
     */
    public function put(
        string $target,
        bool $overrideIfExists = false,
        bool $increment = true
    ): false|string {
        if (!is_file($this->movedFile?:$this->targetCacheFile)) {
            throw new SourceFileInvalid(
                $this->movedFile?:$this->targetCacheFile,
                $this->chunk->translate(
                    'Source uploaded file does not exist.'
                )
            );
        }

        if ($this->status === self::STATUS_WAITING) {
            $this->check();
        }

        $ready = match ($this->status) {
            self::STATUS_READY,
            self::STATUS_BEGIN,
            self::STATUS_RESUME => true,
            default => false
        };
        if (!$ready) {
            throw new RuntimeException(
                sprintf(
                    $this->chunk->translate(
                        'Upload cache file is not ready to move : (%d).'
                    ),
                    $this->status
                )
            );
        }

        $this->close();
        if (file_exists($target)) {
            if (!$overrideIfExists) {
                if ($increment) {
                    $spl = new SplFileInfo($target);
                    $targetDirectory = $spl->getPath();
                    $ext   = $spl->getExtension();
                    $targetBaseName = substr($spl->getBasename(), 0, -(strlen($ext)-1));
                    $count = 0;
                    do {
                        if ($count > 500) {
                            throw new RuntimeException(
                                $this->chunk->translate(
                                    'Could not determine increment target file.'
                                ),
                            );
                        }
                        $count++;
                        $target = sprintf(
                            '%1$s/%2$s%3$s',
                            $targetDirectory,
                            "$targetBaseName.$count",
                            $ext ? ".$ext" : ''
                        );
                    } while (file_exists($target));
                } else {
                    return false;
                }
            } else {
                if (!is_writable($target)) {
                    throw new FileUnWritAbleException(
                        $target,
                        sprintf(
                            $this->chunk->translate(
                                '%s is not writable.'
                            ),
                            $target
                        )
                    );
                }
            }
        }

        $targetDirectory = dirname($target);
        if (!is_dir($targetDirectory)) {
            mkdir($target, 0755, true);
        }

        if (!is_writable($targetDirectory)) {
            throw new DirectoryUnWritAbleException(
                $targetDirectory,
                sprintf(
                    $this->chunk->translate(
                        '%s is not writable.'
                    ),
                    $target
                )
            );
        }

        if ($this->movedFile) {
            $result = Consolidation::callbackReduceError(fn () => copy($this->movedFile, $target));
        } else {
            $result = Consolidation::callbackReduceError(fn() => rename($this->targetCacheFile, $target));
        }
        $this->lastTarget = null;
        if ($result) {
            $this->lastTarget = realpath($target) ?: $target;
            if (!$this->movedFile) {
                $this->movedFile = $this->lastTarget;
            }
        }

        return $this->lastTarget?:false;
    }

    public function close()
    {
        if (is_resource($this->cacheResource)) {
            fflush($this->cacheResource);
            flock($this->cacheResource, LOCK_UN);
            fclose($this->cacheResource);
            $this->cacheResource = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
