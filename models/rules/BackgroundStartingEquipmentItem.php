<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class BackgroundStartingEquipmentItem extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['background_entry_id', 'item_entry_id', 'quantity'], 'required'],
            [['background_entry_id', 'item_entry_id', 'quantity'], 'integer', 'min' => 1, 'max' => BackgroundStartingEquipment::MAX_INTEGER],
            [['background_entry_id', 'item_entry_id'], 'unique', 'targetAttribute' => ['background_entry_id', 'item_entry_id']],
            ['background_entry_id', 'exist', 'targetClass' => BackgroundStartingEquipment::class,
                'targetAttribute' => ['background_entry_id' => 'background_entry_id']],
            ['item_entry_id', 'exist', 'targetClass' => ItemDefinition::class,
                'targetAttribute' => ['item_entry_id' => 'catalog_entry_id']],
        ];
    }
}
