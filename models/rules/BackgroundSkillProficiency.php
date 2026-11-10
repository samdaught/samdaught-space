<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class BackgroundSkillProficiency extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['background_entry_id', 'skill_entry_id'], 'required'],
            [['background_entry_id', 'skill_entry_id'], 'integer', 'min' => 1],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
            ['skill_entry_id', 'exist', 'targetClass' => SkillDefinition::class,
                'targetAttribute' => ['skill_entry_id' => 'catalog_entry_id']],
            ['skill_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['skill_entry_id' => 'id'], 'filter' => ['type' => 'skill']],
            [['background_entry_id', 'skill_entry_id'], 'unique',
                'targetAttribute' => ['background_entry_id', 'skill_entry_id']],
        ];
    }
}
