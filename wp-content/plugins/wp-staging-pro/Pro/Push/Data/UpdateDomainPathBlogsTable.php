<?php

namespace WPStaging\Pro\Push\Data;

class UpdateDomainPathBlogsTable extends DBPushService
{
    /**
     * @inheritDoc
     */
    protected function internalExecute()
    {
        if (!$this->isNetworkClone()) {
            return true;
        }

        return $this->updateBlogsTable();
    }

    /**
     * @return boolean
     */
    private function updateBlogsTable()
    {
        // Early bail if site table is excluded
        if ($this->isTableExcluded($this->stagingPrefix . 'blogs')) {
            $this->log("{$this->stagingPrefix}blogs excluded. Skipping this step");
            return true;
        }

        $tmpBlogsTable = $this->getTmpPrefix() . 'blogs';

        if ($this->isTable($tmpBlogsTable) === false) {
            $this->log('Fatal Error ' . $tmpBlogsTable . ' does not exist');
            $this->returnException('Fatal Error ' . $tmpBlogsTable . ' does not exist');
            return false;
        }

        foreach ($this->getStagingMultisiteBlogs() as $blog) {
            $domain = str_replace($this->dto->getStagingSiteDomain(), DOMAIN_CURRENT_SITE, $blog->domain);
            $path = str_replace(trailingslashit($this->dto->getStagingSitePath()), PATH_CURRENT_SITE, $blog->path);

            $this->log("Updating domain and path in {$tmpBlogsTable} for blog_id = {$blog->blog_id} to {$domain} and {$path} respectively");

            $result = $this->productionDb->query(
                $this->productionDb->prepare(
                    "UPDATE {$tmpBlogsTable} SET domain = %s, path = %s WHERE blog_id = %s",
                    $domain,
                    $path,
                    $blog->blog_id
                )
            );

            if ($result === false) {
                $this->returnException("Failed to update domain and path in {$tmpBlogsTable} for blog_id = {$blog->blog_id}. {$this->productionDb->last_error}");
                return false;
            }
        }

        return true;
    }
}
