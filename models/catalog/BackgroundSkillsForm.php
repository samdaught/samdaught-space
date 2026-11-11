<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\RuleProfile;
use app\services\rules\SkillOptions;
use app\services\rules\CatalogAvailability;
use yii\base\Model;

class BackgroundSkillsForm extends Model
{
    public const REQUIRED_COUNT = 2;
    public $skill_entry_ids = null;

    public ?int $ruleProfileId = null;

    public function rules(): array
    {
        return [['skill_entry_ids', 'validateSkills', 'skipOnEmpty' => false]];
    }

    public function validateSkills($attribute, $params): void
    {
        $ids = $this->$attribute;
        if (!is_array($ids) || !array_is_list($ids) || count($ids) !== self::REQUIRED_COUNT) {
            $this->addError($attribute, 'Выберите два разных навыка, которыми предыстория даёт владение.');
            return;
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                $this->addError($attribute, 'Выберите каждый навык из списка.');
                return;
            }
        }
        if (count(array_unique($ids)) !== self::REQUIRED_COUNT) {
            $this->addError($attribute, 'Навыки предыстории не должны повторяться.');
            return;
        }
        $profile = $this->ruleProfileId === null ? null : RuleProfile::findOne($this->ruleProfileId);
        if ($profile === null) {
            $this->addError($attribute, 'Сначала выберите корректный профиль происхождения предыстории.');
            return;
        }

        sort($ids, SORT_NUMERIC);
        $options = new SkillOptions();
        $availability = new CatalogAvailability();
        foreach ($ids as $id) {
            $entry = $options->lockAndFind($id);
            if ($entry === null) {
                $this->addError($attribute, 'Выберите опубликованные навыки с настроенной характеристикой.');
                return;
            }
            if (!$availability->isAvailableInProfile($entry, $profile)) {
                $this->addError($attribute, 'Выбранный навык недоступен профилю происхождения предыстории.');
                return;
            }
        }
    }
}
