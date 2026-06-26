<?php

namespace craigclement\craftbrokenlinks\records;

use craft\db\ActiveRecord;

/**
 * BrokenLinkRecord represents a broken link in the database.
 *
 * @property int $id
 * @property string $url
 * @property string $status
 * @property int|null $statusCode
 * @property string|null $errorMessage
 * @property int|null $entryId
 * @property string|null $entryTitle
 * @property string|null $linkText
 * @property string|null $field
 * @property string $pageUrl
 * @property \DateTime $lastScanned
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class BrokenLinkRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%brokenlinks_brokenlinks}}';
    }
}
