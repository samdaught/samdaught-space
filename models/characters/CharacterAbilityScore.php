<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\AbilityDefinition;
use yii\db\ActiveRecord;

class CharacterAbilityScore extends ActiveRecord
{
    public function rules(): array
    {
        $isMissingValue = static fn ($value): bool => $value === null || $value === '';

        return [
            [['character_id', 'ability_code'], 'required'],
            ['character_id', 'integer', 'min' => 1],
            ['ability_code', 'string', 'max' => 3],
            ['base_score', 'default', 'value' => null, 'isEmpty' => $isMissingValue],
            ['base_score', 'integer', 'min' => 1, 'isEmpty' => $isMissingValue],
            [
                'character_id',
                'exist',
                'targetClass' => Character::class,
                'targetAttribute' => ['character_id' => 'id'],
            ],
            [
                'ability_code',
                'exist',
                'targetClass' => AbilityDefinition::class,
                'targetAttribute' => ['ability_code' => 'code'],
            ],
            [
                ['character_id', 'ability_code'],
                'unique',
                'targetAttribute' => ['character_id', 'ability_code'],
            ],
        ];
    }
}
