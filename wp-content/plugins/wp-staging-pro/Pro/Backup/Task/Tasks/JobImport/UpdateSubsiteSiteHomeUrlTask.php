<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Pro\Backup\Ajax\Import\PrepareImport;
use WPStaging\Pro\Backup\Task\MultisiteImportTask;

class UpdateSubsiteSiteHomeUrlTask extends MultisiteImportTask
{
    public static function getTaskName()
    {
        return 'backup_restore_update_site_home_url';
    }

    public static function getTaskTitle()
    {
        return 'Updating site and home url for subsites';
    }

    public function execute()
    {
        $this->stepsDto->setTotal(1);

        if ($this->jobDataDto->getIsMissingDatabaseFile()) {
            $this->logger->warning(__('Skipped updating subsites URLs.', 'wp-staging'));
            return $this->generateResponse();
        }

        $this->adjustDomainPath();
        // Skip if destination domain and path already same
        if ($this->sourceSiteDomain === DOMAIN_CURRENT_SITE && $this->sourceSitePath === PATH_CURRENT_SITE && $this->isSubdomainInstall === is_subdomain_install()) {
            $this->logger->info(esc_html__('Skipped updating site URL and home URL, as they already same', 'wp-staging'));
            return $this->generateResponse();
        }

        $this->updateOptionsTableSiteHomeURL();

        $this->logger->info(esc_html__('Updating site URL and home URL for subsites in database finished', 'wp-staging'));

        return $this->generateResponse();
    }

    protected function updateOptionsTableSiteHomeURL()
    {
        foreach ($this->sites as $blog) {
            if ($blog['new_url'] === $blog['site_url']) {
                continue;
            }

            $tmpOptionsTable = $this->getSiteOptionTable($blog['blog_id']);

            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$tmpOptionsTable} SET option_value = %s WHERE option_name IN (%s, %s)",
                    $blog['new_url'],
                    'home',
                    'siteurl'
                )
            );

            // Nothing to do next. The site URL is updated
            if ($result) {
                return;
            }

            // Verify whether the URL was already updated during SEARCH REPLACE
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT count(*) FROM {$tmpOptionsTable} WHERE option_value = %s AND option_name IN (%s, %s)",
                    $blog['new_url'],
                    'home',
                    'siteurl'
                )
            );

            if ($count === 0) {
                $this->logger->warning(sprintf(esc_html__("Failed to update site and home URL in options table for blog_id: %s and site_id: %s", "wp-staging"), $blog['blog_id'], $blog['site_id']));
            }
        }
    }

    /**
     * @param int $siteId
     * @return string
     */
    protected function getSiteOptionTable($siteId)
    {
        if ($siteId > 1) {
            return PrepareImport::TMP_DATABASE_PREFIX . $siteId . '_options';
        }

        return PrepareImport::TMP_DATABASE_PREFIX . 'options';
    }
}
