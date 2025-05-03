<?php
/**
 * Broken Links plugin for Craft CMS
 *
 * A widget that displays a summary of broken links
 */

namespace craigclement\craftbrokenlinks\widgets;

use Craft;
use craft\base\Widget;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

class BrokenLinksWidget extends Widget
{
    /**
     * @var int Number of broken links to display in the widget
     */
    public $limit = 5;
    
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('broken-links', 'Broken Links');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@appicons/link.svg';
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return $this->displayName();
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string 
    {
        $service = new BrokenLinksService();
        $totalBrokenLinks = $service->countBrokenLinks();
        
        if ($totalBrokenLinks > 0) {
            return Craft::t('broken-links', '{count} broken', ['count' => $totalBrokenLinks]);
        }
        
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        // Include our assets bundle
        $service = new BrokenLinksService();
        $latestScan = $service->getLatestScan();
        $brokenLinks = $service->getLatestBrokenLinks($this->limit);
        $totalBrokenLinks = $service->countBrokenLinks();

        // Render the widget template
        return Craft::$app->getView()->renderTemplate(
            'brokenlinks/widgets/broken-links-widget',
            [
                'widget' => $this,
                'latestScan' => $latestScan,
                'brokenLinks' => $brokenLinks,
                'totalBrokenLinks' => $totalBrokenLinks,
                'limit' => $this->limit,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'brokenlinks/widgets/broken-links-widget-settings',
            [
                'widget' => $this
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['limit'], 'integer', 'min' => 1];
        return $rules;
    }
}