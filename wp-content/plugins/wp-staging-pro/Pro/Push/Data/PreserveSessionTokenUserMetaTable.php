<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Core\Utils\Logger;

class PreserveSessionTokenUserMetaTable extends DBPushService
{
    /**
     * @inheritDoc
     */
    protected function internalExecute()
    {
        // usermeta table has been exluded from pushing process so exit here
        if ($this->isTableExcluded($this->stagingPrefix . 'usermeta')) {
            $this->log("{$this->stagingPrefix}usermeta excluded. Skipping this step");
            return true;
        }

        $tmpUserMetaTable = $this->getTmpPrefix() . 'usermeta';

        if ($this->isTable($tmpUserMetaTable) === false) {
            $this->log('Fatal Error ' . $tmpUserMetaTable . ' does not exist', Logger::TYPE_ERROR);
            $this->returnException('Fatal Error ' . $tmpUserMetaTable . ' does not exist');
            return false;
        }

        $this->log("Updating $tmpUserMetaTable session_tokens");

        $userId       = get_current_user_id();
        // Get session token for current user from live site usermeta table
        $sessionToken = $this->productionDb->get_var("SELECT meta_value FROM {$this->productionDb->base_prefix}usermeta WHERE meta_key = 'session_tokens' AND user_id = '$userId'");

        $sessionToken = unserialize($sessionToken);

        if (!$sessionToken) {
            $this->log("Can not get session token of current user from {$this->productionDb->prefix}usermeta ", Logger::TYPE_WARNING);
        }
        // Update session_tokens
        $resultSessionToken = $this->productionDb->query(
            "UPDATE $tmpUserMetaTable SET meta_value = '" . serialize($sessionToken) . "' WHERE meta_key = 'session_tokens' AND user_id = $userId"
        );

        if ($resultSessionToken === false) {
            $this->log("Can not update row session_tokens in $tmpUserMetaTable", Logger::TYPE_WARNING);
            return false;
        }

        return true;
    }
}
