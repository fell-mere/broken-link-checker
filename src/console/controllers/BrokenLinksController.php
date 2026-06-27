<?php

namespace craigclement\craftbrokenlinks\console\controllers;

use craigclement\craftbrokenlinks\Plugin;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Command-line controller for Broken Link Checker plugin.
 *
 * Provides commands to start scans, report scan status, and clear stored data.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class BrokenLinksController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool Whether to force a full scan of all entries.
     */
    public bool $forceFullScan = false;

    /**
     * @var int Maximum number of entries to process in a batch.
     */
    public int $batchSize = 100;

    /**
     * @var bool Whether to wait for the scan to complete before returning.
     */
    public bool $wait = false;

    /**
     * @var int Maximum number of seconds to wait for the scan to complete.
     */
    public int $timeout = 3600;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'forceFullScan',
            'batchSize',
            'wait',
            'timeout',
        ]);
    }

    /**
     * Starts a broken-link scan, optionally waiting for it to finish.
     *
     * @throws \RuntimeException if the scan history record cannot be saved.
     */
    public function actionScan(): int
    {
        $this->stdout('Force full scan: ' . ($this->forceFullScan ? 'Yes' : 'No (only scanning updated entries)') . "\n");
        $this->stdout("Batch size: {$this->batchSize}\n");

        $scanId = Plugin::getInstance()->getBrokenLinks()->startScan($this->forceFullScan, $this->batchSize);

        $this->stdout("Scan started with ID: {$scanId}\n");
        $this->stdout("Added to queue for processing.\n");

        if ($this->wait) {
            $this->stdout("Waiting for scan to complete...\n");

            $startTime = time();
            $completed = false;

            while (!$completed && (time() - $startTime) < $this->timeout) {
                $scanRecord = ScanHistoryRecord::findOne($scanId);

                if (!$scanRecord) {
                    $this->stderr("Error: Scan record not found.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                $status = $scanRecord->status;
                $this->stdout("Status: {$status}\n");

                if ($status === ScanHistoryRecord::STATUS_COMPLETED) {
                    $this->stdout("Scan completed!\n");
                    $this->stdout("Total URLs scanned: {$scanRecord->totalUrlsScanned}\n");
                    $this->stdout("Total broken links found: {$scanRecord->totalBrokenLinks}\n");
                    $completed = true;
                    break;
                }

                if ($status === ScanHistoryRecord::STATUS_FAILED) {
                    $this->stderr("Scan failed.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }

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
     * Prints the status and summary of a scan (by ID, or the latest).
     */
    public function actionStatus(?int $scanId = null): int
    {
        if ($scanId === null) {
            $scan = Plugin::getInstance()->getBrokenLinks()->getLatestScan();
            if (!$scan) {
                $this->stderr("No scans found.\n");
                return ExitCode::DATAERR;
            }
        } else {
            $scan = ScanHistoryRecord::findOne($scanId);
            if (!$scan) {
                $this->stderr("Scan with ID {$scanId} not found.\n");
                return ExitCode::DATAERR;
            }
        }

        $startTime = $scan->startTime instanceof \DateTime
            ? $scan->startTime->format('Y-m-d H:i:s')
            : (string) $scan->startTime;

        $this->stdout("Scan ID: {$scan->id}\n");
        $this->stdout("Status: {$scan->status}\n");
        $this->stdout("Start time: {$startTime}\n");

        if ($scan->endTime) {
            $endTime = $scan->endTime instanceof \DateTime
                ? $scan->endTime->format('Y-m-d H:i:s')
                : (string) $scan->endTime;
            $this->stdout("End time: {$endTime}\n");
        }

        $this->stdout("Total URLs scanned: {$scan->totalUrlsScanned}\n");
        $this->stdout("Total broken links found: {$scan->totalBrokenLinks}\n");

        return ExitCode::OK;
    }

    /**
     * Clears all stored broken-link data, unless a scan is in progress.
     */
    public function actionClearData(): int
    {
        $this->stdout("Clearing all broken links data...\n");
        $service = Plugin::getInstance()->getBrokenLinks();

        if ($service->hasActiveScan()) {
            $this->stderr("A scan is currently in progress. Wait for it to finish before clearing data.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $success = $service->clearAllData();

        if ($success) {
            $this->stdout("All broken links data cleared successfully.\n");
            return ExitCode::OK;
        }

        $this->stderr("Failed to clear broken links data.\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
