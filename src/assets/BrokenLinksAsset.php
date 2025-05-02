namespace craigclement\craftbrokenlinks\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BrokenLinksAsset extends AssetBundle
{
    public function init()
    {
        // Define the path to the published resources
        $this->sourcePath = '@craigclement/craftbrokenlinks/assets';

        // Define the CSS and JS files to include
        $this->css = ['css/styles.css'];
        $this->js = ['js/main.js'];

        // Define the dependencies
        $this->depends = [CpAsset::class];

        parent::init();
    }
}