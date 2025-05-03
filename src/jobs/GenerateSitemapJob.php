<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * GenerateSitemapJob generates a sitemap of entry URLs for processing.
 */
class GenerateSitemapJob extends BaseJob
{
    /**
     * @var int The scan history ID associated with this job
     */
    public $scanId;

    /**
     * @var int|null Maximum number of entries to process in a single job
     */
    public $batchSize = 100;

    /**
     * @var bool Whether to force scan all entries regardless of last updated date
     */
    public $forceFullScan = false;

    /**
     * @var string|null The base URL to use for scanning
     */
    public $baseUrl;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        // Update scan history status to running
        $scanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$scanRecord) {
            throw new \Exception("Scan history record with ID {$this->scanId} not found.");
        }
        
        $scanRecord->status = ScanHistoryRecord::STATUS_RUNNING;
        $scanRecord->save();
        
        try {
            // Get the base URL for the primary site if not provided
            if (!$this->baseUrl) {
                $this->baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
            }

            // Create an entry query
            $entryQuery = Entry::find()
                ->limit($this->batchSize)
                ->with(['*']);  // Load all related fields
            
            // Don't rescan entries that haven't changed since last scan
            if (!$this->forceFullScan) {
                // Get the last scan time to compare against
                $lastScan = ScanHistoryRecord::find()
                    ->where(['status' => ScanHistoryRecord::STATUS_COMPLETED])
                    ->orderBy(['endTime' => SORT_DESC])
                    ->one();
                
                if ($lastScan && $lastScan->endTime) {
                    // Only scan entries that were updated after the last scan
                    $entryQuery->andWhere(['>', 'elements.dateUpdated', $lastScan->endTime]);
                }
            }
            
            // Get all entries matching our criteria
            $entries = $entryQuery->all();
            $totalEntries = count($entries);
            
            // Update the scan record with the total
            $scanRecord->totalUrlsScanned = $totalEntries;
            $scanRecord->save();
            
            if ($totalEntries > 0) {
                // Process entries using the user-defined batch size
                $batches = array_chunk($entries, $this->batchSize);
                
                // Create a job for each batch of entries
                $jobIndex = 0;
                foreach ($batches as $entryBatch) {
                    $entryIds = array_map(function($entry) {
                        return $entry->id;
                    }, $entryBatch);
                    
                    // Add a job to check links in this batch
                    Craft::$app->queue->push(new CheckBrokenLinksJob([
                        'description' => "Checking links in batch " . ($jobIndex + 1) . " of " . count($batches),
                        'scanId' => $this->scanId,
                        'entryIds' => $entryIds,
                        'baseUrl' => $this->baseUrl,
                        'totalBatches' => count($batches),
                        'batchIndex' => $jobIndex,
                    ]));
                    
                    $jobIndex++;
                }
                
                $this->setProgress($queue, 1);
            } else {
                // No entries to process, complete the scan
                $scanRecord->status = ScanHistoryRecord::STATUS_COMPLETED;
                $scanRecord->endTime = new \DateTime();
                $scanRecord->save();
            }
        } catch (\Throwable $e) {
            // Log the error
            Craft::error("Error generating sitemap: " . $e->getMessage(), __METHOD__);
            
            // Update scan history to failed status
            $scanRecord->status = ScanHistoryRecord::STATUS_FAILED;
            $scanRecord->save();
            
            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return 'Generating sitemap of URLs to scan';
    }
}