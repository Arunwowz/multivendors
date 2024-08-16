<?php

/**
 * @see WPStaging\Backend\Administrator::getLicensePage
 *
 * @var object $license
 */

$message = '';
?>
<div class="wpstg_admin">
    <?php require_once(WPSTG_PLUGIN_DIR . 'Backend/views/_main/header.php'); ?>
    <form method="post" action="#">
        <?php if (isset($license->license) && $license->license === 'valid') : ?>
            <label style='display:block;margin-bottom: 5px;margin-top:10px;'><?php echo esc_html__('WP STAGING | PRO is activated.', 'wp-staging'); ?></label>
            <input type="hidden" name="wpstg_deactivate_license" value="1">
            <input type="submit" class="button" value="<?php esc_attr_e('Deactivate License', 'wp-staging'); ?>">
            <?php
            $message = __('You\'ll get updates and support until ', 'wp-staging') . date_i18n(get_option('date_format'), strtotime($license->expires, current_time('timestamp')));
            $message .= '<p><a href="' . esc_url(admin_url()) . 'admin.php?page=wpstg_clone" id="wpstg-new-clone" class="wpstg-next-step-link wpstg-link-btn button-primary">' . __("Start using WP STAGING", "wp-staging") . '</a>';
            ?>
        <?php else : ?>
            <label for="wpstg_license_key" style='display:block;margin-bottom: 5px;margin-top:10px;'><?php echo sprintf(esc_html__('Enter your license key to activate WP STAGING | PRO. %s You can buy a license key on %s.', 'wp-staging'), '<br>', '<a href="https://wp-staging.com?utm_source=wpstg-license-ui&utm_medium=website&utm_campaign=enter-license-key&utm_id=purchase-key&utm_content=wpstaging" target="_blank">wp-staging.com</a>'); ?></label>            
            <input type="text" name="wpstg_license_key" style="width:260px;" value='<?php echo esc_attr(get_option('wpstg_license_key', '')); ?>'>
            <input type="hidden" name="wpstg_activate_license" value="1">
            <input type="submit" class="wpstg-button wpstg-blue-primary" value="<?php esc_attr_e('Activate License', 'wp-staging'); ?>">
        <?php endif; ?>
        <?php
        if (isset($license->error) && $license->error === 'expired') {
            $message =  '<span class="wpstg--red">' . __('Your license expired on ', 'wp-staging') . date_i18n(get_option('date_format'), strtotime($license->expires, current_time('timestamp'))) . '</span>';
        }
        wp_nonce_field('wpstg_license_nonce', 'wpstg_license_nonce');
        ?>
    </form>
        <?php echo '<div style="padding:3px;">' . wp_kses_post($message) . '</div>'; ?>
</div>
