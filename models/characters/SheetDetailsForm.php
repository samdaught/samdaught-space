<?php

declare(strict_types=1);

namespace app\models\characters;

use yii\base\Model;

class SheetDetailsForm extends Model
{
    public const MAX_TEXT = 10000;

    public $details = null;

    public static function defaults(): array
    {
        return [
            'armor_class' => null,
            'speed_feet' => null,
            'initiative' => null,
            'spell_attack_bonus' => null,
            'spell_save_dc' => null,
            'size' => '',
            'languages' => '',
            'appearance' => '',
            'biography' => '',
            'resources' => '',
            'notes' => '',
        ];
    }

    public function rules(): array
    {
        return [['details', 'validateDetails', 'skipOnEmpty' => false]];
    }

    public function validateDetails($attribute, $params): void
    {
        if (
            !is_array($this->details)
            || count($this->details) !== count(self::defaults())
            || array_diff(array_keys(self::defaults()), array_keys($this->details))
        ) {
            $this->addError($attribute, 'Передайте все поля листа без дополнительных полей.');
            return;
        }

        foreach (['size', 'languages'] as $key) {
            if (($this->details[$key] ?? '') !== '') {
                $this->addError(
                    $attribute,
                    'Размер и языки определяются архивом и проверяемыми выборами персонажа.',
                );
            }
        }

        foreach (['armor_class', 'speed_feet', 'initiative', 'spell_attack_bonus', 'spell_save_dc'] as $key) {
            $value = $this->details[$key];
            $minimum = in_array($key, ['initiative', 'spell_attack_bonus'], true)
                ? -2147483648
                : ($key === 'speed_feet' ? 0 : 1);

            if (
                $value !== null
                && (!is_int($value) || $value < $minimum || $value > 2147483647)
            ) {
                $this->addError(
                    $attribute,
                    'В числовом поле «' . $key . '» требуется целое число допустимого диапазона или null у черновика.',
                );
            }
        }

        foreach (['size', 'languages', 'appearance', 'biography', 'resources', 'notes'] as $key) {
            if (!is_string($this->details[$key]) || mb_strlen($this->details[$key]) > self::MAX_TEXT) {
                $this->addError(
                    $attribute,
                    'Текстовые поля листа должны быть строками не длиннее ' . self::MAX_TEXT . ' символов.',
                );
            }
        }
    }
}
