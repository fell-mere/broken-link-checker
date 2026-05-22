<?php

namespace craigclement\craftbrokenlinks\console\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;
use craigclement\craftbrokenlinks\Plugin;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * Command-line controller for Broken Link Checker plugin
 */
class BrokenLinksController extends Controller
{
    public bool $forceFullScan = false;
    public int $batchSize = 100;
    public string $baseUrl = '';
    public bool $wait = false;
    public int $timeout = 3600;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'forceFullScan',
            'batchSize',
            'baseUrl',
            'wait',
            'timeout',
        ]);
    }

    public function actionScan(): int
    {
        if (!$this->baseUrl) {
            $this->baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        }

        $this->stdout("Starting broken link scan with base URL: {$this->baseUrl}\n");
        $this->stdout('Force full scan: ' . ($this->forceFullScan ? 'Yes' : 'No (only scanning updated entries)') . "\n");
        $this->stdout("Batch size: {$this->batchSize}\n");

        $scanId = Plugin::getInstance()->brokenLinks->startScan($this->baseUrl, $this->forceFullScan, $this->batchSize);

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

    public function actionStatus(?int $scanId = null): int
    {
        if ($scanId === null) {
            $scan = Plugin::getInstance()->brokenLinks->getLatestScan();
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

    public function actionClearData(): int
    {
        $this->stdout("Clearing all broken links data...\n");
        $success = Plugin::getInstance()->brokenLinks->clearAllData();

        if ($success) {
            $this->stdout("All broken links data cleared successfully.\n");
            return ExitCode::OK;
        }

        $this->stderr("Failed to clear broken links data.\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
