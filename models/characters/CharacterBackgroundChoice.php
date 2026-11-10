<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\catalog\CatalogEntry;
use app\models\rules\{RuleProfile, ToolDefinition};
use yii\db\ActiveRecord;

class CharacterBackgroundChoice extends ActiveRecord
{
    public const PACKAGE = 'package';
    public const MONEY = 'money';

    public function rules(): array
    {
        return [
            [['character_id', 'background_entry_id', 'rule_profile_id', 'revision'], 'required'],
            [['character_id', 'background_entry_id', 'rule_profile_id', 'tool_entry_id', 'revision'], 'integer', 'min' => 1],
            ['character_id', 'unique'],
            ['character_id', 'exist', 'targetClass' => Character::class, 'targetAttribute' => ['character_id' => 'id']],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
            ['rule_profile_id', 'exist', 'targetClass' => RuleProfile::class, 'targetAttribute' => ['rule_profile_id' => 'id']],
            ['tool_entry_id', 'exist', 'targetClass' => ToolDefinition::class, 'targetAttribute' => ['tool_entry_id' => 'catalog_entry_id']],
            ['equipment_choice', 'in', 'range' => [self::PACKAGE, self::MONEY], 'strict' => true],
        ];
    }
}
