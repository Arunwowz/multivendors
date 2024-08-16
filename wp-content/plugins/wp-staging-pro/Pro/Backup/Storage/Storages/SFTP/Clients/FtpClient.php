<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients;

use Exception;

use function WPStaging\functions\debug_log;

class FtpClient implements ClientInterface
{
    /** @var resource */
    protected $ftp;

    /** @var string */
    protected $host;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var int */
    protected $port;

    /** @var bool */
    protected $passive;

    /** @var bool */
    protected $ssl;

    /** @var string|false */
    protected $error;

    /** @var bool */
    protected $isLogin;

    /** @var string */
    protected $path;

    /**
     * @var string $host
     * @var string $username
     * @var string $password
     * @var bool   $ssl
     * @var bool   $passive
     * @var int    $port
     *
     * @throws FtpException
     */
    public function __construct($host, $username, $password, $ssl, $passive, $port)
    {
        if (!extension_loaded('ftp')) {
            throw new FtpException("PHP FTP extension not loaded");
        }

        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
        $this->username = $username;
        $this->password = $password;
        $this->passive = $passive;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function login($retry = 3)
    {
        if ($this->isLogin) {
            return true;
        }

        if (is_resource($this->ftp) && @ftp_systype($this->ftp) !== false) {
            return true;
        }

        try {
            if ($this->ssl) {
                $this->ftp = @ftp_ssl_connect($this->host, $this->port, 30);
            } else {
                $this->ftp = @ftp_connect($this->host, $this->port, 30);
            }
        } catch (Exception $ex) {
            debug_log(sprintf('Extension - Hostname: %s, Error: %s', $this->host, $ex->getMessage()));
            $this->ftp = false;
        }

        if ($this->ftp === false && $retry > 0) {
            return $this->login($retry - 1);
        }

        if ($this->ftp === false) {
            $this->isLogin = false;
            return false;
        }

        $result = @ftp_login($this->ftp, $this->username, $this->password);

        if ($result === false) {
            $this->isLogin = false;
            return false;
        }

        $this->isLogin = true;
        ftp_pasv($this->ftp, $this->passive);
        ftp_set_option($this->ftp, FTP_AUTOSEEK, false);

        return true;
    }

    public function upload($remotePath, $file, $chunk, $offset = 0)
    {
        if (!$this->login()) {
            return false;
        }

        $result = false;
        if (($handle = fopen('php://temp', 'wb+'))) {
            if (($fileSize = fwrite($handle, $chunk))) {
                rewind($handle);
            }

            if ($remotePath !== '') {
                $remotePath = trailingslashit($remotePath);
            }

            try {
                $result = @ftp_fput($this->ftp, $remotePath . $file, $handle, FTP_BINARY, $offset);
            } catch (Exception $e) {
                debug_log(sprintf("Ftp Extension: Offset - %s, Error:  %s", $offset, $e->getMessage()));
            }

            fclose($handle);
        }

        return $result;
    }

    public function close()
    {
        if ($this->ftp === null || $this->ftp === false) {
            $this->isLogin = false;
            $this->ftp = null;
            return;
        }

        $this->isLogin = false;
        @ftp_close($this->ftp);
        $this->ftp = null;
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
        $this->login();

        if ($this->ftp === false) {
            return [];
        }

        if ($path !== '') {
            ftp_chdir($this->ftp, $path);
        }

        $items = [];
        try {
            $items = ftp_rawlist($this->ftp, '-tr');
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return [];
        }

        $files = [];
        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }

            $metas = preg_split('/\s+/', trim($item));

            if ($metas[1] === '3' || $metas[1] === 'd') {
                continue;
            }

            $files[] = [
                'time' => null,
                'name' => $metas[count($metas) - 1],
                'size' => isset($metas[4]) ? (int)$metas[4] : null,
            ];
        }

        $this->close();
        return $files;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function deleteFile($path)
    {
        $this->login();

        if ($this->ftp === false) {
            return false;
        }

        $filepath = empty($this->path) ? $path : sprintf('%s/%s', $this->path, $path);

        try {
            $result = ftp_delete($this->ftp, $filepath);
            $this->close();
            return $result;
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return false;
        }
    }
}
