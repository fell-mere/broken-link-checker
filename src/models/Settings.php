<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;

/**
 * Broken Links plugin settings.
 *
 * Holds the list of URL patterns that should be skipped during scans so that
 * known-noisy links (for example domains that block crawlers with a 403) do
 * not appear in the results.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var array<int, string> URL patterns to ignore during scans. Any broken
     *                         link URL containing one of these patterns as a
     *                         substring is skipped.
     */
    public array $ignoredUrlPatterns = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['ignoredUrlPatterns'], 'safe'],
        ];
    }
}
