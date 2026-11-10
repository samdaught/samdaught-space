<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class BackgroundFeat extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['background_entry_id', 'feat_entry_id'], 'required'],
            [['background_entry_id', 'feat_entry_id'], 'integer', 'min' => 1],
            ['background_entry_id', 'unique'],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
            ['feat_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['feat_entry_id' => 'id'], 'filter' => ['type' => 'feat']],
            ['feat_entry_id', 'exist', 'targetClass' => FeatDefinition::class,
                'targetAttribute' => ['feat_entry_id' => 'catalog_entry_id'], 'filter' => ['category_code' => FeatCategory::ORIGIN]],
        ];
    }
}
