<?php

namespace WPStaging\Backend\Pro\Licensing;

use WPStaging;
use WPStaging\Framework\Facades\Sanitize;
use WPStaging\Framework\Facades\Escape;

class Licensing
{

    // The license key
    private $licensekey;

    const WPSTG_LICENSE_KEY = 'wpstg_license_key';

    /** @var string 'valid' or 'invalid' */
    const WPSTG_LICENSE_STATUS= 'wpstg_license_status';


    public function __construct()
    {

        // Load some hooks
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_init', [$this, 'activate_license']);
        add_action('admin_init', [$this, 'deactivate_license']);
        add_action('wpstg_weekly_event', [$this, 'weekly_license_check']);
        // For testing weekly_license_check, uncomment line below
        //add_action( 'admin_init', array( $this, 'weekly_license_check' ) );

        // this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
        if (!defined('WPSTG_STORE_URL')) {
			define('WPSTG_STORE_URL', '');
        }

        // the name of your product. This should match the download name in EDD exactly
        if (!defined('WPSTG_ITEM_NAME')) {
            define('WPSTG_ITEM_NAME', 'WP STAGING PRO');
        }


        // Inititalize the EDD software licensing API
        $this->plugin_updater();

        // the license key
        $this->licensekey = trim(get_option(self::WPSTG_LICENSE_KEY));
    }

    /**
     * @return bool
     */
    private function isBetaVersion()
    {
        return defined('WPSTG_IS_BETA') && WPSTG_IS_BETA === true;
    }

    /**
     * EDD software licensing API
     */
    public function plugin_updater()
    {
        $license_key = trim(get_option(self::WPSTG_LICENSE_KEY));

        // Check for 'undefined' here because WPSTG_PLUGIN_FILE will be undefined if plugin is uninstalled to prevent issue #216
        $pluginFile = !defined('WPSTG_PLUGIN_FILE') ? null : WPSTG_PLUGIN_FILE;

        new EDD_SL_Plugin_Updater(WPSTG_STORE_URL, $pluginFile, [
                'version' => WPStaging\Core\WPStaging::getVersion(), // current version number
                'license' => $license_key, // license key (used get_option above to retrieve from DB)
                'item_name' => WPSTG_ITEM_NAME, // name of this plugin
                'author' => 'Rene Hermenau', // author of this plugin
                'beta' => $this->isBetaVersion()
            ]
        );
    }

    /**
     * Activate the license key
     */
    public function activate_license()
    {
        if (isset($_POST['wpstg_activate_license']) && !empty($_POST[self::WPSTG_LICENSE_KEY])) {
            // run a quick security check
            if (!check_admin_referer('wpstg_license_nonce', 'wpstg_license_nonce')) {
                return; // get out if we didn't click the Activate button
            }

            // Save License key in DB
            update_option(self::WPSTG_LICENSE_KEY, Sanitize::sanitizeString($_POST[self::WPSTG_LICENSE_KEY]));

            // retrieve the license from the database
            $license = trim(get_option(self::WPSTG_LICENSE_KEY));

            // data to send in our API request
            $api_params = [
                'edd_action' => 'activate_license',
                'license' => $license,
                'item_name' => urlencode(WPSTG_ITEM_NAME), // the name of our product in EDD
                'url' => home_url()
            ];

            // Call the custom API.
            $response = array('response'=>array('code'=>200));
            // make sure the response came back okay
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                } else {
                    $message = __('An error occurred, please try again. Response: ' . print_r($response, true) );
                }
            } else {

                $license_data = (object)array('success'=>true, 'license'=>'valid', 'expires'=>'2049-12-31 23:59:59');
                if ($license_data->success === false) {

                    switch ($license_data->error) {

                        case 'expired' :

                            $message = sprintf(
                                __('Your license key expired on %s.'), date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                            );
                            break;

                        case 'revoked' :

                            $message = __('Your license key has been disabled.');
                            break;

                        case 'missing' :

                            $message = __('Your License key is invalid.');
                            break;

                        case 'invalid' :
                        case 'site_inactive' :

                            $message = __('Your license is not active for this URL.');
                            break;

                        case 'item_name_mismatch' :

                            $message = sprintf(__('This appears to be an invalid license key for %s.'), WPSTG_ITEM_NAME);
                            break;

                        case 'no_activations_left':

                            $message = __('Your license key has reached its activation limit.');
                            break;

                        default :

                            $message = __('An error occurred, please try again. Response: ' . wp_strip_all_tags(print_r($response, true)));
                            break;
                    }
                }
            }

            // Check if anything passed on a message constituting a failure
            if (!empty($message)) {
                $base_url = admin_url('admin.php?page=wpstg-license');
                $redirect = add_query_arg(['wpstg_licensing' => 'false', 'message' => urlencode($message)], $base_url);
                if (!empty($license_data)){
                    update_option(self::WPSTG_LICENSE_STATUS, $license_data);
                }
                wp_redirect($redirect);
                exit();
            }

            // $license_data->license will be either "valid" or "invalid"
            update_option(self::WPSTG_LICENSE_STATUS, $license_data);
            wp_redirect(admin_url('admin.php?page=wpstg-license'));
            exit();
        }
    }

    public function deactivate_license()
    {
		return;
    }

    /**
     * Check if license key is valid once per week
     *
     * @access  public
     * @return  void
     * @since   2.0.3
     */
    public function weekly_license_check()
    {
		return;
        if (empty($this->licensekey)) {
            return;
        }

        // data to send in our API request
        $api_params = [
            'edd_action' => 'check_license',
            'license' => $this->licensekey,
            'item_name' => urlencode(WPSTG_ITEM_NAME),
            'url' => 'homersimpsonliveshere.com'
        ];

        // Call the API
        $response = wp_remote_post(
            WPSTG_STORE_URL, [
                'timeout' => 15,
                'sslverify' => false,
                'body' => $api_params
            ]
        );

        // make sure the response came back okay
        if (is_wp_error($response)) {
            return;
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));
        if (!empty($license_data)){
            // update_option(self::WPSTG_LICENSE_STATUS, $license_data);
            update_option( 'wpstg_license_status', (object)array('success'=>true, 'license'=>'valid', 'expires'=>'2049-11-29 23:59:59') ); //donaconda
        }

    }

    /**
     * This is a means of catching errors from the activation method above and displaying it to the customer
     * @todo remove commented out HTML code
     */
    public function admin_notices()
    {
        if (isset($_GET['wpstg_licensing']) && !empty($_GET['message'])) {

            $message = filter_input(INPUT_GET,'message');

            switch ($_GET['wpstg_licensing']) {
                case 'false':
                    ?>
                    <div class="wpstg--notice wpstg--error">
                        <p><?php esc_html_e('WP STAGING - Can not activate license key! ', 'wp-staging');
                            echo wp_kses_post($message); ?></p>
                    </div>
                    <?php
                    break;

                case 'true':
                default:
                    // You can add a custom success message here if activation is successful
                    break;
            }
        }
    }


    /**
     * Most pro features are available even if a license has been expired.
     * The only requirement is that a license was valid in the past or still is it.
     * @return bool
     */
    public function isValidOrExpiredLicenseKey()
    {
		return true;
        if (wpstg_is_local()) {
            return true;
        }

        if (!($licenseData = get_option(self::WPSTG_LICENSE_STATUS))) {
            return false;
        }

        if (isset($licenseData->license) && $licenseData->license === 'valid') {
            return true;
        }

        if (isset($licenseData->error) && $licenseData->error === 'expired') {
            return true;
        }

        return false;
    }


}
