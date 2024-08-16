<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients;

interface ClientInterface
{
    /** @return bool */
    public function login();

    /**
     * @param string $remotePath
     * @param mixed $chunk
     * @param int $offset
     *
     * @return bool
     */
    public function upload($remotePath, $file, $chunk, $offset = 0);

    public function close();

    /** @return string|false */
    public function getError();

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function getFiles($path);

    /**
     * @param string $path
     *
     * @return bool
     */
    public function deleteFile($path);

    /**
     * @param string $path
     */
    public function setPath($path);
}
