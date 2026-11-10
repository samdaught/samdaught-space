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
            [['background_entry_id', 'package_money_cp', 'alternative_money_cp', 'include_background_tool'], 'required'],
            ['background_entry_id', 'integer', 'min' => 1], ['background_entry_id', 'unique'],
            [['package_money_cp', 'alternative_money_cp'], 'integer', 'min' => 0, 'max' => self::MAX_INTEGER],
            ['include_background_tool', 'boolean'],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
        ];
    }
}
