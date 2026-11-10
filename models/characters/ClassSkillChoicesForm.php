<?php

declare(strict_types=1);

namespace app\models\characters;

use yii\base\Model;

class ClassSkillChoicesForm extends Model
{
    public $skill_entry_ids = null;
    public array $allowedIds = [];
    public int $choiceCount = 0;

    public function rules(): array
    {
        return [['skill_entry_ids', 'validateSkills', 'skipOnEmpty' => false]];
    }

    public function validateSkills($attribute, $params): void
    {
        $ids = $this->$attribute;
        if (!is_array($ids) || !array_is_list($ids) || count($ids) > $this->choiceCount) {
            $this->addError($attribute, 'Передайте список выбранных навыков: не больше ' . $this->choiceCount . '. Незавершённый выбор разрешён.');
            return;
        }
        foreach ($ids as $id) {
            if (!is_int($id) || !in_array($id, $this->allowedIds, true)) {
                $this->addError($attribute, 'Выберите доступные навыки из списка текущего класса.');
                return;
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            $this->addError($attribute, 'Навыки класса не должны повторяться.');
        }
    }
}
