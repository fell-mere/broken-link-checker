namespace craigclement\craftbrokenlinks\migrations;

use Craft;
use craft\db\Migration;

class m230502_000000_create_broken_links_table extends Migration
{
    public function safeUp(): bool
    {
        // Create the broken_links table
        $this->createTable('{{%broken_links}}', [
            'id' => $this->primaryKey(),
            'url' => $this->string()->notNull(),
            'statusCode' => $this->integer(),
            'error' => $this->text(),
            'lastChecked' => $this->dateTime(),
            'entryId' => $this->integer(),
            'fieldId' => $this->integer(),
            'linkText' => $this->string(),
            'createdAt' => $this->dateTime()->notNull(),
            'updatedAt' => $this->dateTime()->notNull(),
        ]);

        // Add indexes for performance
        $this->createIndex(null, '{{%broken_links}}', 'url');
        $this->createIndex(null, '{{%broken_links}}', 'entryId');

        return true;
    }

    public function safeDown(): bool
    {
        // Drop the broken_links table
        $this->dropTable('{{%broken_links}}');

        return true;
    }
}