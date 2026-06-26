<?php

namespace craigclement\craftbrokenlinks\migrations;

use craft\db\Migration;

/**
 * Adds the completedBatches counter (used to detect when every batch of a scan
 * has finished) and a foreign key from broken-link rows to their entry.
 */
class m260626_120000_completed_batches_and_entry_fk extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $scanHistory = '{{%brokenlinks_scanhistory}}';
        if (!$this->db->columnExists($scanHistory, 'completedBatches')) {
            $this->addColumn(
                $scanHistory,
                'completedBatches',
                $this->integer()->notNull()->defaultValue(0)->after('totalBrokenLinks')
            );
        }

        // Add the entry foreign key if one isn't already present.
        $brokenLinks = '{{%brokenlinks_brokenlinks}}';
        $existingFks = $this->db->getSchema()->getTableForeignKeys($brokenLinks, true);
        $hasEntryFk = false;
        foreach ($existingFks as $fk) {
            if (in_array('entryId', $fk->columnNames, true)) {
                $hasEntryFk = true;
                break;
            }
        }

        if (!$hasEntryFk) {
            // Null out any orphaned entryIds first so the constraint can be added.
            $this->execute(
                "UPDATE $brokenLinks SET [[entryId]] = NULL " .
                "WHERE [[entryId]] IS NOT NULL AND [[entryId]] NOT IN (SELECT [[id]] FROM {{%elements}})"
            );

            $this->addForeignKey(
                null,
                $brokenLinks,
                'entryId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260626_120000_completed_batches_and_entry_fk cannot be reverted.\n";
        return false;
    }
}
