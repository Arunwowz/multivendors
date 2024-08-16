<?php

namespace WPStaging\Pro\Backup\Ajax;

use WPStaging\Core\Cron\Cron;
use WPStaging\Framework\Security\Capabilities;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\FinishBackupTask;
use WPStaging\Framework\Utils\Times;

class ScheduleList
{
    /** @var Times */
    private $times;

    private $backupScheduler;

    public function __construct(BackupScheduler $backupScheduler)
    {
        $this->backupScheduler = $backupScheduler;

        $this->times = new Times();
    }

    /**
     * Rendered via AJAX.
     *
     * @throws \Exception
     */
    public function renderScheduleList()
    {
        if (!current_user_can((new Capabilities())->manageWPSTG())) {
            return;
        }

        $schedules = $this->backupScheduler->getSchedules();

        if (empty($schedules)) {
            wp_send_json_success('<p class="wpstg-backup-no-schedules-list">' . esc_html__('You don\'t have a backup plan yet. Create a new backup and choose a recurrent backup time to start.', 'wp-staging') . '</p>');
        }

        $scheduleHtml = ob_start();
        ?>
        <table>
            <thead>
            <tr>
                <td><?php esc_html_e('Time', 'wp-staging'); ?></td>
                <td><?php esc_html_e('Backups', 'wp-staging'); ?></td>
                <td><?php esc_html_e('Backup Content', 'wp-staging'); ?></td>
                <td></td>
                <td></td>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($schedules as $schedule) :
                $hourAndMinute = new \DateTime('now', wp_timezone());
                $hourAndMinute->setTime($schedule['time'][0], $schedule['time'][1]);
                ?>
                <tr>
                    <td><?php echo esc_html(Cron::getCronDisplayName($schedule['schedule'])); ?><?php esc_html_e(' at ', 'wp-staging') ?><?php echo $hourAndMinute->format(get_option('time_format')); ?></td>
                    <td><?php esc_html_e(sprintf('Keep last %d backup%s', $schedule['rotation'], ($schedule['rotation'] > 1 ? 's' : '')), 'wp-staging'); ?></td>
                    <td>
                        <?php
                        $isExportingDatabase = $schedule['isExportingDatabase'];
                        $isExportingPlugins = $schedule['isExportingPlugins'];
                        $isExportingMuPlugins = $schedule['isExportingMuPlugins'];
                        $isExportingThemes = $schedule['isExportingThemes'];
                        $isExportingUploads = $schedule['isExportingUploads'];
                        $isExportingOtherWpContentFiles = $schedule['isExportingOtherWpContentFiles'];
                        include(trailingslashit(WPSTG_PLUGIN_DIR) . 'Backend/views/backup/modal/partials/backup-contains.php');
                        ?>
                    </td>
                    <td>
                        <div class="wpstg--tooltip wpstg--edit-backup-schedule" data-schedule-id="<?php echo esc_attr($schedule['scheduleId']); ?>">
                            <button id="wpstg--edit-schedule" class="wpstg--edit--button"  data-schedule-id="<?php echo esc_attr($schedule['scheduleId']); ?>"><?php esc_html_e('EDIT', 'wp-staging'); ?></button>
                            <div class='wpstg--tooltiptext'><?php esc_html_e('Edit this backup plan.', 'wp-staging'); ?></div>
                        </div>
                    </td>
                    <td>
                        <div class="wpstg--tooltip wpstg--dismiss-schedule" data-schedule-id="<?php echo esc_attr($schedule['scheduleId']); ?>">
                            <img class="wpstg--dashicons wpstg--delete--schedule--icon" src="<?php echo esc_url(trailingslashit(WPSTG_PLUGIN_URL)) . 'assets/'; ?>svg/vendor/dashicons/dismiss.svg" alt="" data-schedule-id="<?php echo esc_attr($schedule['scheduleId']); ?>">
                            <div class='wpstg--tooltiptext'><?php esc_html_e('Delete this schedule and stop creating new backups. This does not delete any backup files.', 'wp-staging'); ?></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        wp_send_json_success(ob_get_clean());
    }

    public function renderNextBackupSnippet()
    {
        if (!current_user_can((new Capabilities())->manageWPSTG())) {
            return '';
        }
        ?>
        <ul>
            <?php
            $lastRun = get_option(FinishBackupTask::OPTION_LAST_BACKUP);

            if (is_array($lastRun)) :
                $lastRunTime = $this->times->getHumanTimeDiff($lastRun['endTime'], time());
                $lastRunDuration = str_replace(['minutes', 'seconds'], ['min', 'sec'], $this->times->getHumanReadableDuration(gmdate('i:s', $lastRun['duration'])));
                ?>
            <li>
                <?php echo sprintf(
                    '<strong>%s:</strong> %s %s (%s %s)',
                    esc_html__('Last Backup', 'wp-staging'),
                    esc_html($lastRunTime),
                    esc_html__('ago', 'wp-staging'),
                    esc_html__('Duration', 'wp-staging'),
                    esc_html($lastRunDuration)
                ); ?>
            </li>
                <?php
            endif;

            try {
                list($nextBackupTimestamp, $nextBackupCallback) = $this->backupScheduler->getNextBackupSchedule();
            } catch (\Exception $e) {
                $nextBackupTimestamp = null;
                $nextBackupCallback = null;
            }

            if (!is_null($nextBackupTimestamp)) :
                $nextBackupTimeHumanReadable = $this->times->getHumanTimeDiff(time(), $nextBackupTimestamp);
                ?>
            <li>
                <?php echo sprintf(
                    '<strong>%s:</strong> %s %s',
                    esc_html__('Next backup', 'wp-staging'),
                    esc_html__('start in', 'wp-staging'),
                    esc_html($nextBackupTimeHumanReadable)
                ); ?>
            </li>
        </ul>
                <?php
            endif;
    }
}
