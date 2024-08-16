<?php

namespace WPStaging\Pro\Push\Data;

class UpdatePrefixUserMetaTable extends DBPushService
{
    /**
     * @inheritDoc
     */
    protected function internalExecute()
    {
        if ($this->stagingPrefix === $this->productionDb->prefix) {
            $this->log("Skipping. Prefix of production and live site is the same: {$this->productionDb->prefix}");
            return true;
        }

        // usermeta table has been excluded from pushing process so exit here
        if ($this->isTableExcluded($this->stagingPrefix . 'usermeta')) {
            $this->log("{$this->stagingPrefix}usermeta excluded. Skipping this step");
            return true;
        }

        $tmpUserMetaTable = $this->getTmpPrefix() . 'usermeta';

        if ($this->isTable($tmpUserMetaTable) === false) {
            $this->log('Fatal Error ' . $tmpUserMetaTable . ' does not exist');
            $this->returnException('Fatal Error ' . $tmpUserMetaTable . ' does not exist');
            return false;
        }

        $this->log("Updating {$tmpUserMetaTable} db prefix to {$this->productionDb->prefix}");

        $resultMetaKeys = $this->productionDb->query(
            $this->productionDb->prepare(
                "UPDATE {$tmpUserMetaTable} SET meta_key = replace(meta_key, %s, %s) WHERE meta_key LIKE %s",
                $this->stagingPrefix,
                $this->productionDb->prefix,
                $this->stagingPrefix . "_%"
            )
        );

        if ($resultMetaKeys === false) {
            $this->log("SQL - UPDATE {$tmpUserMetaTable} SET meta_key = replace(meta_key, {$this->stagingPrefix}, {$this->productionDb->prefix}) WHERE meta_key LIKE {$this->stagingPrefix}_%");
            $this->log("Failed to update usermeta meta_key database table prefixes {$this->productionDb->last_error}");
            $this->returnException("Failed to update {$tmpUserMetaTable} meta_key database table prefixes {$this->productionDb->last_error}");
            return false;
        }

        return true;
    }
}
