<?php

namespace craigclement\craftbrokenlinks\console\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * Command-line controller for Broken Link Checker plugin
 */
class BrokenLinksController extends Controller
{
    /**
     * @var bool Whether to force a full scan regardless of entry update date
     */
    public $forceFullScan = false;
    
    /**
     * @var int The batch size for entries to process in each job
     */
    public $batchSize = 100;
    
    /**
     * @var string The base URL to scan (defaults to primary site's URL)
     */
    public $baseUrl;
    
    /**
     * @var bool Whether to wait for the scan to complete
     */
    public $wait = false;
    
    /**
     * @var int How many seconds to wait before giving up when using --wait
     */
    public $timeout = 3600; // 1 hour default timeout

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        // Global options for all commands
        return array_merge(parent::options($actionID), [
            'forceFullScan', 
            'batchSize', 
            'baseUrl',
            'wait',
            'timeout'
        ]);
    }

    /**
     * Scan the site for broken links
     *
     * @return int Exit code
     */
    public function actionScan(): int
    {
        // Get the base URL if not provided
        if (!$this->baseUrl) {
            $this->baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        }

        $this->stdout("Starting broken link scan with base URL: {$this->baseUrl}\n");
        if ($this->forceFullScan) {
            $this->stdout("Force full scan: Yes\n");
        } else {
            $this->stdout("Force full scan: No (only scanning updated entries)\n");
        }
        
        $this->stdout("Batch size: {$this->batchSize}\n");
        
        // Start the scan
        $service = new BrokenLinksService();
        $scanId = $service->startScan($this->baseUrl, (bool)$this->forceFullScan, (int)$this->batchSize);
        
        $this->stdout("Scan started with ID: {$scanId}\n");
        $this->stdout("Added to queue for processing.\n");
        
        // If requested, wait for the scan to complete
        if ($this->wait) {
            $this->stdout("Waiting for scan to complete...\n");
            
            $startTime = time();
            $completed = false;
            
            while (!$completed && (time() - $startTime) < $this->timeout) {
                // Check the status
                $scanRecord = ScanHistoryRecord::findOne($scanId);
                
                if (!$scanRecord) {
                    $this->stderr("Error: Scan record not found.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
                
                // Display progress
                $status = $scanRecord->status;
                $this->stdout("Status: {$status}\n");
                
                if ($status === ScanHistoryRecord::STATUS_COMPLETED) {
                    $this->stdout("Scan completed!\n");
                    $this->stdout("Total URLs scanned: {$scanRecord->totalUrlsScanned}\n");
                    $this->stdout("Total broken links found: {$scanRecord->totalBrokenLinks}\n");
                    $completed = true;
                    break;
                } elseif ($status === ScanHistoryRecord::STATUS_FAILED) {
                    $this->stderr("Scan failed.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
                
                // Wait a bit before checking again
                sleep(5);
            }
            
            if (!$completed) {
                $this->stderr("Timeout waiting for scan to complete.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } else {
            $this->stdout("Use the following command to check scan status:\n");
            $this->stdout("./craft broken-links/status {$scanId}\n");
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Check the status of a scan
     * 
     * @param int|null $scanId The ID of the scan to check (optional, defaults to latest scan)
     * @return int Exit code
     */
    public function actionStatus(?int $scanId = null): int
    {
        $service = new BrokenLinksService();
        
        if ($scanId === null) {
            $scan = $service->getLatestScan();
            if (!$scan) {
                $this->stderr("No scans found.\n");
                return ExitCode::DATAERR;
            }
            $scanId = $scan->id;
        } else {
            $scan = ScanHistoryRecord::findOne($scanId);
            if (!$scan) {
                $this->stderr("Scan with ID {$scanId} not found.\n");
                return ExitCode::DATAERR;
            }
        }
        
        $this->stdout("Scan ID: {$scan->id}\n");
        $this->stdout("Status: {$scan->status}\n");
        $this->stdout("Start time: {$scan->startTime->format('Y-m-d H:i:s')}\n");
        
        if ($scan->endTime) {
            $this->stdout("End time: {$scan->endTime->format('Y-m-d H:i:s')}\n");
        }
        
        $this->stdout("Total URLs scanned: {$scan->totalUrlsScanned}\n");
        $this->stdout("Total broken links found: {$scan->totalBrokenLinks}\n");
        
        return ExitCode::OK;
    }
    
    /**
     * Clear all broken links data
     * 
     * @return int Exit code
     */
    public function actionClearData(): int
    {
        $this->stdout("Clearing all broken links data...\n");
        
        $service = new BrokenLinksService();
        $success = $service->clearAllData();
        
        if ($success) {
            $this->stdout("All broken links data cleared successfully.\n");
            return ExitCode::OK;
        } else {
            $this->stderr("Failed to clear broken links data.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}