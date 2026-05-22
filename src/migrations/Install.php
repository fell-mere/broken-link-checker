<?php

namespace craigclement\craftbrokenlinks\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create the broken_links table
        $this->createTable('{{%brokenlinks_brokenlinks}}', [
            'id' => $this->primaryKey(),
            'url' => $this->string(2000)->notNull(),
            'status' => $this->string(255)->notNull(),
            'statusCode' => $this->integer(),
            'errorMessage' => $this->text(),
            'entryId' => $this->integer(),
            'entryTitle' => $this->string(255),
            'linkText' => $this->text(),
            'field' => $this->string(255),
            'pageUrl' => $this->string(2000)->notNull(),
            'lastScanned' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Composite unique index — enables atomic upsert and prevents duplicate records
        $this->createIndex(null, '{{%brokenlinks_brokenlinks}}', ['url(191)', 'pageUrl(191)'], true);

        $this->createIndex(null, '{{%brokenlinks_brokenlinks}}', 'entryId');

        // Create the scan history table
        $this->createTable('{{%brokenlinks_scanhistory}}', [
            'id' => $this->primaryKey(),
            'startTime' => $this->dateTime()->notNull(),
            'endTime' => $this->dateTime(),
            'totalUrlsScanned' => $this->integer()->defaultValue(0),
            'totalBrokenLinks' => $this->integer()->defaultValue(0),
            'status' => $this->string(255)->notNull()->defaultValue('pending'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop the tables if they exist
        $this->dropTableIfExists('{{%brokenlinks_brokenlinks}}');
        $this->dropTableIfExists('{{%brokenlinks_scanhistory}}');

        return true;
    }
}