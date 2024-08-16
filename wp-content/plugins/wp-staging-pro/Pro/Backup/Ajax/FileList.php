<?php

// TODO PHP7.x; declare(strict_type=1);
// TODO PHP7.x; type hints & return types

namespace WPStaging\Pro\Backup\Ajax;

use WPStaging\Framework\Component\AbstractTemplateComponent;
use WPStaging\Framework\TemplateEngine\TemplateEngine;
use WPStaging\Pro\Backup\Entity\ListableBackup;
use WPStaging\Pro\Backup\Ajax\FileList\ListableBackupsCollection;
use WPStaging\Backend\Pro\Licensing\Licensing;
use WPStaging\Framework\SiteInfo;
use WPStaging\Framework\Utils\Sanitize;

class FileList extends AbstractTemplateComponent
{
    /** @var ListableBackupsCollection */
    private $listableBackupsCollection;

    /** @var Sanitize */
    private $sanitize;

    public function __construct(ListableBackupsCollection $listableBackupsCollection, TemplateEngine $templateEngine, Sanitize $sanitize)
    {
        parent::__construct($templateEngine);
        $this->listableBackupsCollection = $listableBackupsCollection;
        $this->sanitize = $sanitize;
    }

    public function render()
    {
        if (!$this->canRenderAjax()) {
            return;
        }

        // Discover the .wpstg backups in the filesystem
        $listableBackups = $this->listableBackupsCollection->getListableBackups();

        /**
         * Javascript expects an array with keys in natural order
         *
         * @var ListableBackup[] $listableBackups
         */
        $listableBackups = array_values($listableBackups);

        // Sort backups by the highest created/upload date, newest first.
        usort($listableBackups, function ($item, $nextItem) {
            /**
             * @var ListableBackup $item
             * @var ListableBackup $nextItem
             */
            return (max($nextItem->dateUploadedTimestamp, $nextItem->dateCreatedTimestamp)) - (max($item->dateUploadedTimestamp, $item->dateCreatedTimestamp));
        });

        // Returns a HTML template
        if (isset($_GET['withTemplate']) && $this->sanitize->sanitizeBool($_GET['withTemplate'])) {
            $output = '';

            $isValidLicenseKey = (new SiteInfo())->isStagingSite() || (new Licensing())->isValidOrExpiredLicenseKey();

            if (empty($listableBackups) || !$isValidLicenseKey) {
                $output .= $this->renderTemplate('Backend/views/backup/listing-backups-no-results.php', [
                    'urlAssets'   => trailingslashit(WPSTG_PLUGIN_URL) . 'assets/',
                    'isValidLicenseKey'   => $isValidLicenseKey,
                ]);
            } else {
                $output .= sprintf('<h3>%s</h3>', __('Your Backups:', 'wp-staging'));

                /** @var ListableBackup $listable */
                foreach ($listableBackups as $listable) {
                    $viewData = [
                        'backup' => $listable,
                        'urlAssets' => trailingslashit(WPSTG_PLUGIN_URL) . 'assets/',
                    ];

                    $output .= $this->renderTemplate(
                        'Backend/views/backup/listing-single-backup.php',
                        $viewData
                    );
                }
            }

            wp_send_json($output);
        }

        // Returns a JSON response
        wp_send_json($listableBackups);
    }
}
