<?php

/**
 * @see \WPStaging\Backend\Pro\Notices\Notices::getWPVersionCompatibleNotice;
 */

use WPStaging\Framework\Facades\Escape;

?>

<div class="notice notice-warning">
    <p>
        <?php
        /* translators: %s: Currently installed WordPress version */
        echo sprintf(
            Escape::escapeHtml(__(
                <<<'HTML'
<p><strong>This version of WP STAGING | PRO has not been tested with WordPress version %1$s.</strong></p>
<p>You can continue to use WP STAGING, but we recommend you wait until our quality assurance team finishes the compatibility audit that we perform on all new WordPress releases. Then you will have a smooth, reliable, and professional experience with WP STAGING, always.</p>
<p>You can expect an update to WP STAGING | PRO usually after a few days after release of a new WordPress version.</p>
<p><strong>Do you want to get news about every new release? </strong><a href="https://twitter.com/wpstg" target="_blank"> Follow us on Twitter for Updates</a> | <a href="https://wp-staging.com/wp-staging-pro-changelog/" target="_blank"> Read the Changelog</a> | <a href="https://wp-staging.com/#newsletter-section" target="_blank"> Subscribe to our mailing list</a></p>
HTML
                ,
                'wp-staging'
            )),
            esc_html(get_bloginfo('version'))
        )
        ?>
    </p>
</div>
