<?php

namespace craigclement\craftbrokenlinks;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use craft\console\Application as ConsoleApplication;
use craft\services\Dashboard;
use craft\events\RegisterComponentTypesEvent;
use craft\i18n\PhpMessageSource;
use craigclement\craftbrokenlinks\console\controllers\BrokenLinksController as ConsoleBrokenLinksController;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use craigclement\craftbrokenlinks\widgets\BrokenLinksWidget;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'brokenLinks' => BrokenLinksService::class,
        ]);

        Craft::$app->i18n->translations['broken-links'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'allowOverrides' => true,
            'forceTranslation' => true,
        ];

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['brokenlinks'] = 'brokenlinks/broken-links/index';
                $event->rules['brokenlinks/start-scan'] = 'brokenlinks/broken-links/start-scan';
                $event->rules['brokenlinks/scan-status'] = 'brokenlinks/broken-links/scan-status';
                $event->rules['brokenlinks/clear-data'] = 'brokenlinks/broken-links/clear-data';
                $event->rules['brokenlinks/export'] = 'brokenlinks/broken-links/export';
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = BrokenLinksWidget::class;
            }
        );

        if (Craft::$app instanceof ConsoleApplication) {
            $this->registerConsoleCommands();
        }

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'brokenlinks',
                    'label' => Craft::t('broken-links', 'Broken Links'),
                    'icon' => '@appicons/link.svg',
                ];
            }
        );

        Craft::info('Broken Links plugin loaded', __METHOD__);
    }

    private function registerConsoleCommands(): void
    {
        Craft::$app->controllerMap['broken-links'] = ConsoleBrokenLinksController::class;
    }
}
