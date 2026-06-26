<?php

namespace craigclement\craftbrokenlinks;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\i18n\PhpMessageSource;
use craft\services\Dashboard;
use craft\services\UserPermissions;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craigclement\craftbrokenlinks\console\controllers\BrokenLinksController as ConsoleBrokenLinksController;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use craigclement\craftbrokenlinks\widgets\BrokenLinksWidget;
use yii\base\Event;

/**
 * Broken Links plugin.
 *
 * @property-read BrokenLinksService $brokenLinks
 */
class Plugin extends BasePlugin
{
    /**
     * Permission required to view and manage broken-link scans.
     */
    public const PERMISSION_MANAGE = 'brokenlinks:manage';

    public string $schemaVersion = '1.1.0';
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
            function(RegisterUrlRulesEvent $event) {
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
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('broken-links', 'Broken Links'),
                    'permissions' => [
                        self::PERMISSION_MANAGE => [
                            'label' => Craft::t('broken-links', 'Manage broken links'),
                        ],
                    ],
                ];
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                if (!Craft::$app->getUser()->checkPermission(self::PERMISSION_MANAGE)) {
                    return;
                }

                $event->navItems[] = [
                    'url' => 'brokenlinks',
                    'label' => Craft::t('broken-links', 'Broken Links'),
                    'icon' => '@appicons/link.svg',
                ];
            }
        );

        Craft::info('Broken Links plugin loaded', __METHOD__);
    }

    /**
     * Returns the broken links service.
     */
    public function getBrokenLinks(): BrokenLinksService
    {
        /** @var BrokenLinksService $service */
        $service = $this->get('brokenLinks');
        return $service;
    }

    private function registerConsoleCommands(): void
    {
        Craft::$app->controllerMap['broken-links'] = ConsoleBrokenLinksController::class;
    }
}
