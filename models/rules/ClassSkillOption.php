<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class ClassSkillOption extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['class_entry_id', 'skill_entry_id'], 'required'],
            [['class_entry_id', 'skill_entry_id'], 'integer', 'min' => 1],
            [['class_entry_id', 'skill_entry_id'], 'unique', 'targetAttribute' => ['class_entry_id', 'skill_entry_id']],
            ['class_entry_id', 'exist', 'targetClass' => ClassDefinition::class, 'targetAttribute' => ['class_entry_id' => 'catalog_entry_id']],
            ['skill_entry_id', 'exist', 'targetClass' => SkillDefinition::class, 'targetAttribute' => ['skill_entry_id' => 'catalog_entry_id']],
        ];
    }
}
