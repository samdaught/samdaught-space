<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class FeatDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['catalog_entry_id', 'category_code'], 'required'],
            ['catalog_entry_id', 'integer', 'min' => 1],
            ['catalog_entry_id', 'unique'],
            ['category_code', 'string', 'max' => 32],
            ['catalog_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['catalog_entry_id' => 'id'], 'filter' => ['type' => 'feat']],
            ['category_code', 'exist', 'targetClass' => FeatCategory::class,
                'targetAttribute' => ['category_code' => 'code']],
        ];
    }
}
