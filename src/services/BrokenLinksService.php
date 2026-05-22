<?php

namespace craigclement\craftbrokenlinks\services;

// Import required libraries
use Craft;
use yii\base\Component;
use craigclement\craftbrokenlinks\records\BrokenLinkRecord;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use craigclement\craftbrokenlinks\models\BrokenLink;
use craigclement\craftbrokenlinks\models\ScanHistory;
use craigclement\craftbrokenlinks\jobs\GenerateSitemapJob;

/**
 * Service to check for broken links in Craft CMS entries.
 */
class BrokenLinksService extends Component
{
    /**
     * Start a new scan for broken links using queue jobs.
     *
     * @param string|null $baseUrl The base URL of the website.
     * @param bool $forceFullScan Whether to force a full scan of all entries.
     * @param int $batchSize Maximum number of entries to process in a batch.
     * @return int The ID of the newly created scan.
     */
    public function startScan(?string $baseUrl = null, bool $forceFullScan = false, int $batchSize = 100): int
    {
        // Create a new scan history record
        $scanRecord = new ScanHistoryRecord();
        $scanRecord->startTime = new \DateTime();
        $scanRecord->status = ScanHistoryRecord::STATUS_PENDING;

        if (!$scanRecord->save()) {
            throw new \RuntimeException('Failed to create scan record: ' . implode(', ', $scanRecord->getFirstErrors()));
        }

        // Get the base URL if not provided
        if (!$baseUrl) {
            $baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        }
        
        // Push the sitemap generation job to the queue
        Craft::$app->queue->push(new GenerateSitemapJob([
            'scanId' => $scanRecord->id,
            'baseUrl' => $baseUrl,
            'forceFullScan' => $forceFullScan,
            'batchSize' => $batchSize,
        ]));
        
        // Return the scan ID
        return $scanRecord->id;
    }
    
    /**
     * Get the latest scan history.
     *
     * @return ScanHistory|null The latest scan history model or null if none exists.
     */
    public function getLatestScan(): ?ScanHistory
    {
        $record = ScanHistoryRecord::find()
            ->orderBy(['startTime' => SORT_DESC])
            ->one();
            
        if (!$record) {
            return null;
        }
        
        // Convert the record to a model
        $model = new ScanHistory();
        $model->setAttributes($record->getAttributes(), false);
        
        return $model;
    }
    
    /**
     * Get the latest scan results (broken links).
     *
     * @param int|null $limit Maximum number of results to return.
     * @param int $offset Offset for pagination.
     * @return array Array of BrokenLink models.
     */
    public function getLatestBrokenLinks(?int $limit = null, int $offset = 0): array
    {
        $query = BrokenLinkRecord::find()
            ->orderBy(['lastScanned' => SORT_DESC]);
            
        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }
        
        $records = $query->all();
        $models = [];
        
        foreach ($records as $record) {
            $model = new BrokenLink();
            $model->setAttributes($record->getAttributes(), false);
            $models[] = $model;
        }
        
        return $models;
    }
    
    /**
     * Count the total number of broken links.
     *
     * @return int Total count of broken links.
     */
    public function countBrokenLinks(): int
    {
        return BrokenLinkRecord::find()->count();
    }
    
    /**
     * Delete all broken links and scan history.
     *
     * @return bool Whether the operation was successful.
     */
    public function clearAllData(): bool
    {
        try {
            BrokenLinkRecord::deleteAll();
            ScanHistoryRecord::deleteAll();
            return true;
        } catch (\Throwable $e) {
            Craft::error('Error clearing broken links data: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
