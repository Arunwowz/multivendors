<?php

/**
 * @see \WPStaging\Backend\Pro\Notices\Notices::getLicenseKeyInvalidNotice
 */

use WPStaging\Framework\Facades\Escape;

?>
<div class="notice notice-error">
    <p>
        <?php
        echo sprintf(
            Escape::escapeHtml(
                __('<strong>Your license key of WP STAGING | PRO is invalid or deactivated.</strong><br>You need a valid license key to use the backup & push feature and to get further updates. Updates are important to make sure that your version of WP STAGING is compatible with your version of WordPress and to prevent any data loss while using WP STAGING|PRO.' .
                '<br><br><a href="%2$s" target="_self"><strong>Enter License Key</strong></a>  |  <a href="%1$s" target="_blank"><strong>Buy license key</strong></a>.', 'wp-staging')
            ),
            'https://wp-staging.com/?utm_source=admin_notice&utm_medium=plugin&utm_campaign=license_invalid',
            esc_url(admin_url() . 'admin.php?page=wpstg-license'),
            'your account'
        );
        ?>
    </p>
</div>
