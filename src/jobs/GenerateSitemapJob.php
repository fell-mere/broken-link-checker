<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * GenerateSitemapJob collects entry IDs and fans out per-batch CheckBrokenLinksJob tasks.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class GenerateSitemapJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var int The ID of the scan this job belongs to.
     */
    public int $scanId;

    /**
     * @var int Maximum number of entries to process in each batch.
     */
    public int $batchSize = 100;

    /**
     * @var bool Whether to force a full scan of all entries.
     */
    public bool $forceFullScan = false;

    /**
     * @var string The base URL of the website being scanned.
     */
    public string $baseUrl = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \Throwable if the scan record is missing or batch fan-out fails.
     */
    public function execute($queue): void
    {
        $scanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$scanRecord) {
            throw new \Exception("Scan history record with ID {$this->scanId} not found.");
        }

        $scanRecord->status = ScanHistoryRecord::STATUS_RUNNING;

        if ($this->forceFullScan) {
            $scanRecord->totalBrokenLinks = 0;
        }

        $scanRecord->save();

        try {
            if (!$this->baseUrl) {
                $this->baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
            }

            $entryQuery = Entry::find();

            if (!$this->forceFullScan) {
                $lastScan = ScanHistoryRecord::find()
                    ->where(['status' => ScanHistoryRecord::STATUS_COMPLETED])
                    ->orderBy(['endTime' => SORT_DESC])
                    ->one();

                if ($lastScan && $lastScan->endTime) {
                    $lastScanTime = $lastScan->endTime instanceof \DateTime
                        ? $lastScan->endTime->format('Y-m-d H:i:s')
                        : (string) $lastScan->endTime;
                    $entryQuery->andWhere(['>', 'elements.dateUpdated', $lastScanTime]);
                }
            }

            // Load only IDs — no need to hydrate full Entry objects or relations here.
            $allEntryIds = $entryQuery->ids();
            $totalEntries = count($allEntryIds);

            $scanRecord->totalUrlsScanned = $totalEntries;
            $scanRecord->save();

            if ($totalEntries > 0) {
                $batches = array_chunk($allEntryIds, $this->batchSize);
                $totalBatches = count($batches);

                foreach ($batches as $batchIndex => $idBatch) {
                    Craft::$app->queue->push(new CheckBrokenLinksJob([
                        'description' => 'Checking links in batch ' . ($batchIndex + 1) . ' of ' . $totalBatches,
                        'scanId' => $this->scanId,
                        'entryIds' => $idBatch,
                        'baseUrl' => $this->baseUrl,
                        'totalBatches' => $totalBatches,
                        'batchIndex' => $batchIndex,
                        'forceFullScan' => $this->forceFullScan,
                    ]));
                }

                $this->setProgress($queue, 1);
            } else {
                $scanRecord->status = ScanHistoryRecord::STATUS_COMPLETED;
                $scanRecord->endTime = new \DateTime();
                $scanRecord->save();
            }
        } catch (\Throwable $e) {
            Craft::error('Error generating sitemap: ' . $e->getMessage(), __METHOD__);
            $scanRecord->status = ScanHistoryRecord::STATUS_FAILED;
            $scanRecord->save();
            throw $e;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return 'Generating sitemap of URLs to scan';
    }
}
