<?php

/**
 * Prepares a Backup (Export) to be executed using Background Processing.
 *
 * @package WPStaging\Pro\Backup\BackgroundProcessing\Export
 */

namespace WPStaging\Pro\Backup\BackgroundProcessing\Export;

use WPStaging\Core\WPStaging;
use WPStaging\Framework\BackgroundProcessing\Action;
use WPStaging\Framework\BackgroundProcessing\Exceptions\QueueException;
use WPStaging\Framework\BackgroundProcessing\Queue;
use WPStaging\Framework\BackgroundProcessing\QueueActionAware;
use WPStaging\Framework\Traits\ResourceTrait;
use WPStaging\Pro\Backup\Ajax\Export\PrepareExport as AjaxPrepareExport;
use Exception;
use WP_Error;
use WPStaging\Pro\Backup\BackupProcessLock;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Dto\TaskResponseDto;
use WPStaging\Pro\Backup\Exceptions\ProcessLockedException;
use WPStaging\Pro\Backup\Job\Jobs\JobExport;
use WPStaging\Vendor\Psr\Log\LogLevel;

use function WPStaging\functions\debug_log;

/**
 * Class PrepareExport
 *
 * @package WPStaging\Pro\Backup\BackgroundProcessing\Export
 */
class PrepareExport
{
    use ResourceTrait;
    use QueueActionAware;

    /**
     * A reference to the class that handles Backup processing when triggered by AJAX actions.
     *
     * @var AjaxPrepareExport
     */
    private $ajaxPrepareExport;

    /** @var JobExport */
    private $jobExport;

    /**
     * A reference to the instance of the Queue manager the class should use for processing.
     *
     * @var Queue
     */
    private $queue;

    private $processLock;

    /**
     * The ID of the Action last inserted in the Queue by this class.
     *
     * Note: the Action ID is a transient, per-instance, value that will NOT be carried over from instance
     * to instance of this class during requests.
     *
     * @var int|null
     */
    private $lastQueuedActionId;

    /**
     * PrepareExport constructor.
     *
     * @param AjaxPrepareExport $ajaxPrepareExport A reference to the object currently handling
     *                                             AJAX Backup preparation requests.
     */
    public function __construct(AjaxPrepareExport $ajaxPrepareExport, Queue $queue, BackupProcessLock $processLock)
    {
        $this->ajaxPrepareExport = $ajaxPrepareExport;
        $this->queue = $queue;
        $this->processLock = $processLock;
    }

    /**
     * @param array<string,mixed>|null $data Either a map of the data to prepare the Backup with, or
     *                                       `null` to use the default Backup settings.
     *
     * @return string|WP_Error Either the Background Processing Job identifier for this backup task, or
     *                         an error instance detailing the cause of the failure.
     */
    public function prepare($data = null)
    {
        $data = empty($data) ? [] : (array)$data;

        try {
            $data = (array)wp_parse_args((array)$data, $this->getDefaultDataConfiguration());
            $prepared = $this->ajaxPrepareExport->validateAndSanitizeData($data);
            $name = isset($prepared['name']) ? $prepared['name'] : 'Background Processing Backup';
            $jobId = uniqid($name . '_', true);

            $data['jobId'] = $jobId;
            $data['name'] = $name;

            $this->queueAction($data);

            return $jobId;
        } catch (Exception $e) {
            return new WP_Error(400, $e->getMessage());
        }
    }

    /**
     * Queues the Background Processing Action required to move the Backup job forward.
     *
     * @param array $jobId The identifier of all the Actions part of this Backup processing.
     *
     * @throws QueueException If there is an issue enqueueing the background processing action required by the
     *                        job prepare.
     */
    private function queueAction($args)
    {
        if (!isset($args['jobId'])) {
            throw new \BadMethodCallException();
        }

        $action = $this->getCurrentAction();
        $priority = $action === null ? 0 : $action->priority - 1;
        $actionId = $this->queue->enqueueAction(self::class . '::' . 'act', $args, $args['jobId'], $priority);

        if ($actionId === false || !$this->queue->getAction($actionId) instanceof Action) {
            throw new QueueException('Backup background processing action could not be queued.');
        }

        $this->lastQueuedActionId = $actionId;
    }

    /**
     * This method is the one the Queue will invoke to move the Backup processing forward.
     *
     * This method will either end the Backup background processing (on completion or failure), or
     * enqueue a new Action in the background processing system to keep running the Backup.
     *
     * @param string $jobId The identifier of all the Actions part of this Backup processing.
     *
     * @return WP_Error|TaskResponseDto Either a reference to the updated Backup task status, or a reference
     *                                  to the Error instance detailing the reasons of the failure.
     * @throws QueueException
     */
    public function act($args)
    {
        try {
            $this->processLock->checkProcessLocked();
        } catch (ProcessLockedException $e) {
            $this->queueAction($args);

            return new WP_Error(400, $e->getMessage());
        }

        if ($args['isInit']) {
            debug_log('[Schedule] Configuring JOB DATA DTO');
            $prepareExport = WPStaging::make(\WPStaging\Pro\Backup\Ajax\Export\PrepareExport::class);
            $prepareExport->prepare($args);
            $this->jobExport = $prepareExport->getJobExport();
        } else {
            $this->jobExport = WPStaging::make(JobExport::class);
        }

        $args['isInit'] = false;

        $taskResponseDto = null;

        debug_log('[Schedule Job Data DTO]: ' . wp_json_encode($this->jobExport->getJobDataDto()));

        do {
            try {
                $taskResponseDto = $this->jobExport->prepareAndExecute();
                $this->jobExport->persist();
                $this->persistDtoToAction($this->getCurrentAction(), $taskResponseDto);
            } catch (Exception $e) {
                error_log('Action for ' . $args['jobId'] . ' failed: ' . $e->getMessage());
                debug_log('Action for ' . $args['jobId'] . ' failed: ' . $e->getMessage());
                $this->persistDtoToAction($this->getCurrentAction(), $taskResponseDto);
                $this->processLock->unlockProcess();

                return new WP_Error(400, $e->getMessage());
            }

            $errorMessage = $this->getLastErrorMessage();
            if ($errorMessage !== false) {
                $this->processLock->unlockProcess();
                return new WP_Error(400, $errorMessage);
            }

            if ($taskResponseDto->isStatus()) {
                // We're finished, get out and bail.
                return $taskResponseDto;
            }
        } while (!$this->isThreshold());

        // We're not done, queue a new Action to keep processing this job.
        $this->queueAction($args);

        return $taskResponseDto;
    }

    /**
     * Returns the ID of the last Background Processing Action queued by this class, if any.
     *
     * @return int|null The ID of the last Background Processing Action queued by this class, if any.
     */
    public function getLastQueuedActionId()
    {
        return $this->lastQueuedActionId;
    }

    /**
     * Commits the current Export Job status to the database.
     *
     * This method is a proxy to the Ajax Export Prepare handler own `commit` method.
     *
     * @return bool Whether the commit was successful, in terms of intended state, or not.
     */
    public function persist()
    {
        return $this->ajaxPrepareExport->persist();
    }

    /**
     * Returns the Job ID of the last Queue action queued by the Job.
     *
     * @return string|null Either the Job ID of the last Action queued by the Job, or `null` if the
     *                     Job did not queue any Action yet.
     */
    public function getQueuedJobId()
    {
        if (empty($this->lastQueuedActionId)) {
            return null;
        }
        try {
            return $this->queue->getAction($this->lastQueuedActionId)->jobId;
        } catch (QueueException $e) {
            return null;
        }
    }

    /**
     * Returns the default data configuration that will be used to prepare a Backup using
     * default settings.
     *
     * @return array<string,bool> The Backup preparation default settings.
     */
    public function getDefaultDataConfiguration()
    {
        return [
            'isExportingPlugins' => true,
            'isExportingMuPlugins' => true,
            'isExportingThemes' => true,
            'isExportingUploads' => true,
            'isExportingOtherWpContentFiles' => true,
            'isExportingDatabase' => true,
            'isAutomatedBackup' => true,
            // Prevent this scheduled backup from generating another schedule.
            'repeatBackupOnSchedule' => false,
            'sitesToExport' => [],
            'storages' => ['localStorage'],
            'isInit' => true,
        ];
    }

    /**
     * Persists the response DTO to the Action custom field, if possible.
     *
     * @param Action|null          $action A reference to the Action object currently being processed, or `null` if
     *                                     the current Action being processed is not available.
     * @param TaskResponseDto|null $dto    A reference to the current task DTO, or `null` if not available.
     *
     * @return void The method does not return any value and will have the side effect of
     *              persisting the task DTO to the Action custom field.
     */
    private function persistDtoToAction(Action $action = null, TaskResponseDto $dto = null)
    {
        try {
            if ($action === null || $dto === null) {
                return;
            }

            $logFile = $this->jobExport->getCurrentTask()->getLogger()->getFileName();
            $this->queue->updateActionFields($action->id, ['custom' => $logFile], true);

            $errorMessage = $this->getLastErrorMessage();
            if ($errorMessage !== false) {
                debug_log($errorMessage);
            }
        } catch (Exception $e) {
            // We could be doing this in the context of Exception handling, let's not throw one more.
        }
    }

    /**
     * @return string|false Return error message. If there is no error message, return false
     */
    private function getLastErrorMessage()
    {
        $error = $this->jobExport->getCurrentTask()->getLogger()->getLastErrorMsg();

        if ($error === false) {
            return false;
        }

        if (is_array($error) && key_exists('message', $error)) {
            $error = $error['message'];
        }

        if (!is_string($error)) {
            $error = json_encode($error);
        }

        debug_log('[Schedule Last Error Message]: ' . $error);
        return $error;
    }
}
