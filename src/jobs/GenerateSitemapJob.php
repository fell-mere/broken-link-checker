<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use craigclement\craftbrokenlinks\jobs\CheckBrokenLinksJob;

class GenerateSitemapJob extends BaseJob
{
    public function execute($queue): void
    {
        Craft::info("Generating Sitemap for Broken Links", __METHOD__);

        // Get all entries' URLs from the Craft CMS site
        $entries = Craft::$app->elements->createElementQuery(Entry::class)->all();
        $urls = [];

        foreach ($entries as $entry) {
            if ($entry->getUrl()) {
                $urls[] = $entry->getUrl();
            }
        }

        if (empty($urls)) {
            Craft::warning("No URLs found for sitemap generation.", __METHOD__);
            return;
        }

        // Store URLs in cache for 1 hour
        Craft::$app->cache->set('brokenLinks_urls', $urls, 3600);

        Craft::info("Sitemap generated. Found " . count($urls) . " URLs.", __METHOD__);

        // Split into batches for checking broken links
        $batchSize = 10; // Process 20 links per job
        $batches = array_chunk($urls, $batchSize);

        foreach ($batches as $batch) {
            Craft::$app->queue->push(new CheckBrokenLinksJob(['urls' => $batch]));
        }

        Craft::info(count($batches) . " jobs added to queue for checking broken links.", __METHOD__);
    }

    protected function defaultDescription(): string
    {
        return "Generating Sitemap for Broken Links Checker";
    }
}
