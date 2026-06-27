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
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class ScanHistoryRecord extends ActiveRecord
{
    // Constants
    // =========================================================================

    /**
     * @var string Status for a scan that has been queued but not yet started.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * @var string Status for a scan that is currently running.
     */
    public const STATUS_RUNNING = 'running';

    /**
     * @var string Status for a scan that has finished successfully.
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * @var string Status for a scan that failed.
     */
    public const STATUS_FAILED = 'failed';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%brokenlinks_scanhistory}}';
    }
}
