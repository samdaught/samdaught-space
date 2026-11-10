<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\{AbilityDefinition, HitDieDefinition};
use yii\base\Model;

class ClassDefinitionForm extends Model
{
    public const REQUIRED_SAVING_THROW_COUNT = 2;
    public $class_hit_die_sides = null;
    public $class_saving_throw_codes = null;

    public function rules(): array
    {
        return [
            ['class_hit_die_sides', 'validateHitDie', 'skipOnEmpty' => false],
            ['class_saving_throw_codes', 'validateSavingThrows', 'skipOnEmpty' => false],
        ];
    }

    public function validateHitDie($attribute, $params): void
    {
        $value = $this->$attribute;
        if (!is_int($value) || !in_array($value, HitDieDefinition::find()->select('sides')->column(), true)) {
            $this->addError($attribute, 'Выберите кость хитов из справочника.');
        }
    }

    public function validateSavingThrows($attribute, $params): void
    {
        $codes = $this->$attribute;
        if (!is_array($codes) || !array_is_list($codes) || count($codes) !== self::REQUIRED_SAVING_THROW_COUNT) {
            $this->addError($attribute, 'Выберите два разных владения спасбросками класса.');
            return;
        }
        $allowed = AbilityDefinition::find()->select('code')->column();
        foreach ($codes as $code) {
            if (!is_string($code) || !in_array($code, $allowed, true)) {
                $this->addError($attribute, 'Выберите характеристики спасбросков из справочника.');
                return;
            }
        }
        if (count(array_unique($codes)) !== self::REQUIRED_SAVING_THROW_COUNT) {
            $this->addError($attribute, 'Владения спасбросками не должны повторяться.');
        }
    }
}
