<?php

namespace craigclement\craftbrokenlinks\records;

use craft\db\ActiveRecord;

/**
 * ScanHistoryRecord represents a link scan history record.
 *
 * @property int $id
 * @property \DateTime $startTime
 * @property \DateTime|null $endTime
 * @property int $totalUrlsScanned
 * @property int $totalBrokenLinks
 * @property int $completedBatches
 * @property string $status
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ScanHistoryRecord extends ActiveRecord
{
    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%brokenlinks_scanhistory}}';
    }
}
