<?php

namespace craigclement\craftbrokenlinks\services;

// Import required libraries
use Craft;                           // Craft CMS core class
use GuzzleHttp\Client;              // Guzzle HTTP client for making web requests
use yii\base\Component;             // Base class for creating Craft services
use craigclement\craftbrokenlinks\records\BrokenLinkRecord; // Import BrokenLinkRecord for database interactions

/**
 * Service to check for broken links in Craft CMS entries.
 */
class BrokenLinksService extends Component
{

    /**
     * Get all site URLs from Craft CMS entries.
     *
     * @return array List of all entry URLs.
     */
    public function getAllSiteUrls(): array
    {
        $urls = [];

        // Fetch all entries in Craft CMS
        $entries = Craft::$app->elements->createElementQuery(\craft\elements\Entry::class)
            ->all();

        foreach ($entries as $entry) {
            if ($entry->getUrl()) {
                $urls[] = $entry->getUrl();
            }
        }

        return $urls;
    }


    /**
     * Crawl the entire website for broken links.
     *
     * @param string $baseUrl The base URL of the website.
     * @return array List of broken links found.
     */
    public function crawlSite(string $baseUrl): array
    {
        // Create an HTTP client with a 5-second timeout
        $client = new Client(['timeout' => 5]); 
        $brokenLinks = [];  // Store broken links
        $visitedUrls = [];  // Track visited pages to avoid duplicates

        // Log the start of the crawling process
        Craft::info("Starting crawl for base URL: $baseUrl", __METHOD__);

        try {
            // Get all pages (entries) from Craft CMS
            $entries = Craft::$app->elements->createElementQuery(\craft\elements\Entry::class)
                ->with(['*'])   // Load all related fields
                ->all();        // Fetch all results

            Craft::info("Found " . count($entries) . " entries to crawl.", __METHOD__);

            // Loop through each entry (page)
            foreach ($entries as $entry) {
                $url = $entry->getUrl();  // Get the page's URL

                // Skip if no URL or already visited
                if (!$url || in_array($url, $visitedUrls)) {
                    Craft::info("Skipping entry ID: {$entry->id} - URL: $url", __METHOD__);
                    continue; 
                }

                // Mark the URL as visited
                $visitedUrls[] = $url;

                // Check all links on the current page
                $this->crawlPage($client, $url, $brokenLinks, $visitedUrls, $entry);
            }

            // Return the list of broken links found
            return $brokenLinks;
        } catch (\Throwable $e) {
            // Log and rethrow any errors encountered
            Craft::error("Error during crawl: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Check all links on a specific page.
     *
     * @param Client $client HTTP client for making requests.
     * @param string $url The page URL being crawled.
     * @param array &$brokenLinks Reference to the broken links list.
     * @param array &$visitedUrls Reference to the visited URLs list.
     * @param \craft\elements\Entry|null $entry Optional entry for context.
     */
    private function crawlPage(Client $client, string $url, array &$brokenLinks, array &$visitedUrls, $entry = null): void
    {
        try {
            // Fetch the page's content
            $response = $client->get($url);
            $html = $response->getBody()->getContents();

            // Extract all <a> tags and their URLs from the page
            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)".*?>(.*?)<\/a>/is', $html, $matches);
            $urls = $matches[1] ?? [];      // Extracted links
            $linkTexts = $matches[2] ?? []; // Extracted link text

            // Loop through each found link
            foreach ($urls as $index => $link) {
                // Create the absolute URL based on the current page's URL
                $absoluteUrl = $this->resolveUrl($url, $link);
                $linkText = strip_tags(trim($linkTexts[$index] ?? ''));

                // Skip if not an external or internal HTTP/HTTPS link
                if (!preg_match('/^https?:\/\//', $absoluteUrl)) {
                    continue;
                }

                try {
                    // Send a HEAD request to check if the link works
                    $response = $client->head($absoluteUrl);

                    // Check if the link is broken (error code 400 and above)
                    if ($response->getStatusCode() >= 400) {
                        $brokenLinks[] = [
                            'url' => $absoluteUrl,
                            'status' => 'Broken (' . $response->getStatusCode() . ')',
                            'entryId' => $entry?->id,
                            'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                            'entryUrl' => $entry ? $entry->getCpEditUrl() : null,
                            'linkText' => $linkText, 
                            'field' => 'todo',  // Placeholder for future field data
                            'pageUrl' => $url,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Add unreachable links to the broken list
                    $brokenLinks[] = [
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'error' => $e->getMessage(),
                        'entryId' => $entry?->id,
                        'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                        'entryUrl' => $entry ? $entry->getCpEditUrl() : null,
                        'linkText' => $linkText,
                        'field' => 'todo',  // Placeholder for future field data
                        'pageUrl' => $url,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Log any errors during crawling
            Craft::error("Error crawling page URL: $url - " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Resolve a relative link into an absolute URL.
     *
     * @param string $baseUrl The page's base URL.
     * @param string $relativeUrl The link's relative URL.
     * @return string The resolved absolute URL.
     */
    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
            new \GuzzleHttp\Psr7\Uri($baseUrl),
            new \GuzzleHttp\Psr7\Uri($relativeUrl)
        );
    }

    /**
     * Save a broken link record to the database.
     *
     * @param array $data The data to save.
     * @return bool Whether the save was successful.
     */
    public function saveBrokenLink(array $data): bool
    {
        $record = new BrokenLinkRecord();
        $record->setAttributes($data, false);

        return $record->save();
    }

    /**
     * Retrieve all broken link records from the database.
     *
     * @return array List of broken link records.
     */
    public function getBrokenLinks(): array
    {
        return BrokenLinkRecord::find()->all();
    }

    /**
     * Delete a broken link record from the database.
     *
     * @param int $id The ID of the record to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteBrokenLink(int $id): bool
    {
        $record = BrokenLinkRecord::findOne($id);
        if ($record) {
            return $record->delete();
        }

        return false;
    }
}
