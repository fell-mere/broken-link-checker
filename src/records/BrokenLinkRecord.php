namespace craigclement\craftbrokenlinks\records;

use craft\db\ActiveRecord;

class BrokenLinkRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%broken_links}}';
    }
}