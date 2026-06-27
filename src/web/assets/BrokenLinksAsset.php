<?php

namespace craigclement\craftbrokenlinks\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Control-panel asset bundle for the Broken Links plugin.
 *
 * Bundles the page/widget styles and the index-page JavaScript so they're
 * served as static files instead of being inlined in the templates.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class BrokenLinksAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@craigclement/craftbrokenlinks/web/assets/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/broken-links.css',
        ];

        $this->js = [
            'js/broken-links.js',
        ];

        parent::init();
    }
}
