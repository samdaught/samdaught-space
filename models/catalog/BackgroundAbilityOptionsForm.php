<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\AbilityDefinition;
use yii\base\Model;

class BackgroundAbilityOptionsForm extends Model
{
    public const REQUIRED_COUNT = 3;

    public $ability_codes = null;

    public function rules(): array
    {
        return [
            ['ability_codes', 'validateAbilityCodes', 'skipOnEmpty' => false],
        ];
    }

    public function validateAbilityCodes($attribute, $params): void
    {
        $codes = $this->$attribute;

        if (!is_array($codes) || !array_is_list($codes) || count($codes) !== self::REQUIRED_COUNT) {
            $this->addError($attribute, 'Выберите три характеристики, доступные для увеличения от этой предыстории.');
            return;
        }

        $allowedCodes = AbilityDefinition::find()->select('code')->column();

        foreach ($codes as $code) {
            if (!is_string($code) || !in_array($code, $allowedCodes, true)) {
                $this->addError($attribute, 'Выберите каждую характеристику из справочника.');
                return;
            }
        }

        if (count(array_unique($codes)) !== self::REQUIRED_COUNT) {
            $this->addError($attribute, 'Характеристики не должны повторяться. Выберите три разные характеристики.');
        }
    }
}
