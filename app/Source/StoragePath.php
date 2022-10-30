<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Traits\ComposerLoaderObject;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class StoragePath extends AbstractContainerization
{
    use ComposerLoaderObject,
        EventsMethods;

    const DEFAULT_API_PATH = 'api';
    const DEFAULT_ADMIN_PATH = 'panel';
    const DEFAULT_MEMBER_PATH = 'dashboard';
    /**
     * @var string
     */
    public readonly string $directorySeparator;

    /**
     * @var string
     */
    protected string $rootDirectory = '';

    /**
     * @var string
     */
    protected string $storageDirectory = '';

    /**
     * @var string
     */
    protected string $adminPath = '';

    /**
     * @var string
     */
    protected string $memberPath = '';

    /**
     * @var string
     */
    protected string $apiPath = '';

    /**
     * @var string
     */
    protected string $appDirectory = '';

    /**
     * @var bool
     */
    private bool $initialize = false;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->directorySeparator = DIRECTORY_SEPARATOR;
        parent::__construct($container);
        $this->initialize();
    }

    protected function initialize() : static
    {
        if ($this->initialize) {
            return $this;
        }
        $this->initialize = true;
        $path = $this->getContainer(Configurations::class)->get('path');
        if (!$path instanceof Configurations) {
            $path = new Configurations();
        }

        $this->appDirectory = Consolidation::appDirectory();
        $this->rootDirectory = Consolidation::rootDirectory();

        $defaultStorage      = "$this->rootDirectory{$this->directorySeparator}storage";
        $storage             = ($path['storage']??null)?:null;
        $storage = !is_string($storage) ? null : $storage;
        if ($storage && !Validator::isRelativePath($storage)) {
            if (str_starts_with($storage, './') || str_starts_with($storage, '.\\')) {
                $storage = $this->rootDirectory . substr($storage, 2);
            } else {
                $storageTmp = realpath($storage) ?: null;
                if (!$storageTmp) {
                    $parent = realpath(dirname($storage));
                    if (!$parent || !is_writable($parent)) {
                        $storageTmp = null;
                    }
                }
                $storage = $storageTmp ?: $this->rootDirectory . $this->directorySeparator . $storage;
            }
        } else {
            $storage = $this->rootDirectory . $this->directorySeparator . 'storage';
        }

        if (!is_dir($storage)) {
            if (!is_writable(dirname($storage))) {
                $storage = $defaultStorage;
            } else {
                Consolidation::callbackReduceError(fn() => mkdir($storage, 0755, true));
                if (!is_writable($storage)) {
                    $storage = $defaultStorage;
                }
            }
        }

        if (!is_dir($storage)) {
            Consolidation::callbackReduceError(fn() => mkdir($storage, 0755, true));
        }

        $this->storageDirectory = $storage;
        $apiPath                = $path->get('api');
        $apiPath = !is_string($apiPath) ? '': $apiPath;
        $apiPath = trim($apiPath) === '' ? self::DEFAULT_API_PATH : preg_replace(
            '~[^a-z0-9_\-]~i',
            '-',
            trim($apiPath)
        );

        $memberPath  = $path->get('member');
        $memberPath = !is_string($memberPath) ? '': $memberPath;
        $memberPath = trim($memberPath) === '' ? self::DEFAULT_MEMBER_PATH : preg_replace(
            '~[^a-z0-9_\-]~i',
            '-',
            trim($memberPath)
        );

        $adminPath  = $path->get('admin');
        $adminPath = !is_string($adminPath) ? '': $adminPath;
        $adminPath = trim($adminPath) === '' ? self::DEFAULT_ADMIN_PATH : preg_replace(
            '~[^a-z0-9_\-]~i',
            '-',
            trim($adminPath)
        );

        $apiPath = preg_match('~^[-_]+$~', $apiPath) ? self::DEFAULT_API_PATH : $apiPath;
        $memberPath = preg_match('~^[-_]+$~', $memberPath) ? self::DEFAULT_API_PATH : $memberPath;
        $adminPath = preg_match('~^[-_]+$~', $adminPath) ? self::DEFAULT_ADMIN_PATH : $adminPath;
        $list = array_unique([$adminPath, $memberPath, $apiPath]);
        // if any same name -> fallback to default
        if (count($list) < 3) {
            $adminPath = self::DEFAULT_ADMIN_PATH;
            $apiPath = self::DEFAULT_API_PATH;
            $memberPath = self::DEFAULT_MEMBER_PATH;
        }
        $this->adminPath    = $adminPath;
        $this->apiPath      = $apiPath;
        $this->memberPath   = $memberPath;
        return $this;
    }

    /**
     * @return string
     */
    public function getAppDirectory(): string
    {
        return $this->appDirectory;
    }

    /**
     * @return string
     */
    public function getAdminPath(): string
    {
        return $this->initialize()->adminPath;
    }

    /**
     * @return string
     */
    public function getMemberPath(): string
    {
        return $this->initialize()->memberPath;
    }

    /**
     * @return string
     */
    public function getApiPath(): string
    {
        return $this->initialize()->apiPath;
    }

    public function getRootDirectory() : string
    {
        return $this->initialize()->rootDirectory;
    }

    /**
     * Storage directory
     *
     * @return string
     */
    public function getStorageDirectory() : string
    {
        return $this->initialize()->storageDirectory;
    }

    /**
     * Cache directory
     *
     * @return string
     */
    public function getCacheDirectory() : string
    {
        $storagePath = $this->getStorageDirectory();
        $cache = $this->eventDispatch('StoragePath:cache:path', 'cache');
        $cache = is_string($cache) || trim($cache) === '' || str_contains($cache, '..')
            ? 'cache'
            : $cache;
        return "$storagePath{$this->directorySeparator}$cache";
    }

    /**
     * @return string
     */
    public function getCacheStreamDirectory() : string
    {
        $cacheDirectory = $this->getCacheDirectory();
        $stream = $this->eventDispatch('StoragePath:streams:path', 'streams');
        $stream = is_string($stream) || trim($stream) === '' || str_contains($stream, '..')
            ? 'streams'
            : $stream;
        return "$cacheDirectory{$this->directorySeparator}$stream";
    }

    /**
     * @return string
     */
    public function getCacheUploadDirectory() : string
    {
        $cacheDirectory = $this->getCacheDirectory();
        $uploads = $this->eventDispatch('StoragePath:uploads:path', 'uploads');
        $uploads = is_string($uploads) || trim($uploads) === '' || str_contains($uploads, '..')
            ? 'stream'
            : $uploads;
        return "$cacheDirectory{$this->directorySeparator}$uploads";
    }

    /**
     * Logs directory
     *
     * @return string
     */
    public function getLogDirectory() : string
    {
        $storagePath = $this->getStorageDirectory();
        $logs = $this->eventDispatch('StoragePath:logs:path', 'logs');
        $logs = is_string($logs) || trim($logs) === '' || str_contains($logs, '..')
            ? 'logs'
            : $logs;
        return "$storagePath{$this->directorySeparator}$logs";
    }

    /**
     * Sessions directory
     *
     * @return string
     */
    public function getSessionsDirectory() : string
    {
        $storagePath = $this->getStorageDirectory();
        $sessions = $this->eventDispatch('StoragePath:sessions:path', 'sessions');
        $sessions = is_string($sessions) || trim($sessions) === '' || str_contains($sessions, '..')
            ? 'sessions'
            : $sessions;
        return "$storagePath{$this->directorySeparator}$sessions";
    }
}
