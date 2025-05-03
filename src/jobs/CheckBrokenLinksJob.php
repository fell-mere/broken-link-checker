<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use GuzzleHttp\Client;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use craigclement\craftbrokenlinks\records\BrokenLinkRecord;
use craigclement\craftbrokenlinks\Plugin;

/**
 * CheckBrokenLinksJob checks links in entries for broken URLs.
 */
class CheckBrokenLinksJob extends BaseJob
{
    /**
     * @var int The scan history ID associated with this job
     */
    public $scanId;

    /**
     * @var array Array of entry IDs to check
     */
    public $entryIds = [];

    /**
     * @var string The base URL for site
     */
    public $baseUrl;
    
    /**
     * @var int Total number of batches in the scan
     */
    public $totalBatches = 1;
    
    /**
     * @var int Current batch index
     */
    public $batchIndex = 0;

    /**
     * @var bool Whether this is part of a forced full scan
     */
    public $forceFullScan = false;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        // Load the scan history record
        $scanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$scanRecord) {
            throw new \Exception("Scan history record with ID {$this->scanId} not found.");
        }

        // Create an HTTP client with a timeout
        $client = new Client(['timeout' => 5]);
        $brokenLinkCount = 0;
        $totalLinks = count($this->entryIds);
        $visitedUrls = [];
        
        try {
            // Load all entries to process
            $entries = Entry::find()
                ->id($this->entryIds)
                ->with(['*'])
                ->all();

            $currentEntry = 0;
            // Process each entry
            foreach ($entries as $entry) {
                $url = $entry->getUrl();
                
                // Skip if no URL or already visited
                if (!$url || in_array($url, $visitedUrls)) {
                    continue;
                }
                
                // Mark URL as visited
                $visitedUrls[] = $url;
                
                // Check links on this page
                $brokenLinks = $this->checkPageLinks($client, $url, $entry);
                $brokenLinkCount += count($brokenLinks);
                
                // Update progress
                $progress = ($currentEntry / count($entries)) / $this->totalBatches + 
                            $this->batchIndex / $this->totalBatches;
                $this->setProgress($queue, $progress);
                $currentEntry++;
            }
            
            // Update the scan record with broken link count
            // Use a transaction to avoid race conditions with other jobs
            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                $freshScanRecord = ScanHistoryRecord::findOne($this->scanId);
                
                // If this is a forced full scan and the last batch, get the total from the database
                if ($this->forceFullScan && $this->batchIndex == $this->totalBatches - 1) {
                    // Get the actual count of broken links from the database
                    $totalBrokenLinks = BrokenLinkRecord::find()->count();
                    $freshScanRecord->totalBrokenLinks = $totalBrokenLinks;
                } else {
                    // Otherwise just increment the total
                    $freshScanRecord->totalBrokenLinks += $brokenLinkCount;
                }
                
                // If this is the last batch, mark as completed
                if ($this->batchIndex == $this->totalBatches - 1) {
                    $freshScanRecord->status = ScanHistoryRecord::STATUS_COMPLETED;
                    $freshScanRecord->endTime = new \DateTime();
                }
                
                $freshScanRecord->save();
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            // Log the error
            Craft::error("Error checking for broken links: " . $e->getMessage(), __METHOD__);
            
            // Update scan history to failed status if this is the last batch
            if ($this->batchIndex == $this->totalBatches - 1) {
                $scanRecord->status = ScanHistoryRecord::STATUS_FAILED;
                $scanRecord->save();
            }
            
            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Check all links on a specific page.
     *
     * @param Client $client HTTP client for making requests.
     * @param string $url The page URL being crawled.
     * @param Entry $entry The entry being processed.
     * @return array List of broken links found.
     */
    private function checkPageLinks(Client $client, string $url, Entry $entry): array
    {
        $brokenLinks = [];
        
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
                    $statusCode = $response->getStatusCode();

                    // Check if the link is broken (error code 400 and above)
                    if ($statusCode >= 400) {
                        $brokenLink = $this->saveBrokenLink([
                            'url' => $absoluteUrl,
                            'status' => 'Broken',
                            'statusCode' => $statusCode,
                            'entryId' => $entry->id,
                            'entryTitle' => $entry->title ?? $entry->slug ?? 'N/A',
                            'linkText' => $linkText,
                            'field' => 'todo',  // Placeholder for future field data
                            'pageUrl' => $url,
                        ]);
                        
                        $brokenLinks[] = $brokenLink;
                    }
                } catch (\Throwable $e) {
                    // Add unreachable links to the broken list
                    $brokenLink = $this->saveBrokenLink([
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'errorMessage' => $e->getMessage(),
                        'entryId' => $entry->id,
                        'entryTitle' => $entry->title ?? $entry->slug ?? 'N/A',
                        'linkText' => $linkText,
                        'field' => 'todo',  // Placeholder for future field data
                        'pageUrl' => $url,
                    ]);
                    
                    $brokenLinks[] = $brokenLink;
                }
            }
        } catch (\Throwable $e) {
            // Log any errors during crawling
            Craft::error("Error crawling page URL: $url - " . $e->getMessage(), __METHOD__);
        }
        
        return $brokenLinks;
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
     * Save a broken link to the database.
     *
     * @param array $data Broken link data
     * @return array The saved broken link data
     */
    private function saveBrokenLink(array $data): array
    {
        $record = new BrokenLinkRecord();
        $record->url = $data['url'];
        $record->status = $data['status'];
        $record->statusCode = $data['statusCode'] ?? null;
        $record->errorMessage = $data['errorMessage'] ?? null;
        $record->entryId = $data['entryId'];
        $record->entryTitle = $data['entryTitle'];
        $record->linkText = $data['linkText'];
        $record->field = $data['field'];
        $record->pageUrl = $data['pageUrl'];
        $record->lastScanned = new \DateTime();
        
        try {
            // Check if this broken link already exists
            $existingRecord = BrokenLinkRecord::find()
                ->where(['url' => $data['url'], 'pageUrl' => $data['pageUrl']])
                ->one();
                
            if ($existingRecord) {
                // Update the existing record
                $existingRecord->status = $data['status'];
                $existingRecord->statusCode = $data['statusCode'] ?? null;
                $existingRecord->errorMessage = $data['errorMessage'] ?? null;
                $existingRecord->linkText = $data['linkText'];
                $existingRecord->lastScanned = new \DateTime();
                $existingRecord->save();
                
                return $existingRecord->getAttributes();
            } else {
                // Save the new record
                $record->save();
                return $record->getAttributes();
            }
        } catch (\Throwable $e) {
            Craft::error("Error saving broken link: " . $e->getMessage(), __METHOD__);
            // Return the data even if saving fails
            return $data;
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return 'Checking for broken links';
    }
}