<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients;

use Exception;
use WPStaging\Pro\Backup\Exceptions\StorageException;
use WPStaging\Pro\Backup\WithBackupIdentifier;
use WPStaging\Vendor\phpseclib\Crypt\RSA;
use WPStaging\Vendor\phpseclib\Net\SFTP;
use WPStaging\Vendor\phpseclib\Net\SSH2;

use function WPStaging\functions\debug_log;

class SftpClient implements ClientInterface
{
    use WithBackupIdentifier;

    /** @var SFTP */
    protected $sftp;

    /** @var string */
    protected $host;

    /** @var int */
    protected $port;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string */
    protected $key;

    /** @var string */
    protected $passphrase;

    /** @var string|false */
    protected $error;

    /** @var bool */
    private $isBadKey;

    /** @var bool */
    private $isLogin = false;

    /** @var string */
    protected $path;

    public function __construct($host, $username, $password, $key, $passphrase, $port)
    {
        $this->username = $username;
        $this->password = $password;
        $this->key = $key;
        $this->passphrase = $passphrase;

        $this->host = $host;
        $this->port = $port;
        $this->isLogin = false;

        if (defined('NET_SFTP_LOGGING')) {
            define('NET_SFTP_LOGGING', SSH2::LOG_COMPLEX);
        }

        if (defined('NET_SSH2_LOGGING')) {
            define('NET_SSH2_LOGGING', SSH2::LOG_COMPLEX);
        }
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function connect()
    {
        $this->sftp = new SFTP($this->host, $this->port, 90);

        if (!empty($this->password)) {
            return $this->sftp->login($this->username, $this->password);
        }

        $rsa = new RSA();
        $rsa->setPassword($this->passphrase);
        $result = $rsa->loadKey(trim($this->key));
        $this->isBadKey = false;
        if (!$result) {
            $this->isBadKey = true;
        }

        return $this->sftp->login($this->username, $rsa);
    }

    /**
     * @return bool
     */
    public function login()
    {
        if ($this->isLogin) {
            return true;
        }

        $result = $this->connect();

        if ($result === true) {
            $this->isLogin = true;
            return true;
        }

        $this->isLogin = false;
        if (!$this->sftp->isConnected()) {
            $this->error = "Unable to connect to SFTP server ";
            debug_log("Error: " . $this->error);
            return false;
        }

        if (!$this->sftp->isAuthenticated()) {
            $this->error = "Unable to login to SFTP server ";
            debug_log("Error: " . $this->error);
            if ($this->isBadKey) {
                $this->error .= ' - Either the passphrase or key provided is not correct. ';
                debug_log("Error: " . $this->error);
            }

            return false;
        }

        debug_log("Error: Unable to login via sFTP. Unknown error. ");
        return false;
    }

    /**
     * @param string $remotePath
     * @param string $file
     * @param int $chunk
     * @param int $offset
     * @return bool
     */
    public function upload($remotePath, $file, $chunk, $offset = 0)
    {
        if (!$this->sftp->isConnected()) {
            $this->connect();
        }

        $result = false;
        if (($handle = fopen('php://temp', 'wb+'))) {
            if (($fileSize = fwrite($handle, $chunk))) {
                rewind($handle);
            }

            if (!$this->isDirectoryChanged($remotePath)) {
                $file = '/' . trailingslashit($remotePath) . $file;
            }

            try {
                $result = $this->sftp->put($file, $handle, SFTP::SOURCE_LOCAL_FILE | SFTP::RESUME_START, $offset);
            } catch (Exception $e) {
                debug_log("Error: " . $e->getMessage());
            }

            fclose($handle);
        }

        if (!$result) {
            debug_log("sFTP SFTP errors: " . json_encode($this->sftp->getSFTPErrors()));
            throw new StorageException("Unable to upload backup to SFTP storage. Backup is still available locally");
        }

        return $result;
    }

    public function close()
    {
        $this->isLogin = false;
        if ($this->sftp !== null) {
            $this->sftp->disconnect();
        }
    }

    public function getError()
    {
        return $this->error;
    }

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function getFiles($path)
    {
        if (!$this->sftp->isConnected()) {
            $this->connect();
        }

        if ($this->isDirectoryChanged($path)) {
            $path = '.';
        }

        try {
            $items = @$this->sftp->rawlist($path);
        } catch (Exception $ex) {
            return false;
        }

        if (empty($items)) {
            $this->error .= "Could not upload backup via SFTP to " . $path . " Does the folder exist on the remote server? ";
            $this->error .= "The backup is still available on the local file system.";
            return false;
        }

        $files = [];
        foreach ($items as $file) {
            if ($file['type'] !== 1) {
                continue;
            }

            $files[] = [
                'name' => $file['filename'],
                'time' => $file['mtime'],
                'size' => $file['size'],
            ];
        }

        uasort($files, function ($file1, $file2) {
            return $file1['time'] < $file2['time'] ? -1 : 1;
        });

        return array_values($files);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function deleteFile($path)
    {
        try {
            return @$this->sftp->delete($path);
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isDirectoryChanged($path)
    {
        $path = rtrim($path);
        $path = untrailingslashit($path);
        $currentPath = $this->sftp->pwd();
        if (empty($path)) {
            return false;
        }

        if ('/' . $path === $currentPath) {
            return true;
        }

        return $this->sftp->chdir($path);
    }
}
