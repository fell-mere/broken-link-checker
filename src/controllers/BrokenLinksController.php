<?php

// Define the namespace for the controller
namespace craigclement\craftbrokenlinks\controllers;

// Import necessary Craft CMS and Yii components
use craft\web\Controller;
use Craft;
use yii\web\Response;

// Define the main controller class for managing broken links
class BrokenLinksController extends Controller
{
    // Ensure only authenticated users can access this controller
    protected array|int|bool $allowAnonymous = false;

    /**
     * **Index Action: Displays the main plugin page in the Control Panel.**
     * 
     * This action is triggered when visiting the `/broken-links` route in the CP.
     * 
     * @return Response The rendered template.
     */
    public function actionIndex(): Response
    {
        $brokenLinks = Craft::$app->get('brokenLinksService')->getBrokenLinks();

        return $this->renderTemplate('broken-links/index', [
            'brokenLinks' => $brokenLinks,
        ]);
    }

    /**
     * **Rescan Action: Initiates a rescan for broken links.**
     * 
     * This action is triggered when accessing `/broken-links/rescan`.
     * It requires a POST request and a valid CSRF token.
     * 
     * @return Response Redirects to the posted URL after initiating the rescan.
     */
    public function actionRescan(): Response
    {
        $this->requirePostRequest();
        $this->requireCsrfToken();

        // Trigger a rescan job
        Craft::$app->queue->push(new \craigclement\craftbrokenlinks\jobs\CheckBrokenLinksJob());

        Craft::$app->session->setNotice('Rescan started. Results will be updated shortly.');

        return $this->redirectToPostedUrl();
    }
}
