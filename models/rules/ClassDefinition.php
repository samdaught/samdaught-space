<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class ClassDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'hit_die_sides'], 'required'], ['catalog_entry_id', 'integer', 'min' => 1],
            ['catalog_entry_id', 'unique'], ['hit_die_sides', 'integer', 'min' => 1, 'max' => 32767],
            ['skill_choice_count', 'integer', 'min' => 0, 'max' => 100],
            ['catalog_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['catalog_entry_id' => 'id'], 'filter' => ['type' => 'class']],
            ['hit_die_sides', 'exist', 'targetClass' => HitDieDefinition::class, 'targetAttribute' => ['hit_die_sides' => 'sides']],
        ];
    }
}
