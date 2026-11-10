<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class ItemDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'kind_code'], 'required'], ['catalog_entry_id', 'integer', 'min' => 1],
            ['catalog_entry_id', 'unique'], ['kind_code', 'string', 'max' => 32],
            ['catalog_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['catalog_entry_id' => 'id'], 'filter' => ['type' => 'item']],
            ['kind_code', 'exist', 'targetClass' => ItemKind::class, 'targetAttribute' => ['kind_code' => 'code']],
        ];
    }
}
