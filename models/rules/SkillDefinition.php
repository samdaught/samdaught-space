<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class SkillDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'ability_code'], 'required'],
            ['catalog_entry_id', 'integer', 'min' => 1],
            ['catalog_entry_id', 'unique'],
            ['ability_code', 'string', 'max' => 3],
            ['catalog_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['catalog_entry_id' => 'id'], 'filter' => ['type' => 'skill']],
            ['ability_code', 'exist', 'targetClass' => AbilityDefinition::class,
                'targetAttribute' => ['ability_code' => 'code']],
        ];
    }
}
