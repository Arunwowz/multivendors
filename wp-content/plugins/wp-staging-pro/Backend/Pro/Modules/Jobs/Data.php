<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use WPStaging\Framework\CloningProcess\Data\Job as DataJob;
use WPStaging\Pro\Push\Data\PreserveBlogPublicSettings;
use WPStaging\Pro\Push\Data\PreserveHomeSiteURL;
use WPStaging\Pro\Push\Data\PreserveOptions;
use WPStaging\Pro\Push\Data\PreservePermalinkStructure;
use WPStaging\Pro\Push\Data\PreserveSessionTokenUserMetaTable;
use WPStaging\Pro\Push\Data\PreserveWPStagingInfo;
use WPStaging\Pro\Push\Data\PreserveWPStagingProVersion;
use WPStaging\Pro\Push\Data\RemoveStagingOptions;
use WPStaging\Pro\Push\Data\UpdateActivePluginsOptionsTable;
use WPStaging\Pro\Push\Data\UpdateDomainPathBlogsTable;
use WPStaging\Pro\Push\Data\UpdateDomainPathSiteTable;
use WPStaging\Pro\Push\Data\UpdatePrefixOptionsTable;
use WPStaging\Pro\Push\Data\UpdatePrefixUserMetaTable;

/**
 * Class Data
 * @package WPStaging\Backend\Pro\Modules\Jobs
 */
class Data extends DataJob
{
    /**
     * Initialize
     */
    public function initialize()
    {
        // Fix current step
        if ($this->options->currentStep == 0) {
            $this->options->currentStep = 1;
        }

        parent::initialize();

        $this->options->destinationDir = '';
        $this->options->mainJob = 'push';
        $this->tables = [];
    }

    /**
     * Calculate Total Steps in This Job and Assign It to $this->options->totalSteps
     * @return void
     */
    protected function calculateTotalSteps()
    {
        $this->options->totalSteps = 11;

        if ($this->isNetworkClone()) {
            $this->options->totalSteps = 13;
        }
    }

    /**
     * Checks Whether There is Any Job to Execute or Not
     * @return bool
     */
    protected function isFinished()
    {
        return
            $this->options->currentStep > $this->options->totalSteps ||
            !method_exists($this, "step" . $this->options->currentStep);
    }

    /**
     * Update several entries in options table
     * @return bool
     */
    protected function step1()
    {
        return (new PreserveWPStagingInfo($this->getCloningDto(1)))->execute();
    }

    /**
     * Update table wp_options
     * Change table prefix
     * @return bool
     */
    protected function step2()
    {
        return (new UpdatePrefixOptionsTable($this->getCloningDto(2)))->execute();
    }

    /**
     * Update table user_meta
     * Change table prefix
     * @return bool
     */
    protected function step3()
    {
        return (new UpdatePrefixUserMetaTable($this->getCloningDto(3)))->execute();
    }

    /**
     * Update table options active_plugins
     * Update active plugins
     * @return bool
     */
    protected function step4()
    {
        return (new UpdateActivePluginsOptionsTable($this->getCloningDto(4)))->execute();
    }

    /**
     * Update table tmp_usermeta session token
     * @return bool
     */
    protected function step5()
    {
        return (new PreserveSessionTokenUserMetaTable($this->getCloningDto(5)))->execute();
    }

    /**
     * Get permalink_structure from live site and copy it to the migrating tmp_tables to keep the current permalink structure
     * Update permalink_structure
     * @return bool
     */
    protected function step6()
    {
        return (new PreservePermalinkStructure($this->getCloningDto(6)))->execute();
    }

    /**
     * Get original siteurl and home path and copy it to the wpstgtmp table
     *
     * @return bool
     */
    protected function step7()
    {
        return (new PreserveHomeSiteURL($this->getCloningDto(7)))->execute();
    }

    /**
     * Get wpstgpro_version from live site and copy it to wpstgtmp_options
     */
    protected function step8()
    {
        return (new PreserveWPStagingProVersion($this->getCloningDto(8)))->execute();
    }

    /**
     * Get blog_public from live site and copy it to wpstgtmp_options
     * @return bool
     */
    protected function step9()
    {
        return (new PreserveBlogPublicSettings($this->getCloningDto(9)))->execute();
    }

    /**
     * Preserve data and prevents data from being pushed from staging to production in wp_options
     * @return bool
     */
    protected function step10()
    {
        return (new PreserveOptions($this->getCloningDto(10)))->execute();
    }

    /**
     * Delete several option from tmp_options and make sure they do not exist on production site after pushing
     * @return boolean
     */
    protected function step11()
    {
        return (new RemoveStagingOptions($this->getCloningDto(11)))->execute();
    }

    /**
     * Adjust "domain" and "path" in site table
     * @return boolean
     */
    protected function step12()
    {
        return (new UpdateDomainPathSiteTable($this->getCloningDto(12)))->execute();
    }

    /**
     * Adjust "domain" and "path" in blogs table
     * @return boolean
     */
    protected function step13()
    {
        return (new UpdateDomainPathBlogsTable($this->getCloningDto(13)))->execute();
    }

    protected function getTables()
    {
        $this->tables = [];
    }
}
