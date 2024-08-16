<?php

/**
 * The command that will handle the creation of a Backup from the command line.
 *
 * @package WPStaging\Pro\WpCli\Commands
 */

namespace WPStaging\Pro\WpCli\Commands;

use WP_CLI;
use WPStaging\Core\WPStaging;
use WPStaging\Pro\Backup\BackgroundProcessing\Export\PrepareExport;

/**
 * Class BackupCreateCommand
 *
 * @package WPStaging\Pro\WpCli\Commands
 */
class BackupCreateCommand implements CommandInterface
{
    /**
     * Creates the Backup using the Background Processing system.
     *
     * @param array               $args      A list of the positional arguments provided by the user, already validated.
     * @param array<string,mixed> $assocArgs A map of the associative arguments, options and flags, provided by the
     *                                       user.
     * @return void The method will not return any value and will communicate the result to the user by means of an exit
     *                                       status.
     *
     * @throws WP_CLI\ExitException If the Backup preparation fails, then a message will be provided to the user
     *                              detailing the reason.
     */
    public function __invoke(array $args = [], array $assocArgs = [])
    {
        $data = [];
        try {
            $jobId = WPStaging::make(PrepareExport::class)->prepare($data);

            if ($jobId instanceof \WP_Error) {
                WP_CLI::error('Failed to create Backup: ' . $jobId->get_error_message());
            }

            $quiet = isset($assocArgs['quiet']);
            if (!$quiet) {
                WP_CLI::success(
                    sprintf(
                        "Backup prepared with Job ID %s\nUse the \"%s\" command to check its status.",
                        $jobId,
                        "wp wpstg backup-status '$jobId'"
                    )
                );
            } else {
                WP_CLI::line($jobId);
            }
        } catch (\Exception $e) {
            WP_CLI::error('Exception thrown while preparing the Backup: ' . $e->getMessage());
        }
    }
}
