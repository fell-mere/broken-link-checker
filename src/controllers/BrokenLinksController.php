<?php

// Define the namespace for the controller
namespace craigclement\craftbrokenlinks\controllers;

// Import necessary Craft CMS and Yii components
use craft\web\Controller;
use craigclement\craftbrokenlinks\Plugin;
use Craft;

// Define the main controller class for managing broken links
class BrokenLinksController extends Controller
{
    // Allow anonymous access to all actions in this controller
    protected array|int|bool $allowAnonymous = true;

    /**
     * **Index Action: Displays the main plugin page in the Control Panel.**
     * 
     * This action is triggered when visiting the `/brokenlinks` route in the CP.
     * 
     * @return string The rendered template.
     */
    public function actionIndex(): string
    {
        // Render the `brokenlinks/index` template (Twig file)
        return $this->renderTemplate('brokenlinks/index');
    }


    /**
 * **Run Crawl Action: Executes the link checking process asynchronously using a queue.**
 * 
 * - This action is triggered when accessing `/brokenlinks/run-crawl`.
 * - It fetches all site URLs and batches them into jobs for asynchronous processing.
 * - The queue jobs will check links in smaller batches to prevent timeouts.
 */
public function actionRunCrawl()
{
    // Set response format to JSON
    Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;

    // Push the GenerateSitemapJob to queue
    Craft::$app->queue->push(new \craigclement\craftbrokenlinks\jobs\GenerateSitemapJob());

    // Return JSON response confirming jobs were added to queue
    return $this->asJson([
        'success' => true,
        'message' => 'Sitemap generation started. Checking for broken links will begin soon.',
    ]);
}


        /**
     * **Queue Test Job Action: Confirms queue processing works.**
     * 
     * This action adds a simple test job to the queue.
     */
    public function actionQueueTestJob()
    {
        Craft::$app->queue->push(new \craigclement\craftbrokenlinks\jobs\TestJob());

        return $this->asJson([
            'success' => true,
            'message' => 'Test job added to the queue.',
        ]);
    }

    /**
 * **Get Results Action: Fetches processed broken links from cache.**
 *
 * - This action is triggered when accessing `/brokenlinks/get-results`.
 * - It retrieves the cached results and returns them as JSON.
 */
    public function actionGetResults()
    {
        // Set response format to JSON
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Retrieve cached broken links and sitemap URLs
        $brokenLinks = Craft::$app->cache->get('brokenLinks_results');
        $sitemapUrls = Craft::$app->cache->get('brokenLinks_urls'); // Fetch sitemap URLs

        $queue = Craft::$app->queue;
        $stillProcessing = $queue->getTotalJobs() > 0;

        return $this->asJson([
            'success' => (bool) $brokenLinks,
            'message' => $brokenLinks ? 'Broken links retrieved successfully.' : 'Results are not ready yet. Try again later.',
            'data' => $brokenLinks ?? [],
            'sitemap_urls' => $sitemapUrls ?? [], // Include sitemap URLs in response
            'stillProcessing' => $stillProcessing, // Indicate if jobs are still processing
        ]);
    }
    
}
