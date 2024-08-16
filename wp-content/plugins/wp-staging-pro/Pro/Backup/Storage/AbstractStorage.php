<?php

namespace WPStaging\Pro\Backup\Storage;

use Exception;
use WPStaging\Vendor\GuzzleHttp\Client as GuzzleClient;

use function WPStaging\functions\debug_log;

abstract class AbstractStorage
{
    protected $identifier;

    protected $label;

    /** @var string */
    protected $error;

    abstract public function authenticate();

    abstract public function testConnection();

    abstract public function revoke();

    abstract public function cleanBackups();

    abstract public function getFiles();

    abstract public function updateSettings($settings);
    /**
     * Check if the storage is authenticated or not
     *
     * @return boolean true if the storage is authenticated, otherwise returns false
     * @access public
     */
    public function isAuthenticated()
    {
        $options = $this->getOptions();
        if (isset($options['isAuthenticated'])) {
            return $options['isAuthenticated'];
        }
        return false;
    }

    public function getOptions()
    {
        return get_option($this->getOptionName(), []);
    }

    private function getOptionName()
    {
        return 'wpstg_' . $this->identifier;
    }

    /**
     * Save options
     * Save the storage configuration.
     *
     * @param $options
     *
     * @return bool
     * @access public
     */
    public function saveOptions($options = [])
    {
        return update_option($this->getOptionName(), $options, false);
    }

    /**
     * Display storage success message
     *
     * @return void
     */
    public function showAuthenticateSuccessFailureMessage()
    {

        if (empty($_GET['auth-storage'])) {
            return;
        }
        $userDataToDisplay = '';
        if ($this->label === 'Google Drive' && isset($_GET['userDataToDisplay'])) {
            $userDataToDisplay = filter_input(INPUT_GET, 'userDataToDisplay');
        }

        switch ($_GET['auth-storage']) {
            case 'true':
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php printf(esc_html__('The %s storage is authenticated successfully! %s', 'wp-staging'), $this->label, esc_html($userDataToDisplay)); ?>
                    </p>
                </div>
                <?php
                break;
            case 'false':
                ?>
                <div class="wpstg--notice wpstg--error is-dismissible">
                    <p>
                        <?php printf(esc_html__('The %s storage authentication failed!', 'wp-staging'), $this->label); ?>
                    </p>
                </div>
                <?php
                break;
        }
    }

    /**
     * Whether Guzzle available to work
     *
     * @return bool
     */
    public function isGuzzleAvailable()
    {
        try {
            $http = new GuzzleClient([
                "verify" => WPSTG_PLUGIN_DIR . 'Pro/Backup/cacert.pem'
            ]);
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return false;
        }

        return true;
    }

    /** @return string */
    public function getError()
    {
        return $this->error;
    }
}
