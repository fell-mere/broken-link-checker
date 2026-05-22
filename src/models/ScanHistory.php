<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;

class ScanHistory extends Model
{
    public ?int $id = null;
    public mixed $startTime = null;
    public mixed $endTime = null;
    public int $totalUrlsScanned = 0;
    public int $totalBrokenLinks = 0;
    public string $status = ScanHistoryRecord::STATUS_PENDING;

    public function rules(): array
    {
        return [
            [['startTime', 'status'], 'required'],
            [['totalUrlsScanned', 'totalBrokenLinks'], 'integer'],
            ['status', 'string'],
        ];
    }
}
