<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Pro\Backup\Ajax\Import\PrepareImport;
use WPStaging\Pro\Backup\Task\MultisiteImportTask;

class UpdateDomainPathTask extends MultisiteImportTask
{
    public static function getTaskName()
    {
        return 'backup_restore_update_domain_and_path';
    }

    public static function getTaskTitle()
    {
        return 'Updating domain and path in database';
    }

    public function execute()
    {
        $this->stepsDto->setTotal(1);

        if ($this->jobDataDto->getIsMissingDatabaseFile()) {
            $this->logger->warning(__('Skipped updating site URL domain and path.', 'wp-staging'));
            return $this->generateResponse();
        }

        $this->adjustDomainPath();
        // Skip if source and current domain and path already same
        if ($this->sourceSiteDomain === DOMAIN_CURRENT_SITE && $this->sourceSitePath === PATH_CURRENT_SITE && $this->isSubdomainInstall === is_subdomain_install()) {
            $this->logger->info(esc_html__('Skipped updating site URL domain and path as already same', 'wp-staging'));
            return $this->generateResponse();
        }

        if ($this->sourceSiteDomain !== DOMAIN_CURRENT_SITE || $this->sourceSitePath !== PATH_CURRENT_SITE) {
            $this->updateSiteTableDomainPath();
        }

        $this->updateBlogsTableDomainPath();

        $this->logger->info(esc_html__('Updating site URL domain and URL path in database finished', 'wp-staging'));

        return $this->generateResponse();
    }

    protected function updateSiteTableDomainPath()
    {
        $tmpSiteTable = PrepareImport::TMP_DATABASE_PREFIX . 'site';
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$tmpSiteTable} SET domain = %s, path = %s",
                DOMAIN_CURRENT_SITE,
                PATH_CURRENT_SITE
            )
        );

        if (!$result) {
            $this->logger->warning(esc_html__("Failed to update Domain and Path in site table", "wp-staging"));
        }
    }

    protected function updateBlogsTableDomainPath()
    {
        $tmpBlogsTable = PrepareImport::TMP_DATABASE_PREFIX . 'blogs';

        foreach ($this->sites as $blog) {
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$tmpBlogsTable} SET domain = %s, path = %s WHERE blog_id = %s AND site_id = %s",
                    $blog['new_domain'],
                    $blog['new_path'],
                    $blog['blog_id'],
                    $blog['site_id']
                )
            );

            if (!$result) {
                $this->logger->warning(sprintf(esc_html__("Failed to update Domain and Path in blogs table for blog_id: %s and site_id: %s", "wp-staging"), $blog['blog_id'], $blog['site_id']));
            }
        }
    }
}
