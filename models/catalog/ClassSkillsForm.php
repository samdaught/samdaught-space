<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\RuleProfile;
use app\services\rules\{SkillOptions, CatalogAvailability};
use yii\base\Model;

class ClassSkillsForm extends Model
{

    public const MAX_OPTIONS = 100;
    public $class_skill_choice_count = null;
    public $class_skill_entry_ids = null;
    public ?int $ruleProfileId = null;

    public function rules(): array
    {
        return [
            ['class_skill_choice_count', 'validateCount', 'skipOnEmpty' => false],
            ['class_skill_entry_ids', 'validateSkills', 'skipOnEmpty' => false],
        ];
    }

    public function validateCount($attribute, $params): void
    {
        $count = $this->$attribute;
        if (!is_int($count) || $count < 0 || $count > self::MAX_OPTIONS) {
            $this->addError($attribute, 'Количество выборов должно быть целым числом от 0 до ' . self::MAX_OPTIONS . '.');
        }
    }

    public function validateSkills($attribute, $params): void
    {
        $ids = $this->$attribute;
        if (!is_array($ids) || !array_is_list($ids) || count($ids) > self::MAX_OPTIONS) {
            $this->addError($attribute, 'Передайте список разрешённых навыков длиной не больше ' . self::MAX_OPTIONS . '.');
            return;
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                $this->addError($attribute, 'Выберите навыки из справочника.');
                return;
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            $this->addError($attribute, 'Разрешённые навыки класса не должны повторяться.');
            return;
        }
        if (!$this->hasErrors('class_skill_choice_count') && count($ids) < $this->class_skill_choice_count) {
            $this->addError($attribute, 'В разрешённом списке должно хватать навыков для всех выборов.');
        }
        $profile = $this->ruleProfileId === null ? null : RuleProfile::findOne($this->ruleProfileId);
        if ($profile === null) {
            $this->addError($attribute, 'Выберите корректный профиль происхождения класса.');
            return;
        }
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $entry = (new SkillOptions())->lockAndFind($id);
            if ($entry === null || !(new CatalogAvailability())->isAvailableInProfile($entry, $profile)) {
                $this->addError($attribute, 'Выберите опубликованные навыки с определением, доступные профилю класса.');
                return;
            }
        }
    }
}
