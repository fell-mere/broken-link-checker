<?php

// Declare the namespace for this plugin
namespace craigclement\craftbrokenlinks;

// Import necessary Craft CMS classes and components
use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent; // register a route in the front-end
use craft\web\UrlManager; // register a route in the control panel
use craft\events\RegisterCpNavItemsEvent; // register a navigation item in the control panel
use craft\web\twig\variables\Cp; // acces to control panel functions
use yii\base\Event;

// Define the main plugin class, extending Craft's BasePlugin
class Plugin extends BasePlugin
{
    // Define the plugin's schema version for migrations and updates
    public string $schemaVersion = '1.0.0';

    // This method runs when the plugin is initialized
    public function init(): void
    {
        // Call the parent class's initialization method
        parent::init();


        // Register the service
        $this->setComponents([
            'brokenLinksService' => \craigclement\craftbrokenlinks\services\BrokenLinksService::class,
        ]);

        // Register a Control Panel (CP) route for the plugin's index page
        Event::on(
            UrlManager::class,                        // Target the CP URL manager
            UrlManager::EVENT_REGISTER_CP_URL_RULES, // Listen for CP route registration
            function (RegisterUrlRulesEvent $event) {
                // Define a CP route for the Broken Links plugin index page
                $event->rules['brokenlinks'] = 'brokenlinks/broken-links/index';
                $event->rules['brokenlinks/queue-test-job'] = 'brokenlinks/broken-links/queue-test-job';
                $event->rules['brokenlinks/run-crawl'] = 'brokenlinks/broken-links/run-crawl';
                $event->rules['brokenlinks/get-results'] = 'brokenlinks/broken-links/get-results';
                
            }
        );

        //Register a front-end route for the crawling action
        Event::on(
            UrlManager::class,                        // Target the front-end URL manager
            UrlManager::EVENT_REGISTER_SITE_URL_RULES, // Listen for front-end route registration
            function (RegisterUrlRulesEvent $event) {
                // Define a front-end route for triggering the crawl action
                $event->rules['brokenlinks/run-crawl'] = 'brokenlinks/broken-links/run-crawl';
                $event->rules['brokenlinks/get-results'] = 'brokenlinks/broken-links/get-results';
            }
        );

        // Add a navigation item to the Craft Control Panel
        Event::on(
            Cp::class,                            // Target the Control Panel navigation
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,     // Listen for nav item registration
            function (RegisterCpNavItemsEvent $event) {
                // Add the "Broken Links" menu item to the CP navigation
                $event->navItems[] = [
                    'url' => 'brokenlinks',            // Path to the plugin's main page
                    'label' => 'Broken Links',        // Display label in the menu
                    'icon' => '@appicons/link.svg',   // Optional: Custom icon path
                ];
            }
        );

        // Log a message indicating that the plugin has been successfully loaded
        Craft::info('Broken Links plugin loaded', __METHOD__);
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new \craft\base\Model([
            'batchSize' => 10, // Default batch size
        ]);
    }

    public function settingsHtml(): ?string
    {
        return \Craft::$app->getView()->renderTemplate('brokenlinks/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
