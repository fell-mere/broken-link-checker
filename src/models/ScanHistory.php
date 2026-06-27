<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * Represents the history and summary of a single broken-link scan run.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class ScanHistory extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null The scan record ID.
     */
    public ?int $id = null;

    /**
     * @var mixed The date and time the scan started.
     */
    public mixed $startTime = null;

    /**
     * @var mixed The date and time the scan finished, if it has.
     */
    public mixed $endTime = null;

    /**
     * @var int The total number of URLs scanned.
     */
    public int $totalUrlsScanned = 0;

    /**
     * @var int The total number of broken links found.
     */
    public int $totalBrokenLinks = 0;

    /**
     * @var int The number of batches completed so far.
     */
    public int $completedBatches = 0;

    /**
     * @var string The current scan status.
     */
    public string $status = ScanHistoryRecord::STATUS_PENDING;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['startTime', 'status'], 'required'],
            [['totalUrlsScanned', 'totalBrokenLinks', 'completedBatches'], 'integer'],
            ['status', 'string'],
        ];
    }
}
