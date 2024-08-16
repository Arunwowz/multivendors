<?php

namespace WPStaging\Pro\Backup\Storage;

use WPStaging\Core\WPStaging;

class StorageBase
{
    /** @var Providers */
    private $providers;

    /** @var string */
    private $error;

    /** @var string */
    private $provider;

    public function __construct(Providers $providers)
    {
        $this->providers = $providers;
    }

    public function revoke()
    {
        $authProvider = $this->getProvider();
        if ($authProvider === false) {
            return $this->jsonResponse($this->error);
        }

        $result = $authProvider->revoke();

        $providerName = $this->providers->getStorageProperty($this->provider, 'name', true);
        if (!$result) {
            return $this->jsonResponse("Failed to revoke provider for: " . $providerName);
        }

        return $this->jsonResponse("Revoke successful for: " . $providerName, true);
    }

    public function authenticate()
    {
        $authProvider = $this->getProvider();
        if ($authProvider === false) {
            return $this->jsonResponse($this->error);
        }

        $result = $authProvider->authenticate();

        $providerName = $this->providers->getStorageProperty($this->provider, 'name', true);
        if ($result !== true) {
            return $this->jsonResponse("Connection failed to " . $providerName . ' - Open "System Info > WP STAGING debug log" for details.');
        }

        return $this->jsonResponse("Successfully connected to " . $providerName, true);
    }

    public function testConnection()
    {
        $authProvider = $this->getProvider();
        if ($authProvider === false) {
            return $this->jsonResponse($this->error);
        }

        $result = $authProvider->testConnection();

        $providerName = $this->providers->getStorageProperty($this->provider, 'name', true);
        if ($result !== true) {
            return $this->jsonResponse("Connection failed to " . $providerName . ' - Open "System Info > WP STAGING debug log" for details.');
        }

        return $this->jsonResponse($providerName . " - Connection test successfull.", true);
    }

    public function updateSettings()
    {
        $authProvider = $this->getProvider();
        if ($authProvider === false) {
            return $this->jsonResponse($this->error);
        }

        $result = $authProvider->updateSettings($_POST);

        $providerName = $this->providers->getStorageProperty($this->provider, 'name', true);
        if (!$result) {
            return $this->jsonResponse("Failed to save settings for " . $providerName);
        }

        if ($authProvider->isAuthenticated()) {
            $this->jsonResponse("Settings saved successfully.", true);
            return;
        }

        return $this->jsonResponse('Settings saved, but connection to ' . $providerName . ' failed. Check the credentials or open System Info > WP STAGING debug log for details.', true, true);
    }

    /**
     * @return bool|AbstractStorage
     */
    private function getProvider()
    {
        if (!isset($_POST['provider'])) {
            $this->error = 'Provider not set!';
            return false;
        }

        $provider = sanitize_text_field($_POST['provider']);
        if (!in_array($provider, $this->providers->getStorageIds(true))) {
            $this->error = 'Provider not available for remote storage!';
            return false;
        }

        $authClass = $this->providers->getStorageProperty($provider, 'authClass', true);
        if ($authClass === false || !class_exists($authClass)) {
            $this->error = "Auth class for provider doesn't exist!";
            return false;
        }

        $this->provider = $provider;

        return WPStaging::make($authClass);
    }

    private function jsonResponse($message = '', $success = false, $warning = false)
    {
        wp_send_json([
            'success' => $success,
            'warning' => $warning,
            'message' => $message
        ]);
    }
}
