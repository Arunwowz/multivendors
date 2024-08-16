<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients;

use Exception;

class FtpCurlClient implements ClientInterface
{
    /** @var resource */
    protected $handler;

    /** @var string */
    protected $hostname;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var bool */
    protected $passive;

    /** @var bool */
    protected $ssl;

    /** @var int */
    protected $port;

    /** @var string|false */
    protected $error;

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
    public function __construct($hostname, $username, $password, $ssl, $passive, $port)
    {
        if (!extension_loaded('curl')) {
            throw new FtpException("PHP cURL extension not loaded");
        }

        $this->hostname  = $hostname;
        $this->username  = $username;
        $this->password  = $password;
        $this->port      = $port;
        $this->passive   = $passive;
        $this->ssl       = $ssl;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return bool
     */
    public function login()
    {
        $this->error = false;
        try {
            $this->sendCurlRequest("", [
                CURLOPT_TIMEOUT => 120
            ]);
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }

        return $this->error === false;
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
        if (($handle = fopen('php://temp', 'wb+'))) {
            if (($fileSize = fwrite($handle, $chunk))) {
                rewind($handle);
            }

            $curlOptions = [
                CURLOPT_UPLOAD     => true,
                CURLOPT_FTPAPPEND  => true,
                CURLOPT_INFILE     => $handle,
                CURLOPT_INFILESIZE => $fileSize,
            ];

            if ($remotePath !== '') {
                $remotePath = trailingslashit($remotePath);
            }

            $this->error = false;
            try {
                $this->sendCurlRequest($remotePath . $file, $curlOptions);
            } catch (Exception $ex) {
                $this->error = $ex->getMessage();
            }

            fclose($handle);
            return $this->error === false;
        }

        return false;
    }

    /**
     * @return void
     */
    public function close()
    {
        try {
            if ($this->handler !== null) {
                curl_close($this->handler);
                $this->handler = null;
            }
        } catch (Exception $ex) {
        }
    }

    /**
     * @param string $path
     *
     * @return array
     */
    public function getFiles($path)
    {
        $this->error = false;
        try {
            $response = $this->sendCurlRequest(sprintf('/%s/', $path), [
                CURLOPT_CUSTOMREQUEST => 'LIST -tr'
            ]);
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }

        if ($this->error !== false) {
            return false;
        }

        $items = explode(PHP_EOL, $response);
        $files = [];
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

        return $files;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function deleteFile($path)
    {
        $this->error = false;
        $filePath = empty($this->path) ? $path : sprintf('%s/%s', $this->path, $path);
        $curlPath = empty($this->path) ? '' : sprintf('/%s/', $this->path);

        try {
            $this->sendCurlRequest($curlPath, [
                CURLOPT_QUOTE => [
                    sprintf('DELE /%s', $filePath)
                ]
            ]);
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }

        return $this->error === false;
    }

    public function getError()
    {
        return $this->error;
    }

    protected function sendCurlRequest($path, $options = [])
    {
        $this->handler = curl_init();

        // Set FTP URL
        curl_setopt($this->handler, CURLOPT_URL, sprintf('ftp://%s:%d/%s', $this->hostname, $this->port, $path));

        // Set username and password
        curl_setopt($this->handler, CURLOPT_USERPWD, sprintf('%s:%s', $this->username, $this->password));

        // Set default configuration
        curl_setopt($this->handler, CURLOPT_HEADER, false);
        curl_setopt($this->handler, CURLOPT_RETURNTRANSFER, true);
        //
        curl_setopt($this->handler, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($this->handler, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($this->handler, CURLOPT_TIMEOUT, 0);

        // Add additional options to connect to FTP with SSL if SSL was selected
        if ($this->ssl) {
            curl_setopt($this->handler, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->handler, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->handler, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
            curl_setopt($this->handler, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
        }

        // Is passive
        if ($this->passive) {
            curl_setopt($this->handler, CURLOPT_FTP_USE_EPSV, true);
        } else {
            curl_setopt($this->handler, CURLOPT_FTP_USE_EPRT, true);
            curl_setopt($this->handler, CURLOPT_FTPPORT, 0);
        }

        // Apply cURL options
        foreach ($options as $name => $value) {
            curl_setopt($this->handler, $name, $value);
        }

        // HTTP request
        $response = curl_exec($this->handler);
        if ($response === false) {
            if (($errno = curl_errno($this->handler))) {
                switch ($errno) {
                    case 6:
                    case 7:
                        $this->error = __("Unable to connect FTP server. Check your settings.", 'wp-staging');
                        break;
                    case 9:
                        $this->error = __("Unable to connect FTP server. Check your permissions.", 'wp-staging');
                        break;
                    case 28:
                        $this->error = __("Unable to connect FTP server. Server timeout. Check your settings.", 'wp-staging');
                        break;
                    case 67:
                        $this->error = __("Unable to login to FTP server. Check your credentials.", 'wp-staging');
                        break;
                    default:
                        $this->error = sprintf(__("Unable to connect FTP server. Error code: %s", 'wp-staging'), $errno);
                }
            }
        }

        // HTTP errors
        $httpCode = curl_getinfo($this->handler, CURLINFO_HTTP_CODE);
        if ($httpCode === 429) {
            $this->error = __("FTP Curl Client - Too many requests!", 'wp-staging');
        }

        if ($httpCode >= 500) {
            $this->error = __("FTP Curl Client - Internal Server Error", 'wp-staging');
        } elseif ($httpCode >= 400) {
            $this->error = sprintf(__("FTP Curl Client - Error code: %s", 'wp-staging'), $httpCode);
        }

        return $response;
    }
}
