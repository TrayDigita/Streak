<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Interfaces;

interface SessionDriverInterface
{
    /**
     * Set default session name
     *
     * @param string $session_name
     */
    public function setDefaultSessionName(string $session_name);

    /**
     * Get default session name
     *
     * @return string
     */
    public function getDefaultSessionName() : string;

    /**
     * Set the time to live
     *
     * @param int $max_lifetime
     */
    public function setTTL(int $max_lifetime);

    /**
     * Get time to live
     *
     * @return int
     */
    public function getTTL() : int;

    /**
     * Close the session
     * @see \SessionHandlerInterface::close()
     *
     * @return bool
     */
    public function close() : bool;

    /**
     * Destroy the session
     * @see \SessionHandlerInterface::destroy()
     *
     * @param string $id
     *
     * @return bool
     */
    public function destroy(string $id) : bool;

    /**
     * Cleanup old sessions
     *
     * @see \SessionHandlerInterface::gc()
     * @param int $max_lifetime
     *
     * @return int|false
     */
    public function gc(int $max_lifetime) : int|false;

    /**
     * Initiate session
     * @see \SessionHandlerInterface::open()
     *
     * @param string $path
     * @param string $name
     *
     * @return bool
     */
    public function open(string $path, string $name) : bool;

    /**
     * Read session data
     * @see \SessionHandlerInterface::read()
     *
     * @param string $id
     *
     * @return string
     */
    public function read(string $id) : string;

    /**
     * Write Session data
     * @see \SessionHandlerInterface::write()
     *
     * @param string $id
     * @param string $data
     *
     * @return bool
     */
    public function write(string $id, string $data) : bool;

    /**
     * Update session timestamp
     *
     * @see \SessionHandler::updateTimestamp()
     * @param string $id
     * @param string $data
     *
     * @return bool
     */
    public function updateTimeStamp(string $id, string $data) : bool;
}
