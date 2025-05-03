<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;

/**
 * BrokenLink model representing a broken link entity.
 */
class BrokenLink extends Model
{
    /**
     * @var int|null ID
     */
    public $id;
    
    /**
     * @var string The broken URL
     */
    public $url;
    
    /**
     * @var string Status description ('Broken', 'Unreachable', etc.)
     */
    public $status;
    
    /**
     * @var int|null HTTP status code if available
     */
    public $statusCode;
    
    /**
     * @var string|null Error message if any
     */
    public $errorMessage;
    
    /**
     * @var int|null Entry ID that contains this link
     */
    public $entryId;
    
    /**
     * @var string|null Entry title that contains this link
     */
    public $entryTitle;
    
    /**
     * @var string|null The text content of the link
     */
    public $linkText;
    
    /**
     * @var string|null The field where the link was found
     */
    public $field;
    
    /**
     * @var string The URL of the page containing the broken link
     */
    public $pageUrl;
    
    /**
     * @var \DateTime When this link was last scanned
     */
    public $lastScanned;

    /**
     * @inheritdoc
     */
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