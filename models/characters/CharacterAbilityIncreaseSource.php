<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class CharacterAbilityIncreaseSource extends ActiveRecord
{
    public const KIND_BACKGROUND = 'background';
    public const KIND_LEVEL = 'level';
    public const BACKGROUND_SLOT = 'background';

    public function rules(): array
    {
        $isMissingValue = static fn ($value): bool => $value === null || $value === '';
        $isBackground = static fn (self $model): bool => $model->source_kind === self::KIND_BACKGROUND;
        $isLevel = static fn (self $model): bool => $model->source_kind === self::KIND_LEVEL;

        return [
            ['revision', 'default', 'value' => 1, 'isEmpty' => $isMissingValue],
            [['character_id', 'slot', 'source_kind', 'source_entry_id', 'obtained_level', 'revision'], 'required'],
            ['revision', 'integer', 'min' => 1],
            [['character_id', 'source_entry_id'], 'integer', 'min' => 1],
            ['slot', 'string', 'max' => 64],
            ['source_kind', 'in', 'range' => [self::KIND_BACKGROUND, self::KIND_LEVEL], 'strict' => true],
            ['obtained_level', 'integer', 'min' => 1, 'max' => 20],
            ['class_entry_id', 'default', 'value' => null, 'isEmpty' => $isMissingValue],
            ['class_entry_id', 'integer', 'min' => 1, 'isEmpty' => $isMissingValue],
            ['class_entry_id', 'required', 'when' => $isLevel],
            ['class_entry_id', 'in', 'range' => [null], 'strict' => true, 'skipOnEmpty' => false, 'when' => $isBackground],
            ['slot', 'compare', 'compareValue' => self::BACKGROUND_SLOT, 'operator' => '===', 'when' => $isBackground],
            ['obtained_level', 'compare', 'compareValue' => 1, 'type' => 'number', 'when' => $isBackground],
            [
                'character_id', 'exist',
                'targetClass' => Character::class,
                'targetAttribute' => ['character_id' => 'id'],
            ],
            [
                'source_entry_id', 'exist',
                'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['source_entry_id' => 'id'],
            ],
            [
                'source_entry_id', 'exist',
                'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['source_entry_id' => 'id'],
                'filter' => ['type' => 'background'],
                'when' => $isBackground,
            ],
            [
                'class_entry_id', 'exist',
                'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['class_entry_id' => 'id'],
                'filter' => ['type' => 'class'],
            ],
            [
                ['character_id', 'slot'], 'unique',
                'targetAttribute' => ['character_id', 'slot'],
            ],
        ];
    }
}
