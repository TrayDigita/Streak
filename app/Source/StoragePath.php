<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use ReflectionClass;
use Throwable;
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

    protected string $rootDirectory = '';
    protected string $storagePath = '';
    protected string $adminPath = '';
    protected string $memberPath = '';
    protected string $apiPath = '';
    protected string $appDirectory = '';
    private bool $initialize = false;

    public function __construct(Container $container)
    {
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

        $defaultStorage      = $this->rootDirectory . DIRECTORY_SEPARATOR . 'storage';
        $this->rootDirectory = Consolidation::rootDirectory();
        $storage             = ($path['storage']??null)?:null;
        $storage = !is_string($storage) ? null : $storage;
        if ($storage && !Validator::isRelativePath($storage)) {
            if (str_starts_with($storage, './')) {
                $storage = $this->rootDirectory . substr($storage, 2);
            } else {
                $storageTmp = realpath($storage) ?: null;
                if (!$storageTmp) {
                    $parent = realpath(dirname($storage));
                    if (!$parent || !is_writable($parent)) {
                        $storageTmp = null;
                    }
                }
                $storage = $storageTmp ?: $this->rootDirectory . '/' . $storage;
            }
        } else {
            $storage = $this->rootDirectory . '/storage';
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

        $this->storagePath = $storage;
        $apiPath = $path->get('api');
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

    public function getStoragePath() : string
    {
        return $this->initialize()->storagePath;
    }

    public function getCachePath() : string
    {
        $storagePath = $this->getStoragePath();
        $cache = $this->eventDispatch('StoragePath:cache:path', 'cache');
        $cache = is_string($cache) || trim($cache) === '' || str_contains($cache, '..')
            ? 'cache'
            : $cache;
        return "$storagePath/$cache";
    }

    public function getLogPath() : string
    {
        $storagePath = $this->getStoragePath();
        $logs = $this->eventDispatch('StoragePath:cache:path', 'logs');
        $logs = is_string($logs) || trim($logs) === '' || str_contains($logs, '..')
            ? 'logs'
            : $logs;
        return "$storagePath/$logs";
    }

    public function getSessionsPath() : string
    {
        $storagePath = $this->getStoragePath();
        $sessions = $this->eventDispatch('StoragePath:cache:path', 'sessions');
        $sessions = is_string($sessions) || trim($sessions) === '' || str_contains($sessions, '..')
            ? 'sessions'
            : $sessions;
        return "$storagePath/$sessions";
    }
}
