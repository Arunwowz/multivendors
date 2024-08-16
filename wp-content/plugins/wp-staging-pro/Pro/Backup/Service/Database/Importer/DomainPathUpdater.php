<?php

namespace WPStaging\Pro\Backup\Service\Database\Importer;

use UnexpectedValueException;
use WPStaging\Pro\Backup\Dto\Job\JobImportDataDto;

class DomainPathUpdater
{
    protected $sites;

    private $sourceSiteDomain;

    private $sourceSitePath;

    protected $isSourceSubdomainInstall;

    public function getSourceSiteDomain()
    {
        return $this->sourceSiteDomain;
    }

    public function getSourceSitePath()
    {
        return $this->sourceSitePath;
    }

    public function getIsSourceSubdomainInstall()
    {
        return $this->isSourceSubdomainInstall;
    }

    public function setSourceSiteDomain($sourceSiteDomain)
    {
        $this->sourceSiteDomain = $sourceSiteDomain;
    }

    public function setSourceSitePath($sourceSitePath)
    {
        $this->sourceSitePath = $sourceSitePath;
    }

    public function setSourceSubdomainInstall($isSubdomainInstall)
    {
        $this->isSourceSubdomainInstall = $isSubdomainInstall;
    }

    public function setSourceSites($sites)
    {
        $this->sites = $sites;
    }

    public function getSitesWithNewURLs($baseDomain, $basePath, $homeURL, $isSubdomainInstall)
    {
        $adjustedSites = [];
        foreach ($this->sites as $site) {
            $adjustedSites[] = $this->adjustSiteDomainPath($site, $baseDomain, $basePath, $homeURL, $isSubdomainInstall);
        }

        return apply_filters('wpstg.backup.restore.multisites.subsites', $adjustedSites, $baseDomain, $basePath, $homeURL, $isSubdomainInstall);
    }

    public function readMetaData(JobImportDataDto $jobDataDto)
    {
        $this->isSourceSubdomainInstall = $jobDataDto->getBackupMetadata()->getSubdomainInstall();

        $sourceSiteURL = $jobDataDto->getBackupMetadata()->getSiteUrl();
        $sourceSiteURLWithoutWWW = str_ireplace('//www.', '//', $sourceSiteURL);
        $parsedURL = parse_url($sourceSiteURLWithoutWWW);

        if (!is_array($parsedURL) || !array_key_exists('host', $parsedURL)) {
            throw new UnexpectedValueException("Bad URL format, cannot proceed.");
        }

        $this->sourceSiteDomain = $parsedURL['host'];
        $this->sourceSitePath = '/';
        if (array_key_exists('path', $parsedURL)) {
            $this->sourceSitePath = $parsedURL['path'];
        }

        $this->sites = $jobDataDto->getBackupMetadata()->getSites();
    }

    private function adjustSiteDomainPath($site, $baseDomain, $basePath, $homeURL, $isSubdomainInstall)
    {
        $subsiteDomain = str_replace($this->sourceSiteDomain, $baseDomain, $site['domain']);
        $subsitePath = str_replace(trailingslashit($this->sourceSitePath), $basePath, $site['path']);
        $subsiteUrlWithoutScheme = untrailingslashit($subsiteDomain . $subsitePath);
        $mainsiteUrlWithoutScheme = untrailingslashit($baseDomain . $basePath);

        $wwwPrefix = '';
        if (strpos($homeURL, '//www.') !== false) {
            $wwwPrefix = 'www.';
        }

        if ($this->isSourceSubdomainInstall === $isSubdomainInstall && $subsiteUrlWithoutScheme === $mainsiteUrlWithoutScheme) {
            $site['new_url'] = parse_url($homeURL, PHP_URL_SCHEME) . '://' . $wwwPrefix . $subsiteUrlWithoutScheme;
            $site['new_domain'] = rtrim($subsiteDomain, '/');
            $site['new_path'] = $subsitePath;
            return $site;
        }

        $subsiteDomain = $baseDomain;
        $subsitePath = $basePath;

        if (strpos($subsiteUrlWithoutScheme, $mainsiteUrlWithoutScheme) === false) {
            return $this->mapSubsiteFromDomain($site, $homeURL, $wwwPrefix, $baseDomain, $basePath, $mainsiteUrlWithoutScheme, $subsiteUrlWithoutScheme, $isSubdomainInstall);
        }

        $subsiteName = str_replace($mainsiteUrlWithoutScheme, '', $subsiteUrlWithoutScheme);
        $subsiteName = rtrim($subsiteName, '.');
        $subsiteName = trim($subsiteName, '/');
        if ($wwwPrefix === '' && (strpos($subsiteDomain, 'www.') === 0)) {
            $subsiteDomain = substr($subsiteDomain, 4);
        }

        if ($isSubdomainInstall && ($subsiteName !== '')) {
            $subsiteDomain = $subsiteName . '.' . $subsiteDomain;
        }

        if (!$isSubdomainInstall && ($subsiteName !== '')) {
            $subsiteName = strpos($subsiteUrlWithoutScheme, 'www.') === 0 ? substr($subsiteName, 4) : $subsiteName;
            $subsiteName = empty($subsiteName) ? '' : trailingslashit($subsiteName);
            $subsiteName = ltrim($subsiteName, '/');
            $subsitePath = $subsitePath . $subsiteName;
        }

        $subsiteUrlWithoutScheme = untrailingslashit(rtrim($subsiteDomain, '/') . $subsitePath);
        $site['new_url'] = parse_url($homeURL, PHP_URL_SCHEME) . '://' . $wwwPrefix . $subsiteUrlWithoutScheme;
        $site['new_domain'] = rtrim($subsiteDomain, '/');
        $site['new_path'] = $subsitePath;
        return $site;
    }

    protected function mapSubsiteFromDomain($site, $homeURL, $wwwPrefix, $baseDomain, $basePath, $mainsiteUrlWithoutScheme, $subsiteUrlWithoutScheme, $isSubdomainInstall)
    {
        if (!$isSubdomainInstall) {
            $site['new_url'] = parse_url($homeURL, PHP_URL_SCHEME) . '://' . $wwwPrefix . trailingslashit($mainsiteUrlWithoutScheme) . $site['domain'];
            $site['new_domain'] = rtrim($baseDomain, '/');
            $site['new_path'] = $basePath . trailingslashit($site['domain']);
            return $site;
        }

        $site['new_url'] = parse_url($homeURL, PHP_URL_SCHEME) . '://' . $wwwPrefix . $site['domain'] . '.' . $mainsiteUrlWithoutScheme;
        $site['new_domain'] = $site['domain'] . '.' . rtrim($baseDomain, '/');
        $site['new_path'] = $basePath;
        return $site;
    }
}
