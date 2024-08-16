<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Backend\Modules\Jobs\Exceptions\FatalException;

class UpdateDomainPathSiteTable extends DBPushService
{
    /**
     * @inheritDoc
     */
    protected function internalExecute()
    {
        if (!$this->isNetworkClone()) {
            return true;
        }

        return $this->updateSiteTable();
    }

    /**
     * @throws FatalException
     * @return boolean
     */
    private function updateSiteTable()
    {
        // Early bail if site table is excluded
        if ($this->isTableExcluded($this->stagingPrefix . 'site')) {
            $this->log("{$this->stagingPrefix}site excluded. Skipping this step");
            return true;
        }

        $tmpSiteTable = $this->getTmpPrefix() . 'site';

        if ($this->isTable($tmpSiteTable) === false) {
            $this->log('Fatal Error ' . $tmpSiteTable . ' does not exist');
            $this->returnException('Fatal Error ' . $tmpSiteTable . ' does not exist');
            return false;
        }

        $this->log("Updating domain and path in {$tmpSiteTable} to " . DOMAIN_CURRENT_SITE . " and " . PATH_CURRENT_SITE . " respectively");
        // Replace URLs
        $result = $this->productionDb->query(
            $this->productionDb->prepare(
                "UPDATE {$tmpSiteTable} SET domain = %s, path = %s",
                DOMAIN_CURRENT_SITE,
                PATH_CURRENT_SITE
            )
        );

        if ($result === false) {
            $this->returnException("Failed to update domain and path in {$tmpSiteTable}. {$this->productionDb->last_error}");
            return false;
        }

        return true;
    }
}
