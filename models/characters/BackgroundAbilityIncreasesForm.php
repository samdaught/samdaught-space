<?php

declare(strict_types=1);

namespace app\models\characters;

use yii\base\Model;

class BackgroundAbilityIncreasesForm extends Model
{
    public const ALLOWED_ABILITY_COUNT = 3;
    public const POINT_BUDGET = 3;
    public const MAX_PER_ABILITY = 2;

    public $increases = null;

    public array $allowedAbilityCodes = [];

    public function rules(): array
    {
        return [
            ['increases', 'validateIncreases', 'skipOnEmpty' => false],
        ];
    }

    public function validateIncreases($attribute, $params): void
    {
        $increases = $this->$attribute;

        if (!is_array($increases)) {
            $this->addError($attribute, 'Передайте распределение увеличений. Для пустого выбора используйте пустой массив.');
            return;
        }

        if (count($this->allowedAbilityCodes) !== self::ALLOWED_ABILITY_COUNT
            || count(array_unique($this->allowedAbilityCodes)) !== self::ALLOWED_ABILITY_COUNT) {
            $this->addError($attribute, 'Для выбранной предыстории не настроены три доступные характеристики.');
            return;
        }

        $total = 0;

        foreach ($increases as $code => $amount) {
            if (!in_array($code, $this->allowedAbilityCodes, true)) {
                $this->addError($attribute, 'Распределяйте увеличения только среди характеристик, разрешённых выбранной предысторией.');
                return;
            }

            if (!is_int($amount) || $amount < 1 || $amount > self::MAX_PER_ABILITY) {
                $this->addError($attribute, 'Увеличение одной характеристики должно быть целым числом от 1 до ' . self::MAX_PER_ABILITY . '. Невыбранные характеристики не передавайте.');
                return;
            }

            $total += $amount;
        }

        if ($total > self::POINT_BUDGET) {
            $this->addError($attribute, 'От предыстории можно распределить не больше ' . self::POINT_BUDGET . ' очков увеличений.');
        }
    }
}
