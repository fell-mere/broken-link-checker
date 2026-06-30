<?php

namespace craigclement\craftbrokenlinks\controllers;

use Craft;
use craft\web\Controller;
use craigclement\craftbrokenlinks\models\Settings;
use craigclement\craftbrokenlinks\Plugin;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use yii\web\Response;

/**
 * Control-panel controller for starting scans, reporting status, clearing
 * stored data, and exporting broken-link results.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class BrokenLinksController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \yii\web\ForbiddenHttpException if the user lacks the manage permission.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        return true;
    }

    /**
     * Renders the broken-links index page with the latest scan and results.
     */
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

    /**
     * Starts a new broken-link scan and returns the result as JSON.
     *
     * @throws \yii\web\BadRequestHttpException if the request is not a POST request.
     */
    public function actionStartScan(): Response
    {
        $this->requirePostRequest();

        $forceFullScan = (bool)Craft::$app->request->getBodyParam('forceFullScan', false);
        $batchSize = (int)Craft::$app->request->getBodyParam('batchSize', 100);

        if ($batchSize < 1) {
            $batchSize = 100;
        }

        try {
            $scanId = Plugin::getInstance()->getBrokenLinks()->startScan($forceFullScan, $batchSize);

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

    /**
     * Returns the status of a scan (by `scanId`, or the latest) as JSON.
     */
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

    /**
     * Clears all stored broken-link data and returns the result as JSON.
     *
     * @throws \yii\web\BadRequestHttpException if the request is not a POST request.
     */
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

    /**
     * Renders the ignore-list settings form (GET) or saves the submitted
     * ignored URL patterns (POST).
     *
     * @throws \yii\base\InvalidConfigException if the plugin settings model is misconfigured.
     */
    public function actionSettings(): Response
    {
        $plugin = Plugin::getInstance();
        /** @var Settings $settings */
        $settings = $plugin->getSettings();

        if ($this->request->getIsPost()) {
            $patterns = (string)$this->request->getBodyParam('ignoredUrlPatterns', '');
            $lines = array_filter(array_map('trim', explode("\n", $patterns)));
            $settings->ignoredUrlPatterns = array_values($lines);

            if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes())) {
                Craft::$app->getSession()->setError(Craft::t('broken-links', 'Couldn’t save settings.'));

                return $this->renderTemplate('brokenlinks/settings', [
                    'settings' => $settings,
                ]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('broken-links', 'Settings saved.'));

            return $this->redirectToPostedUrl();
        }

        return $this->renderTemplate('brokenlinks/settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Adds the domain of the given URL to the ignored-patterns list and
     * removes any existing broken-link records for that domain.
     *
     * @throws \yii\web\BadRequestHttpException if the request is not a POST request.
     */
    public function actionIgnoreUrl(): Response
    {
        $this->requirePostRequest();

        $url = (string)Craft::$app->getRequest()->getBodyParam('url', '');

        if ($url === '') {
            return $this->asJson(['success' => false, 'message' => 'No URL provided.']);
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return $this->asJson(['success' => false, 'message' => 'Could not parse host from URL.']);
        }

        $plugin = Plugin::getInstance();
        /** @var \craigclement\craftbrokenlinks\models\Settings $settings */
        $settings = $plugin->getSettings();
        $patterns = $settings->ignoredUrlPatterns;

        if (!in_array($host, $patterns, true)) {
            $patterns[] = $host;
            $settings->ignoredUrlPatterns = $patterns;

            if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes())) {
                return $this->asJson(['success' => false, 'message' => 'Failed to save ignore list.']);
            }
        }

        // Remove existing broken-link records for this domain so they
        // disappear immediately without waiting for the next scan.
        try {
            \craigclement\craftbrokenlinks\records\BrokenLinkRecord::deleteAll(['like', 'url', '%' . $host . '%', false]);
        } catch (\Throwable $e) {
            Craft::error('Error removing ignored links: ' . $e->getMessage(), __METHOD__);
        }

        return $this->asJson(['success' => true, 'pattern' => $host]);
    }

    /**
     * Exports the broken-link results as a downloadable CSV file.
     */
    public function actionExport(): Response
    {
        $brokenLinks = Plugin::getInstance()->getBrokenLinks()->getLatestBrokenLinks(null);

        $fileName = 'broken-links-' . date('Y-m-d-His') . '.csv';

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

        $response = Craft::$app->getResponse();
        $response->content = $content;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
