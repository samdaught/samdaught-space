<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\{ClassDefinition, RuleProfile};
use yii\db\ActiveRecord;

class CharacterClassSkillChoice extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['character_id', 'class_entry_id', 'rule_profile_id', 'revision'], 'required'],
            [['character_id', 'class_entry_id', 'rule_profile_id', 'revision'], 'integer', 'min' => 1],
            ['character_id', 'unique'],
            ['character_id', 'exist', 'targetClass' => Character::class, 'targetAttribute' => ['character_id' => 'id']],
            ['class_entry_id', 'exist', 'targetClass' => ClassDefinition::class, 'targetAttribute' => ['class_entry_id' => 'catalog_entry_id']],
            ['rule_profile_id', 'exist', 'targetClass' => RuleProfile::class, 'targetAttribute' => ['rule_profile_id' => 'id']],
        ];
    }
}
