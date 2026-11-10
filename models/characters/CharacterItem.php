<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\ItemDefinition;
use yii\db\ActiveRecord;

class CharacterItem extends ActiveRecord
{
    public const SOURCE_DRAFT = 'draft';
    public const SOURCE_CREATION = 'creation';
    public const SOURCE_GM = 'gm';
    public const MAX_QUANTITY = 2147483647;
    public const MAX_NOTE_LENGTH = 1000;

    public function rules(): array
    {
        return [
            [['character_id', 'item_entry_id', 'quantity', 'source_kind', 'is_equipped'], 'required'],
            [['character_id', 'item_entry_id'], 'integer', 'min' => 1],
            ['quantity', 'integer', 'min' => 1, 'max' => self::MAX_QUANTITY],
            ['is_equipped', 'boolean'], ['note', 'trim'], ['note', 'string', 'max' => self::MAX_NOTE_LENGTH],
            ['source_kind', 'in', 'range' => [self::SOURCE_DRAFT, self::SOURCE_CREATION, self::SOURCE_GM], 'strict' => true],
            ['character_id', 'exist', 'targetClass' => Character::class, 'targetAttribute' => ['character_id' => 'id']],
            ['item_entry_id', 'exist', 'targetClass' => ItemDefinition::class, 'targetAttribute' => ['item_entry_id' => 'catalog_entry_id']],
        ];
    }
}
