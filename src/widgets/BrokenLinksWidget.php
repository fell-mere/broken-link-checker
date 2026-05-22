<?php

namespace craigclement\craftbrokenlinks\widgets;

use Craft;
use craft\base\Widget;
use craigclement\craftbrokenlinks\Plugin;

class BrokenLinksWidget extends Widget
{
    public int $limit = 5;

    private ?int $_totalBrokenLinks = null;

    public static function displayName(): string
    {
        return Craft::t('broken-links', 'Broken Links');
    }

    public static function icon(): ?string
    {
        return '@appicons/link.svg';
    }

    public function getTitle(): string
    {
        return $this->displayName();
    }

    public function getSubtitle(): ?string
    {
        $total = $this->getCachedTotal();

        if ($total > 0) {
            return Craft::t('broken-links', '{count} broken', ['count' => $total]);
        }

        return null;
    }

    public function getBodyHtml(): ?string
    {
        $service = Plugin::getInstance()->brokenLinks;
        $latestScan = $service->getLatestScan();
        $brokenLinks = $service->getLatestBrokenLinks($this->limit);
        $totalBrokenLinks = $this->getCachedTotal();

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

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'brokenlinks/widgets/broken-links-widget-settings',
            ['widget' => $this]
        );
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['limit'], 'integer', 'min' => 1];
        return $rules;
    }

    private function getCachedTotal(): int
    {
        if ($this->_totalBrokenLinks === null) {
            $this->_totalBrokenLinks = Plugin::getInstance()->brokenLinks->countBrokenLinks();
        }
        return $this->_totalBrokenLinks;
    }
}
