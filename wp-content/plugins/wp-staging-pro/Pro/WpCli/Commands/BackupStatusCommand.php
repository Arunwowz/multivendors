<?php

/**
 * Allows checking on the status of a Backup prepared for background processing.
 *
 * @package WPStaging\Pro\WpCli\Commands
 */

namespace WPStaging\Pro\WpCli\Commands;

use WPStaging\Core\WPStaging;
use WPStaging\Framework\BackgroundProcessing\Action;
use WPStaging\Framework\BackgroundProcessing\Queue;
use WP_CLI;
use WPStaging\Pro\Backup\Dto\Task\Export\Response\FinalizeExportResponseDto;
use WPStaging\Pro\Backup\Dto\TaskResponseDto;

/**
 * Class BackupStatusCommand
 *
 * @package WPStaging\Pro\WpCli\Commands
 */
class BackupStatusCommand implements CommandInterface
{

    /**
     * Checks the status of a Backup that is being created using the Background Processing system.
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
        $queue = WPStaging::make(Queue::class);
        $jobId = (string)$args[0];
        $action = $queue->getLatestUpdatedAction($jobId);

        if (!$action instanceof Action) {
            WP_CLI::error('Could not find any Action associated to the Job ID.');
        }

        if (!class_exists(FinalizeExportResponseDto::class)) {
            WP_CLI::error('Finalize Response DTO class does not exist.');
        }

        $dto = unserialize($action->custom);

        if (!$dto instanceof TaskResponseDto) {
            WP_CLI::error('Could not find any DTO associated to latest Action for the Job ID ' . $jobId . ' Dto: ' . $action->custom);
        }

        $dtoData = $dto->toArray();
        WP_CLI::print_value(json_encode($dtoData, JSON_PRETTY_PRINT));
    }
}
