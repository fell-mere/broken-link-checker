<?php

namespace craigclement\craftbrokenlinks\widgets;

use Craft;
use craft\base\Widget;
use craigclement\craftbrokenlinks\Plugin;
use craigclement\craftbrokenlinks\web\assets\BrokenLinksAsset;

/**
 * Dashboard widget showing a summary of the most recent broken-link scan.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class BrokenLinksWidget extends Widget
{
    // Public Properties
    // =========================================================================

    /**
     * @var int The maximum number of broken links to show in the widget body.
     */
    public int $limit = 5;

    // Private Properties
    // =========================================================================

    /**
     * @var int|null Cached total count of broken links.
     * @see getCachedTotal()
     */
    private ?int $_totalBrokenLinks = null;

    // Public Methods
    // =========================================================================

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
        $total = $this->getCachedTotal();

        if ($total > 0) {
            return Craft::t('broken-links', '{count} broken', ['count' => $total]);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(BrokenLinksAsset::class);

        $service = Plugin::getInstance()->getBrokenLinks();
        $latestScan = $service->getLatestScan();
        $brokenLinks = $service->getLatestBrokenLinks($this->limit);
        $totalBrokenLinks = $this->getCachedTotal();

        return $view->renderTemplate(
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
            ['widget' => $this]
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

    // Private Methods
    // =========================================================================

    /**
     * Returns the total broken-link count, resolving it once and caching it.
     */
    private function getCachedTotal(): int
    {
        if ($this->_totalBrokenLinks === null) {
            $this->_totalBrokenLinks = Plugin::getInstance()->getBrokenLinks()->countBrokenLinks();
        }
        return $this->_totalBrokenLinks;
    }
}
