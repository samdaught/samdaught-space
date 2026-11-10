<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\AbilityDefinition;
use app\services\characters\StandardArray;
use yii\base\Model;

class AbilityScoresForm extends Model
{
    public $base_scores = null;

    public function rules(): array
    {
        return [
            ['base_scores', 'validateBaseScores', 'skipOnEmpty' => false],
        ];
    }

    public function validateBaseScores($attribute, $params): void
    {
        $scores = $this->$attribute;

        if (!is_array($scores)) {
            $this->addError($attribute, 'Передайте значения характеристик. Незавершённые характеристики можно оставить нераспределёнными.');
            return;
        }

        $allowedCodes = AbilityDefinition::find()
            ->select('code')
            ->column();

        if (count($scores) !== count($allowedCodes)) {
            $message = count($scores) < count($allowedCodes)
                ? 'Переданы не все характеристики. Укажите каждую характеристику или оставьте её нераспределённой.'
                : 'В распределении есть лишние характеристики. Обновите страницу и повторите попытку.';
            $this->addError($attribute, $message);
            return;
        }

        foreach ($allowedCodes as $code) {
            if (!array_key_exists($code, $scores)) {
                $this->addError($attribute, 'Состав характеристик не совпадает со справочником: есть пропущенная или неизвестная характеристика. Обновите страницу и повторите попытку.');
                return;
            }
        }

        $standardArray = new StandardArray();

        if (!$standardArray->isValidAllocation($scores)) {
            $this->addError(
                $attribute,
                'Используйте только целые числа ' . implode(', ', StandardArray::VALUES)
                . ', каждое не более одного раза. Незавершённые характеристики можно оставить нераспределёнными.'
            );
            return;
        }
    }
}
