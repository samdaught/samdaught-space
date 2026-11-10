<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class ClassSavingThrowProficiency extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['class_entry_id', 'ability_code'], 'required'], ['class_entry_id', 'integer', 'min' => 1],
            ['ability_code', 'string', 'max' => 3],
            [['class_entry_id', 'ability_code'], 'unique', 'targetAttribute' => ['class_entry_id', 'ability_code']],
            ['class_entry_id', 'exist', 'targetClass' => ClassDefinition::class, 'targetAttribute' => ['class_entry_id' => 'catalog_entry_id']],
            ['ability_code', 'exist', 'targetClass' => AbilityDefinition::class, 'targetAttribute' => ['ability_code' => 'code']],
        ];
    }
}
