<?php

namespace WPStaging\Pro\Backup\Task;

use UnexpectedValueException;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Service\Database\Importer\DomainPathUpdater;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

/**
 * Class MultisiteImportTask
 *
 * This is an abstract class for the multisite specific import actions of importing a site.
 *
 * @package WPStaging\Pro\Backup\Abstracts\Task
 */
abstract class MultisiteImportTask extends ImportTask
{
    /** @var array */
    protected $sites;

    /** @var wpdb */
    protected $wpdb;

    /** @var string */
    protected $sourceSiteDomain;

    /** @var string */
    protected $sourceSitePath;

    /** @var bool */
    protected $isSubdomainInstall;

    /** @var DomainPathUpdater */
    protected $domainPathUpdater;

    public function __construct(DomainPathUpdater $domainPathUpdater, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);

        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->domainPathUpdater = $domainPathUpdater;
    }

    /**
     * @throws UnexpectedValueException
     */
    protected function adjustDomainPath()
    {
        $this->domainPathUpdater->readMetaData($this->jobDataDto);
        $this->sourceSiteDomain = $this->domainPathUpdater->getSourceSiteDomain();
        $this->sourceSitePath = $this->domainPathUpdater->getSourceSitePath();
        $this->isSubdomainInstall = $this->domainPathUpdater->getIsSourceSubdomainInstall();
        $this->sites = $this->domainPathUpdater->getSitesWithNewURLs(DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE, home_url(), is_subdomain_install());
    }
}
