<?php

declare(strict_types=1);

namespace app\models\characters;

use yii\db\ActiveRecord;

class CharacterInventoryState extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['character_id', 'revision'], 'required'], [['character_id', 'revision'], 'integer', 'min' => 1],
            ['character_id', 'unique'], ['character_id', 'exist', 'targetClass' => Character::class, 'targetAttribute' => ['character_id' => 'id']],
        ];
    }
}
