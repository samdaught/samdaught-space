<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\AbilityDefinition;
use yii\db\ActiveRecord;

class CharacterAbilityIncrease extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['source_id', 'ability_code', 'amount'], 'required'],
            [['source_id', 'amount'], 'integer', 'min' => 1],
            ['ability_code', 'string', 'max' => 3],
            [
                'source_id', 'exist',
                'targetClass' => CharacterAbilityIncreaseSource::class,
                'targetAttribute' => ['source_id' => 'id'],
            ],
            [
                'ability_code', 'exist',
                'targetClass' => AbilityDefinition::class,
                'targetAttribute' => ['ability_code' => 'code'],
            ],
            [
                ['source_id', 'ability_code'], 'unique',
                'targetAttribute' => ['source_id', 'ability_code'],
            ],
        ];
    }
}
