<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\SkillDefinition;
use yii\db\ActiveRecord;

class CharacterClassSkill extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['character_id', 'skill_entry_id'], 'required'],
            [['character_id', 'skill_entry_id'], 'integer', 'min' => 1],
            [['character_id', 'skill_entry_id'], 'unique', 'targetAttribute' => ['character_id', 'skill_entry_id']],
            ['character_id', 'exist', 'targetClass' => CharacterClassSkillChoice::class, 'targetAttribute' => ['character_id' => 'character_id']],
            ['skill_entry_id', 'exist', 'targetClass' => SkillDefinition::class, 'targetAttribute' => ['skill_entry_id' => 'catalog_entry_id']],
        ];
    }
}
