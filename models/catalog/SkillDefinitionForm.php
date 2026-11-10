<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\AbilityDefinition;
use yii\base\Model;

class SkillDefinitionForm extends Model
{
    public $skill_ability_code = null;

    public function rules(): array
    {
        return [
            ['skill_ability_code', 'required', 'message' => 'Выберите характеристику навыка по умолчанию.'],
            ['skill_ability_code', 'string', 'max' => 3],
            ['skill_ability_code', 'exist', 'targetClass' => AbilityDefinition::class,
                'targetAttribute' => ['skill_ability_code' => 'code'],
                'message' => 'Выберите характеристику из справочника.'],
        ];
    }
}
