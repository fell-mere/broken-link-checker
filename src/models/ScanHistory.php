<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

/**
 * ScanHistory model representing a link scan history entity.
 */
class ScanHistory extends Model
{
    /**
     * @var int|null ID
     */
    public $id;
    
    /**
     * @var \DateTime When the scan started
     */
    public $startTime;
    
    /**
     * @var \DateTime|null When the scan completed
     */
    public $endTime;
    
    /**
     * @var int Total number of URLs scanned
     */
    public $totalUrlsScanned = 0;
    
    /**
     * @var int Total number of broken links found
     */
    public $totalBrokenLinks = 0;
    
    /**
     * @var string Status of the scan
     */
    public $status = ScanHistoryRecord::STATUS_PENDING;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['startTime', 'status'], 'required'],
            [['totalUrlsScanned', 'totalBrokenLinks'], 'integer'],
            ['status', 'string'],
        ];
    }
}