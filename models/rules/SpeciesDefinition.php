<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class SpeciesDefinition extends ActiveRecord
{
    public const MAX_SPEED_FEET = 2147483647;

    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'speed_feet'], 'required'], ['catalog_entry_id', 'integer', 'min' => 1],
            ['catalog_entry_id', 'unique'], ['speed_feet', 'integer', 'min' => 0, 'max' => self::MAX_SPEED_FEET],
            ['catalog_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['catalog_entry_id' => 'id'], 'filter' => ['type' => 'species']],
        ];
    }
}
