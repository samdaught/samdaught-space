<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class BackgroundStartingEquipment extends ActiveRecord
{
    public const DEFAULT_ALTERNATIVE_MONEY_CP = 5000;
    public const MAX_ITEMS = 100;
    public const MAX_INTEGER = 2147483647;

    public function rules(): array
    {
        return [
            ['background_entry_id', 'required'],
            ['background_entry_id', 'integer', 'min' => 1], ['background_entry_id', 'unique'],
            [['package_money_cp', 'alternative_money_cp'], 'integer', 'min' => 0, 'max' => self::MAX_INTEGER],
            ['include_background_tool', 'boolean'],
            ['source_text', 'string', 'min' => 1, 'max' => 10000],
            ['source_text', 'validateRepresentation', 'skipOnEmpty' => false],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
        ];
    }

    public function validateRepresentation(string $attribute, mixed $params): void
    {
        $hasSourceText = trim((string) $this->source_text) !== '';
        $hasStructuredFields = $this->package_money_cp !== null
            || $this->alternative_money_cp !== null
            || $this->include_background_tool !== null;

        if ($hasSourceText === $hasStructuredFields) {
            $this->addError('source_text', 'Укажите либо точный текст комплекта из источника, либо структурированный комплект.');
        }
    }
}
