<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\RuleProfile;
use yii\db\ActiveRecord;

class CatalogEntry extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['title', 'body'], 'trim', 'skipOnEmpty' => true],
            ['body', 'required', 'when' => static function (CatalogEntry $entry): bool {
                return (bool) $entry->is_published;
            }],
            [['type', 'slug', 'title', 'origin_profile_id'], 'required'],
            ['type', 'string', 'max' => 32],
            [
                'type',
                'exist',
                'targetClass' => CatalogEntryType::class,
                'targetAttribute' => ['type' => 'code'],
            ],
            ['slug', 'string', 'max' => 100],
            ['title', 'string', 'max' => 255],
            ['origin_profile_id', 'integer'],
            [['type', 'slug'], 'unique', 'targetAttribute' => ['type', 'slug']],
            [
                'origin_profile_id',
                'exist',
                'targetClass' => RuleProfile::class,
                'targetAttribute' => ['origin_profile_id' => 'id']
            ],
            ['body', 'string'],
            ['source_id', 'default', 'value' => null],
            ['source_id', 'integer'],
            [
                'source_id',
                'exist',
                'targetClass' => ContentSource::class,
                'targetAttribute' => ['source_id' => 'id'],
            ],
        ];
    }
}
