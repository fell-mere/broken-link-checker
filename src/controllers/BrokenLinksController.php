<?php

namespace craigclement\craftbrokenlinks\controllers;

use Craft;
use craft\web\Controller;
use craigclement\craftbrokenlinks\Plugin;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use yii\web\Response;

/**
 * Control-panel controller for starting scans, reporting status, clearing
 * stored data, and exporting broken-link results.
 */
class BrokenLinksController extends Controller
{
    protected array|int|bool $allowAnonymous = [];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        return true;
    }

    public function actionIndex(): Response
    {
        $service = Plugin::getInstance()->getBrokenLinks();

        $latestScan = $service->getLatestScan();
        $brokenLinks = $service->getLatestBrokenLinks(1000);
        $totalBrokenLinks = $service->countBrokenLinks();

        return $this->renderTemplate('brokenlinks/index', [
            'latestScan' => $latestScan,
            'brokenLinks' => $brokenLinks,
            'totalBrokenLinks' => $totalBrokenLinks,
        ]);
    }

    public function actionStartScan(): Response
    {
        $this->requirePostRequest();

        $baseUrl = Craft::$app->request->getBodyParam('url');
        $forceFullScan = (bool)Craft::$app->request->getBodyParam('forceFullScan', false);
        $batchSize = (int)Craft::$app->request->getBodyParam('batchSize', 100);

        if ($batchSize < 1) {
            $batchSize = 100;
        }

        if (!$baseUrl) {
            $baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return $this->asJson([
                'success' => false,
                'message' => 'Invalid URL provided.',
            ]);
        }

        try {
            $scanId = Plugin::getInstance()->getBrokenLinks()->startScan($baseUrl, $forceFullScan, $batchSize);

            return $this->asJson([
                'success' => true,
                'message' => 'Scan started successfully. Check the queue to monitor progress.',
                'scanId' => $scanId,
            ]);
        } catch (\Throwable $e) {
            Craft::error('Error starting scan: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => 'Error starting scan: ' . $e->getMessage(),
            ]);
        }
    }

    public function actionScanStatus(): Response
    {
        $scanId = Craft::$app->request->getQueryParam('scanId');

        if (!$scanId) {
            $scan = Plugin::getInstance()->getBrokenLinks()->getLatestScan();

            if (!$scan) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'No scan found.',
                ]);
            }

            $scanId = $scan->id;
        }

        try {
            $scanRecord = ScanHistoryRecord::findOne($scanId);

            if (!$scanRecord) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Scan not found.',
                ]);
            }

            return $this->asJson([
                'success' => true,
                'status' => $scanRecord->status,
                'startTime' => $scanRecord->startTime,
                'endTime' => $scanRecord->endTime,
                'totalUrlsScanned' => $scanRecord->totalUrlsScanned,
                'totalBrokenLinks' => $scanRecord->totalBrokenLinks,
                'isComplete' => $scanRecord->status === ScanHistoryRecord::STATUS_COMPLETED,
                'isFailed' => $scanRecord->status === ScanHistoryRecord::STATUS_FAILED,
                'isRunning' => in_array($scanRecord->status, [
                    ScanHistoryRecord::STATUS_PENDING,
                    ScanHistoryRecord::STATUS_RUNNING,
                ]),
            ]);
        } catch (\Throwable $e) {
            Craft::error('Error getting scan status: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => 'Error getting scan status: ' . $e->getMessage(),
            ]);
        }
    }

    public function actionClearData(): Response
    {
        $this->requirePostRequest();

        $service = Plugin::getInstance()->getBrokenLinks();

        if ($service->hasActiveScan()) {
            return $this->asJson([
                'success' => false,
                'message' => 'A scan is currently in progress. Wait for it to finish before clearing data.',
            ]);
        }

        try {
            $success = $service->clearAllData();

            return $this->asJson([
                'success' => $success,
                'message' => $success ? 'All broken links data cleared successfully.' : 'Failed to clear data.',
            ]);
        } catch (\Throwable $e) {
            Craft::error('Error clearing data: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => 'Error clearing data: ' . $e->getMessage(),
            ]);
        }
    }

    public function actionExport(): Response
    {
        $format = Craft::$app->request->getQueryParam('format', 'csv');
        $brokenLinks = Plugin::getInstance()->getBrokenLinks()->getLatestBrokenLinks(null);

        $fileName = 'broken-links-' . date('Y-m-d-His');

        if ($format === 'json') {
            $fileName .= '.json';

            $data = [];
            foreach ($brokenLinks as $link) {
                $data[] = [
                    'url' => $link->url,
                    'status' => $link->status,
                    'statusCode' => $link->statusCode,
                    'linkText' => $link->linkText,
                    'pageUrl' => $link->pageUrl,
                    'entryId' => $link->entryId,
                    'entryTitle' => $link->entryTitle,
                    'lastScanned' => $link->lastScanned ? date('Y-m-d H:i:s', strtotime($link->lastScanned)) : null,
                ];
            }

            // JSON_INVALID_UTF8_SUBSTITUTE keeps the export from silently
            // failing on malformed bytes scraped from third-party pages.
            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($content === false) {
                throw new \RuntimeException('Failed to encode broken links as JSON: ' . json_last_error_msg());
            }
            $mimeType = 'application/json';
        } else {
            $fileName .= '.csv';

            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, ['URL', 'Status', 'Status Code', 'Link Text', 'Page URL', 'Entry ID', 'Entry Title', 'Last Scanned']);
            foreach ($brokenLinks as $link) {
                fputcsv($fh, [
                    $link->url,
                    $link->status,
                    $link->statusCode,
                    $link->linkText,
                    $link->pageUrl,
                    $link->entryId,
                    $link->entryTitle,
                    $link->lastScanned ? date('Y-m-d H:i:s', strtotime($link->lastScanned)) : '',
                ]);
            }
            rewind($fh);
            $content = stream_get_contents($fh);
            fclose($fh);
            $mimeType = 'text/csv';
        }

        $response = Craft::$app->getResponse();
        $response->content = $content;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
