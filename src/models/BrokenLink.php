<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;

/**
 * Represents a single broken link found during a scan.
 */
class BrokenLink extends Model
{
    public ?int $id = null;
    public string $url = '';
    public string $status = '';
    public ?int $statusCode = null;
    public ?string $errorMessage = null;
    public ?int $entryId = null;
    public ?string $entryTitle = null;
    public ?string $linkText = null;
    public ?string $field = null;
    public string $pageUrl = '';
    public mixed $lastScanned = null;

    public function rules(): array
    {
        return [
            [['url', 'status', 'pageUrl', 'lastScanned'], 'required'],
            [['statusCode', 'entryId'], 'integer'],
            [['errorMessage', 'linkText'], 'string'],
            [['url', 'pageUrl'], 'string', 'max' => 2000],
            [['status', 'entryTitle', 'field'], 'string', 'max' => 255],
        ];
    }
}
