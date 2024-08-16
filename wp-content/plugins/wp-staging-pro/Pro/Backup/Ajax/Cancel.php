<?php

namespace WPStaging\Pro\Backup\Ajax;

use WPStaging\Core\WPStaging;
use WPStaging\Framework\Analytics\AnalyticsEventDto;
use WPStaging\Framework\Component\AbstractTemplateComponent;
use WPStaging\Framework\TemplateEngine\TemplateEngine;
use WPStaging\Pro\Backup\BackupProcessLock;
use WPStaging\Pro\Backup\Dto\JobDataDto;
use WPStaging\Pro\Backup\Exceptions\ProcessLockedException;
use WPStaging\Pro\Backup\Job\Jobs\JobCancel;
use WPStaging\Pro\Backup\Job\Jobs\JobExport;

class Cancel extends AbstractTemplateComponent
{
    protected $processLock;

    public function __construct(TemplateEngine $templateEngine, BackupProcessLock $processLock)
    {
        $this->processLock = $processLock;

        parent::__construct($templateEngine);
    }

    public function render()
    {
        if (!$this->canRenderAjax()) {
            return;
        }

        try {
            $this->processLock->checkProcessLocked();
        } catch (ProcessLockedException $e) {
            wp_send_json_error($e->getMessage(), $e->getCode());
        }

        /** @var JobExport $job */
        $job = WPStaging::getInstance()->get(JobCancel::class);

        if (isset($_POST['isInit']) && sanitize_text_field($_POST['isInit']) === 'yes') {
            $jobDataDto = WPStaging::getInstance()->getContainer()->make(JobDataDto::class);
            $jobDataDto->setInit(true);
            $jobDataDto->setId(substr(md5(mt_rand() . time()), 0, 12));
            $job->setJobDataDto($jobDataDto);

            $jobId = isset($_POST['jobIdBeingCancelled']) ? html_entity_decode(sanitize_text_field($_POST['jobIdBeingCancelled'])) : '';

            AnalyticsEventDto::enqueueCancelEvent($jobId);
        }

        wp_send_json($job->prepareAndExecute());
    }
}
