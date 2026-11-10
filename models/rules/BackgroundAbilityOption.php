<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class BackgroundAbilityOption extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['background_entry_id', 'ability_code'], 'required'],
            ['background_entry_id', 'integer', 'min' => 1],
            ['ability_code', 'string', 'max' => 3],
            [
                'background_entry_id',
                'exist',
                'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'],
                'filter' => ['type' => 'background'],
            ],
            [
                'ability_code',
                'exist',
                'targetClass' => AbilityDefinition::class,
                'targetAttribute' => ['ability_code' => 'code'],
            ],
            [
                ['background_entry_id', 'ability_code'],
                'unique',
                'targetAttribute' => ['background_entry_id', 'ability_code'],
            ],
        ];
    }
}
