<?php

declare(strict_types=1);

namespace app\models\characters;

use yii\base\Model;

class BackgroundChoicesForm extends Model
{
    public $tool_entry_id = null;
    public $equipment_choice = null;
    public bool $requiresToolChoice = false;
    public array $allowedToolIds = [];
    public bool $packageAvailable = false;
    public bool $moneyAvailable = false;

    public function rules(): array
    {
        return [
            ['tool_entry_id', 'validateTool', 'skipOnEmpty' => false],
            ['equipment_choice', 'validateEquipment', 'skipOnEmpty' => false],
        ];
    }

    public function validateTool($attribute, $params): void
    {
        $id = $this->$attribute;
        if ($id === null) {
            return;
        }
        if (!is_int($id) || $id < 1 || !$this->requiresToolChoice || !in_array($id, $this->allowedToolIds, true)) {
            $this->addError($attribute, 'Выберите доступный инструмент из разрешённой группы. Фиксированный инструмент определяется предысторией.');
        }
    }

    public function validateEquipment($attribute, $params): void
    {
        $choice = $this->$attribute;
        if ($choice === null) {
            return;
        }
        if (($choice !== CharacterBackgroundChoice::PACKAGE || !$this->packageAvailable)
            && ($choice !== CharacterBackgroundChoice::MONEY || !$this->moneyAvailable)) {
            $this->addError($attribute, 'Выберите доступный комплект или денежную альтернативу.');
        }
    }
}
