<?php

// Declare the namespace for this plugin
namespace craigclement\craftbrokenlinks;

// Import necessary Craft CMS classes and components
use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent; // register a route in the front-end
use craft\web\UrlManager; // register a route in the control panel
use craft\events\RegisterCpNavItemsEvent; // register a navigation item in the control panel
use craft\web\twig\variables\Cp; // access to control panel functions
use craft\console\Application as ConsoleApplication;
use craft\console\controllers\ResaveController;
use craft\services\Dashboard;
use craft\events\RegisterComponentTypesEvent;
use craft\i18n\PhpMessageSource;
use craigclement\craftbrokenlinks\widgets\BrokenLinksWidget;
use yii\base\Event;

// Define the main plugin class, extending Craft's BasePlugin
class Plugin extends BasePlugin
{
    // Define the plugin's schema version for migrations and updates
    public string $schemaVersion = '1.0.0';
    
    // Whether the plugin has a settings component
    public bool $hasCpSettings = true;

    // This method runs when the plugin is initialized
    public function init(): void
    {
        // Call the parent class's initialization method
        parent::init();

        // Register translations
        Craft::$app->i18n->translations['broken-links'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'allowOverrides' => true,
            'forceTranslation' => true,
        ];

        // Register a Control Panel (CP) route for the plugin's index page
        Event::on(
            UrlManager::class,                        // Target the CP URL manager
            UrlManager::EVENT_REGISTER_CP_URL_RULES, // Listen for CP route registration
            function (RegisterUrlRulesEvent $event) {
                // Define a CP route for the Broken Links plugin index page
                $event->rules['brokenlinks'] = 'brokenlinks/broken-links/index';
                $event->rules['brokenlinks/start-scan'] = 'brokenlinks/broken-links/start-scan';
                $event->rules['brokenlinks/scan-status'] = 'brokenlinks/broken-links/scan-status';
                $event->rules['brokenlinks/clear-data'] = 'brokenlinks/broken-links/clear-data';
                $event->rules['brokenlinks/export'] = 'brokenlinks/broken-links/export';
            }
        );

        // Register dashboard widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = BrokenLinksWidget::class;
            }
        );

        // Register console commands if in console request context
        if (Craft::$app instanceof ConsoleApplication) {
            $this->registerConsoleCommands();
        }

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
    
    /**
     * Register console commands for this plugin.
     */
    private function registerConsoleCommands(): void
    {
        // Add our console commands to the application's controller map
        Craft::$app->controllerMap['broken-links'] = 'craigclement\craftbrokenlinks\console\controllers\BrokenLinksController';
    }

    /**
     * Installation migration.
     * @return array Array of migration classes to run during installation.
     */
    public function defineMigrations(): array
    {
        return [
            'Install' => 'craigclement\craftbrokenlinks\migrations\Install',
        ];
    }
}
