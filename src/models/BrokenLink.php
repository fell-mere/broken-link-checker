<?php

namespace craigclement\craftbrokenlinks\models;

use craft\base\Model;

/**
 * Represents a single broken link found during a scan.
 *
 * @author Fell Mere
 * @since 1.0.0
 */
class BrokenLink extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null The record ID.
     */
    public ?int $id = null;

    /**
     * @var string The broken link's URL.
     */
    public string $url = '';

    /**
     * @var string The link status (e.g. "Broken" or "Unreachable").
     */
    public string $status = '';

    /**
     * @var int|null The HTTP status code returned for the link, if any.
     */
    public ?int $statusCode = null;

    /**
     * @var string|null The error message captured when the link was unreachable.
     */
    public ?string $errorMessage = null;

    /**
     * @var int|null The ID of the entry the link was found on.
     */
    public ?int $entryId = null;

    /**
     * @var string|null The title of the entry the link was found on.
     */
    public ?string $entryTitle = null;

    /**
     * @var string|null The anchor text of the link.
     */
    public ?string $linkText = null;

    /**
     * @var string|null The field the link was found in, if known.
     */
    public ?string $field = null;

    /**
     * @var string The URL of the page the link was found on.
     */
    public string $pageUrl = '';

    /**
     * @var mixed The date the link was last scanned.
     */
    public mixed $lastScanned = null;

    // Public Methods
    // =========================================================================

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
