<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Driver;

use RuntimeException;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;
use TrayDigita\Streak\Source\StoragePath;

class DefaultDriver extends AbstractSessionDriver
{
    protected ?string $sessionName = null;
    protected ?string $savePath = null;
    protected string $extension = 'session';
    protected string $session_cached = '';
    protected mixed $resource;
    protected mixed $wouldBlock = null;

    /**
     * FileHandler constructor.
     */
    protected function afterConstruct()
    {
        $savePath = $this->getContainer(StoragePath::class);
        $savePath = $savePath->getSessionsDirectory();
        $originalPath = $savePath;
        $savePath = $this->eventDispatch('Session:save_path', $savePath);
        $savePath = !is_string($savePath) || !is_dir(dirname($savePath))
            ? $originalPath
            : (realpath($savePath)?:$originalPath);
        if (is_writable(dirname($savePath))) {
            if (!is_dir($savePath)) {
                mkdir($savePath, 0755, true);
            }
        }
        if (!is_writable($savePath)) {
            $savePath = session_save_path();
        } else {
            session_save_path($savePath);
        }

        if (!is_dir($savePath) && !is_writable(dirname($savePath))) {
            throw new RuntimeException(
                sprintf(
                    $this
                        ->getContainer(Translator::class)
                        ->translate('Session save path %s is not writable.'),
                    $savePath
                )
            );
        }

        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }
        $this->savePath = $savePath;
    }

    public function close(): bool
    {
        return $this->resource && flock($this->resource, LOCK_EX|LOCK_NB, $this->wouldBlock);
    }

    public function destroy(string $id) : bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }
        $meta = stream_get_meta_data($this->resource);
        if ($this->wouldBlock) {
            flock($this->resource, LOCK_UN, $this->wouldBlock);
        }
        fclose($this->resource);
        $this->resource = null;
        if (isset($meta['uri']) && file_exists($meta['uri'])) {
            unlink($meta['uri']);
        }

        return true;
    }

    public function gc(int $max_lifetime) : int|false
    {
        $dir = "$this->savePath/$this->sessionName/";
        $openDir = opendir($dir);
        $time = time();
        $ext = strlen($this->extension);
        $ttl = $this->getTTL();
        $counted = 0;
        while ($openDir && $file = readdir($openDir)) {
            if ($file === '.'
                || $file === '..'
                || substr($file, -($ext+1)) !== ".$this->extension"
                || !is_file("$dir/$file")
            ) {
                continue;
            }
            $f = "$dir/$file.$this->extension";
            if ((filemtime($f) + $ttl) < $time) {
                $counted++;
                unlink($f);
            }
        }
        $openDir && closedir($openDir);
        return $counted?:1;
    }

    public function open(string $path, string $name) : bool
    {
        $this->sessionName = $name;
        $dir =  "$this->savePath/$name";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if ($this->resource) {
            flock($this->resource, LOCK_UN, $this->wouldBlock);
        }
        return is_dir($dir);
    }

    public function read(string $id) : string
    {
        $this->session_cached = $this->session_cached?:'';
        if (!$this->sessionName) {
            return $this->session_cached;
        }

        $id   = sha1($id);
        $file =  "$this->savePath/$this->sessionName/$id.$this->extension";
        if (!$this->resource) {
            touch($file);
            $this->resource = fopen($file, 'r+b');
        }
        if (!$this->resource) {
            return $this->session_cached;
        }
        flock($this->resource, LOCK_UN, $this->wouldBlock);
        rewind($this->resource);
        $this->session_cached = '';
        while (!feof($this->resource)) {
            $this->session_cached .= fgets($this->resource, 4096);
        }
        return $this->session_cached;
    }

    public function write(string $id, string $data) : bool
    {
        if (!$this->resource || $this->wouldBlock) {
            return false;
        }

        if (!rewind($this->resource)) {
            return false;
        }
        $length = strlen($data);
        $written = 0;
        while ($length > $written) {
            $write    = fwrite($this->resource, substr($data, $written, 2048));
            $written += $write;
            if (!$write) {
                break;
            }
        }

        return $written === $length;
    }

    public function updateTimeStamp(string $id, string $data) : bool
    {
        return $this->write($id, $data);
    }
}
