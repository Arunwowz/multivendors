<?php

/**
 * Provides information about the plugin integration with wp-cli.
 *
 * @package WPStaging\Pro\WpCli\Commands
 */

namespace WPStaging\Pro\WpCli\Commands;

use WP_CLI\ExitException;
use WPStaging\Core\WPStaging;

/**
 * Class Dispatcher
 *
 * @package WPStaging\Pro\WpCli\Commands
 */
class Dispatcher
{
    /**
     * Whether the dispatcher did set up or not.
     *
     * @var bool
     */
    protected static $setUp = false;

    /**
     * {}
     */
    public static function registrationArgs()
    {
        return [
            'shortdesc' => 'Manages WP STAGING | PRO cloning and pushing operations.'
        ];
    }

    /**
     * Creates a site backup.
     *
     * ## OPTIONS
     *
     * @subcommand  backup-create
     * @throws ExitException
     */
    public function backupCreate(array $args = [], array $assocArgs = [])
    {
        /** @var BackupCreateCommand $command */
        $command = static::getSubcommand('backup-create');
        return $command($args, $assocArgs);
    }

    /**
     * Checks the status of a site backup.
     *
     * ## OPTIONS
     *
     * <jobId>
     * : The Identifier of the Backup Job.
     *
     * ## EXAMPLES
     *
     *     wp wpstg backup:status --jobId single.wp-staging.local_6110e7d6af6666.39978425
     *
     * @subcommand  backup-status
     * @throws ExitException
     */
    public function backupStatus(array $args = [], array $assocArgs = [])
    {
        /** @var BackupStatusCommand $command */
        $command = static::getSubcommand('backup-status');
        return $command($args, $assocArgs);
    }

    /**
     * Returns the sub-command mapped to the sub-command slug.
     *
     * @param string $subCommand The sub-command slug; e.g. `create`.
     *
     * @return CommandInterface A command instance reference.
     */
    public static function getSubcommand($subCommand)
    {
        $subCommandMap = self::getSubCommandMap();

        $commandClass = isset($subCommandMap[$subCommand]) ? $subCommandMap[$subCommand] : static::class;

        if ($commandClass === false) {
            // If we're here, and the sub-command is not mapped, then this is a developer error: report it.
            throw new \LogicException("No command class is mapped to the {$subCommand} sub-command!");
        }

        if (!class_implements($commandClass, CommandInterface::class)) {
            throw new \LogicException(
                "The class {$commandClass} MUST implement the " . CommandInterface::class . ' interface.'
            );
        }

        return WPStaging::make($commandClass);
    }

    /**
     * Returns the map from the sub-command slugs to the class implementing them.
     *
     *
     * @return array<string,string> A map from the sub-command slugs to the classes implementing them.
     */
    protected static function getSubCommandMap()
    {
        $subCommandMap = [
            'backup-create' => BackupCreateCommand::class,
            'backup-status' => BackupStatusCommand::class
        ];

        /**
         * Allows filtering the map from command slugs to the classes implementing them.
         *
         * @param array<string,string> $subCommandMap A map from the sub-command slugs to
         *                                            the classes implementing them.
         */
        return apply_filters('wpstg_wpcli_subcommand_map', $subCommandMap);
    }
}
