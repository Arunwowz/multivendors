<?php

/**
 * @var \WPStaging\Backend\Pro\Modules\Jobs\Scan $scan
 * @var stdClass                                 $options
 *
 * @see \WPStaging\Backend\Administrator::ajaxPushScan
 */

use WPStaging\Framework\Facades\Escape;

$isNetworkClone = $scan->isNetworkClone();
$showAll  = false;
$selected = false;
?>

<div class="wpstg-tabs-wrapper">
    <a href="#" class="wpstg-tab-header active" data-id="#wpstg-scanning-db" style="display:block;">
        <span class="wpstg-tab-triangle"></span>
        <?php echo esc_html__("Database Tables", "wp-staging") ?>
        <span id="wpstg-tables-count" class="wpstg-selection-preview"></span>
    </a>

    <div class="wpstg-tab-section" id="wpstg-scanning-db">
        <?php do_action("wpstg_scanning_db") ?>
        <h4 style="margin:0">
            <?php echo esc_html__("Select tables to push to production website", "wp-staging");
            ?>
        </h4>
        <p>
            <?php echo Escape::escapeHtml(__("<strong class='wpstg--red'>Note:</strong> Your database table selection is automatically saved<br/>and used as default selection for the next push.", "wp-staging")); ?>
        </p>
        <p>
                <?php
                $db = empty($options->databaseDatabase) ? DB_NAME : $options->databaseDatabase;
                echo '<strong>' . esc_html__("Database Name: ", "wp-staging") . '</strong>' . esc_html($db);
                echo '<br/>';
                echo '<strong>' . esc_html__("Table Prefix: ", "wp-staging")  . '</strong>' . esc_html($options->prefix);
                ?>
        </p>
        <div>
            <a href="javascript:;" class="wpstg-button-unselect button" style="margin-bottom:10px;"> <?php esc_html_e('Unselect All', 'wp-staging'); ?> </a>
            <a href="javascript:;" class="wpstg-button-db-prefix button" style="margin-bottom:10px;"> <?php echo esc_html($options->prefix); ?> </a>
            <span class="wpstg--tooltip wpstg--tooltip-normal">
                <a href="javascript:;" class="wpstg-button-show-tables button" style="margin-bottom:10px;"> <?php esc_html_e('Show All Tables', 'wp-staging'); ?> </a>
                <span class="wpstg--tooltiptext">
                    <?php esc_html_e('Show all custom and extra tables existing in the staging sites database, even those belonging to other sites.', 'wp-staging'); ?>
                </span>
            </span>
        </div>
        <select
            id="wpstg_select_tables_pushing"
            data-clone="<?php echo $options->current; ?>"
            data-prefix="<?php echo $options->prefix; ?>"
            data-network="<?php echo $isNetworkClone ? 'true' : 'false'; ?>"
            multiple="multiple">
            <?php require 'selections/tables.php'; ?>
        </select>
        <div>
            <a href="javascript:;" class="wpstg-button-unselect button" style="margin-top:10px; margin-bottom: 10px;"> <?php esc_html_e('Unselect All', 'wp-staging'); ?> </a>
            <a href="javascript:;" class="wpstg-button-db-prefix button" style="margin-top:10px; margin-bottom:10px;"> <?php echo $options->prefix; ?> </a>
            <span class="wpstg--tooltip wpstg--tooltip-normal">
                <a href="javascript:;" class="wpstg-button-show-tables button" style="margin-top:10px; margin-bottom:10px;"> <?php esc_html_e('Show All Tables', 'wp-staging'); ?> </a>
                <span class="wpstg--tooltiptext">
                    <?php esc_html_e('Show all custom and extra tables existing in the staging sites database, even those belonging to other sites.', 'wp-staging'); ?>
                </span>
            </span>
        </div>
    </div>

    <a href="#" class="wpstg-tab-header" data-id="#wpstg-scanning-files">
        <span class="wpstg-tab-triangle"></span>
        <?php echo esc_html__("Select Files", "wp-staging") ?>
        <span id="wpstg-files-count" class="wpstg-selection-preview"></span>
    </a>

    <div class="wpstg-tab-section" id="wpstg-scanning-files">
        <h4>
            <?php echo esc_html__("Select plugins, themes & uploads folder to push to production website.", "wp-staging") ?>
        </h4>

        <p>
            <?php echo Escape::escapeHtml(__("<strong class='wpstg--red'>Note:</strong> Your folder selection is automatically saved <br/>and used as the default selection for the next push.", "wp-staging")); ?>
        </p>
        <?php echo $scan->directoryListing() // phpcs:ignore ?>

        <h4 style="margin:10px 0 10px 0">
            <?php echo esc_html__("Extra Directories to Copy", "wp-staging") ?>
        </h4>

        <textarea id="wpstg_extraDirectories" name="wpstg_extraDirectories" style="width:100%;height:100px;"></textarea>
        <p>
            <span>
                <?php
                echo sprintf(esc_html__(
                    "Enter one directory path per line. %s" .
                    "Directory must start with absolute path: %s",
                    "wp-staging"
                ), '<br />', esc_html($options->root . $options->cloneDirectoryName))
                ?>
            </span>
        </p>

        <p>
            <span>
                <?php
                if (isset($options->clone)) {
                    echo esc_html__("Plugins will be pushed to: ", "wp-staging") . esc_html($options->root . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins');
                    echo '<br>';
                    echo esc_html__("Themes will be pushed to: ", "wp-staging") . esc_html($options->root . 'wp-content' . DIRECTORY_SEPARATOR . 'themes');
                    echo '<br>';
                    echo esc_html__("Medias will be pushed to: ", "wp-staging") . esc_html($options->root . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads');
                }
                ?>
            </span>
        </p>
    </div>
    <p><label>
        <input type="checkbox" id="wpstg-remove-uninstalled-plugins-themes" name="wpstg-remove-uninstalled-plugins-themes">
        <?php echo esc_html__("Uninstall all plugins/themes on production site that are not installed on staging site.", "wp-staging"); ?>
    </label></p>
    <p><label> <?php echo ($options->uploadsSymlinked ? "<b>" . esc_html__("Note: This option is disabled as uploads dir was symlinked", "wp-staging") . "</b><br/>" : '') ?>
        <input type="checkbox" id="wpstg-delete-upload-before-pushing" name="wpstg-delete-upload-before-pushing" <?php echo $options->uploadsSymlinked ? 'disabled' : '' ?>>
        <?php echo esc_html__("Delete wp-content/uploads folder on production site including all images before starting copy process.", "wp-staging"); ?>
    </label></p>
    <p id="wpstg-backup-upload-container" style="display: none;"><label>
        <input type="checkbox" id="wpstg-backup-upload-before-pushing" name="wpstg-backup-upload-before-pushing" <?php echo $options->uploadsSymlinked ? 'disabled' : '' ?>>
        <?php echo esc_html__("Create a backup of folder wp-content/uploads before deleting it."
            . " Helpful in case the push process fails."
            . " Make sure you have enough space for the uploads folder backup on your hosting otherwise the process will fail."
            . " Backup will be written to wp-content/uploads.wpstg_backup."
            . " If there is already a backup at wp-content/uploads.wpstg_backup then it will be replaced with the new one."
            . " This backup will automatically be deleted after 7 days.", "wp-staging"); ?>
    </label></p>
    <p><label>
            <?php if (is_multisite() && !is_main_site()) : ?>
            <input type="checkbox" id="wpstg-create-backup-before-pushing" name="wpstg-create-backup-before-pushing" disabled>
                <?php echo esc_html__("Create database backup (Not supported in subsites for now, coming soon!)", "wp-staging"); ?>
            <?php else : ?>
                <input type="checkbox" id="wpstg-create-backup-before-pushing" name="wpstg-create-backup-before-pushing"
                       checked>
                <?php echo esc_html__("Create database backup", "wp-staging"); ?>
            <?php endif; ?>
        </label>
    </p>
</div>

<div class="wpstg-progress-bar-wrapper" style="display: none;">
    <h2 id="wpstg-processing-header"><?php echo esc_html__("Processing, please wait...", "wp-staging") ?></h2>
    <div class="wpstg-progress-bar">
        <div class="wpstg-progress" id="wpstg-progress-backup"></div>
        <div class="wpstg-progress wpstg-pro" id="wpstg-progress-files"></div>
        <div class="wpstg-progress" id="wpstg-progress-data"></div>
        <div class="wpstg-progress" id="wpstg-progress-finishing"></div>
    </div>
    <div class="wpstg-clear-both">
        <div id="wpstg-processing-status"></div>
        <div id="wpstg-processing-timer"></div>
    </div>
    <div class="wpstg-clear-both"></div>
</div>

<div id="wpstg-error-wrapper">
    <div id="wpstg-error-details"></div>
</div>

<button type="button" id="wpstg-cancel-pushing" class="wpstg-button--primary wpstg-button--red" style="display: none;">
    <?php echo esc_html__("Cancel", "wp-staging")?>
</button>

<div class="wpstg-log-details" style="display: none;"></div>
<p></p>
<button type="button" class="wpstg-prev-step-link wpstg-button--primary">
    <?php esc_html_e("Back", "wp-staging") ?>
</button>

<button type="button" id="wpstg-push-changes" class="wpstg-next-step-link-pro wpstg-button--primary wpstg-button--blue"
        data-action="wpstg_push_changes" data-clone="<?php echo esc_attr($options->current); ?>" data-clone-name="<?php echo esc_attr($options->cloneName); ?>">
    <?php
    echo esc_html__('Push Staging Site to Live Site', 'wp-staging');
    ?>
</button>
<p></p>
