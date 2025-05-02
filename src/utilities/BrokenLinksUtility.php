namespace craigclement\craftbrokenlinks\utilities;

use Craft;
use craft\base\Utility;

class BrokenLinksUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Broken Links');
    }

    public static function id(): string
    {
        return 'broken-links';
    }

    public static function iconPath(): ?string
    {
        return Craft::getAlias('@app/icons/broken-link.svg');
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('broken-links/index');
    }
}