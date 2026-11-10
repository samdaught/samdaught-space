<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\SpeciesDefinition;
use yii\base\Model;

class SpeciesDefinitionForm extends Model
{
    public $species_speed_feet = null;

    public function rules(): array
    {
        return [['species_speed_feet', 'validateSpeed', 'skipOnEmpty' => false]];
    }

    public function validateSpeed($attribute, $params): void
    {
        $speed = $this->$attribute;
        if (!is_int($speed) || $speed < 0 || $speed > SpeciesDefinition::MAX_SPEED_FEET) {
            $this->addError($attribute, 'Задайте базовую скорость ходьбы целым неотрицательным числом футов в допустимом диапазоне.');
        }
    }
}
