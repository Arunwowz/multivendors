<?php
/**
 * @var string $cloneID The ID of the clone.
 * @var array  $data    An array of Clone data.
 * @var $license
 *
 * @see src/Backend/views/clone/ajax/single-overview.php:62
 */
?>
<?php
/*
 * Hey there! This is friendly reminder that overriding, bypassing, or modifying
 * the license check code is a copyright infringement liable to legal actions.
 *
 * If you need help with your license, please feel free to contact us to normalize it:
 *
 * @link https://wp-staging.com/support/ The link to renew your license.
 *
 * @link https://www.copyright.gov/title17/92chap5.html The link to U.S copyright law information.
 * @link https://europa.eu/youreurope/business/running-business/intellectual-property/copyright/index_en.htm The link to EU copyright law information.
 */
if (isset($license->license) && $license->license === 'valid' || (isset($license->error) && $license->error === 'expired') || wpstg_is_local()) :
    ?>
<a href="#" class="wpstg-push-changes wpstg-merge-clone wpstg-clone-action"
   data-clone="<?php echo esc_attr($cloneID); ?>"
   title="<?php echo esc_html__("Push and overwrite current production website with the selected staging site. Select specific folders and database tables in the next step.", "wp-staging"); ?>">
    <?php esc_html_e("Push Changes", "wp-staging") ?>
</a>
<?php endif; ?>
