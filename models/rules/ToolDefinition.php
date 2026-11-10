<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class ToolDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'group_code', 'ability_code'], 'required'],
            ['catalog_entry_id', 'integer', 'min' => 1], ['catalog_entry_id', 'unique'],
            ['group_code', 'string', 'max' => 32], ['ability_code', 'string', 'max' => 3],
            ['catalog_entry_id', 'exist', 'targetClass' => ItemDefinition::class,
                'targetAttribute' => ['catalog_entry_id' => 'catalog_entry_id'], 'filter' => ['kind_code' => ItemKind::TOOL]],
            ['group_code', 'exist', 'targetClass' => ToolGroup::class, 'targetAttribute' => ['group_code' => 'code']],
            ['ability_code', 'exist', 'targetClass' => AbilityDefinition::class, 'targetAttribute' => ['ability_code' => 'code']],
        ];
    }
}
