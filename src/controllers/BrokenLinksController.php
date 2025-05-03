<?php

// Define the namespace for the controller
namespace craigclement\craftbrokenlinks\controllers;

// Import necessary Craft CMS and Yii components
use craft\web\Controller;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use Craft;

// Define the main controller class for managing broken links
class BrokenLinksController extends Controller
{
    // Allow anonymous access to actions that should be accessible without logging in
    protected array|int|bool $allowAnonymous = [];

    /**
     * **Index Action: Displays the main plugin page in the Control Panel.**
     *
     * This action is triggered when visiting the `/brokenlinks` route in the CP.
     *
     * @return string The rendered template.
     */
    public function actionIndex(): string
    {
        $service = new BrokenLinksService();
        
        // Get the latest scan history
        $latestScan = $service->getLatestScan();
        
        // Get the latest broken links (limited to 1000 for performance)
        $brokenLinks = $service->getLatestBrokenLinks(1000);
        
        // Count total broken links
        $totalBrokenLinks = $service->countBrokenLinks();
        
        // Render the template with the data
        return $this->renderTemplate('brokenlinks/index', [
            'latestScan' => $latestScan,
            'brokenLinks' => $brokenLinks,
            'totalBrokenLinks' => $totalBrokenLinks,
        ]);
    }

    /**
     * **Run Scan Action: Starts a new scan for broken links.**
     *
     * This action is triggered when accessing the `/brokenlinks/start-scan` route.
     * It returns the results as a JSON response.
     */
    public function actionStartScan()
    {
        // Require POST request for this action
        $this->requirePostRequest();
        
        // Set the response format to JSON
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Get parameters from the request
        $baseUrl = Craft::$app->request->getBodyParam('url');
        $forceFullScan = (bool)Craft::$app->request->getBodyParam('forceFullScan', false);
        $batchSize = (int)Craft::$app->request->getBodyParam('batchSize', 100);
        
        // Validate the batch size
        if ($batchSize < 1) {
            $batchSize = 100;
        }
        
        // Get the base URL for the primary site if not provided
        if (!$baseUrl) {
            $baseUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        }

        // Validate the URL
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return $this->asJson([
                'success' => false,
                'message' => 'Invalid URL provided.',
            ], 400);
        }
        
        try {
            // Create an instance of the BrokenLinksService
            $service = new BrokenLinksService();
            
            // Start a new scan
            $scanId = $service->startScan($baseUrl, $forceFullScan, $batchSize);
            
            // Return a successful JSON response
            return $this->asJson([
                'success' => true,
                'message' => 'Scan started successfully. Check the queue to monitor progress.',
                'scanId' => $scanId,
            ]);
        } catch (\Throwable $e) {
            // Log any errors encountered during the scan
            Craft::error('Error starting scan: ' . $e->getMessage(), __METHOD__);
            
            // Return an error response
            return $this->asJson([
                'success' => false,
                'message' => 'Error starting scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * **Get Scan Status Action: Retrieves the status of a scan.**
     *
     * This action is triggered when accessing the `/brokenlinks/scan-status` route.
     * It returns the scan status as a JSON response.
     */
    public function actionScanStatus()
    {
        // Set the response format to JSON
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Get the scan ID from the request
        $scanId = Craft::$app->request->getQueryParam('scanId');
        
        if (!$scanId) {
            // If no scan ID provided, get the latest scan
            $service = new BrokenLinksService();
            $scan = $service->getLatestScan();
            
            if (!$scan) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'No scan found.',
                ]);
            }
            
            $scanId = $scan->id;
        }
        
        try {
            // Find the scan record
            $scanRecord = ScanHistoryRecord::findOne($scanId);
            
            if (!$scanRecord) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Scan not found.',
                ]);
            }
            
            // Return the scan status
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
                    ScanHistoryRecord::STATUS_RUNNING
                ]),
            ]);
        } catch (\Throwable $e) {
            // Log any errors
            Craft::error('Error getting scan status: ' . $e->getMessage(), __METHOD__);
            
            // Return an error response
            return $this->asJson([
                'success' => false,
                'message' => 'Error getting scan status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * **Clear Data Action: Clears all saved broken links data.**
     *
     * This action is triggered when accessing the `/brokenlinks/clear-data` route.
     * It returns the result as a JSON response.
     */
    public function actionClearData()
    {
        // Require POST request for this action
        $this->requirePostRequest();
        
        // Set the response format to JSON
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        try {
            // Create an instance of the BrokenLinksService
            $service = new BrokenLinksService();
            
            // Clear all data
            $success = $service->clearAllData();
            
            // Return the result
            return $this->asJson([
                'success' => $success,
                'message' => $success ? 'All broken links data cleared successfully.' : 'Failed to clear data.',
            ]);
        } catch (\Throwable $e) {
            // Log any errors
            Craft::error('Error clearing data: ' . $e->getMessage(), __METHOD__);
            
            // Return an error response
            return $this->asJson([
                'success' => false,
                'message' => 'Error clearing data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
