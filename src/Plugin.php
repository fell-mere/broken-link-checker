<?php

// Declare the namespace for this plugin
namespace craigclement\craftbrokenlinks;

// Import necessary Craft CMS classes and components
use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent; // register a utility
use craft\services\Utilities; // access to utilities
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

        // Register the utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = \craigclement\craftbrokenlinks\utilities\BrokenLinksUtility::class;
            }
        );

        // Log a message indicating that the plugin has been successfully loaded
        Craft::info('Broken Links plugin loaded', __METHOD__);
    }
}
