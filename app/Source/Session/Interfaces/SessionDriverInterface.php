<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Interfaces;

interface SessionDriverInterface
{
    public function setDefaultSessionName(string $session_name);
    public function getDefaultSessionName() : string;
    public function setTTL(int $max_lifetime);
    public function getTTL() : int;
    public function close() : bool;
    public function destroy(string $id) : bool;
    public function gc(int $max_lifetime) : int|false;
    public function open(string $path, string $name) : bool;
    public function read(string $id) : string;
    public function write(string $id, string $data) : bool;
    public function updateTimeStamp(string $id, string $data) : bool;
}
