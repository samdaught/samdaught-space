<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\catalog\{CatalogEntry, ClassSkillsForm};
use app\models\characters\{Character, CharacterClassSkillChoice, CharacterClassSkill, ClassSkillChoicesForm};
use app\models\rules\{ClassDefinition, ClassSkillOption, RuleProfile};
use app\services\rules\{SkillOptions, CatalogAvailability};

class CharacterClassSkills
{

    public function get(Character $character): array
    {
        $saved = CharacterClassSkillChoice::findOne($character->id);
        $matches = $saved !== null && $saved->class_entry_id === $character->class_entry_id
            && $saved->rule_profile_id === $character->rule_profile_id;
        $issues = [];
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $entry = null;
        if ($character->class_entry_id !== null) {
            $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR SHARE', [':id' => $character->class_entry_id])->queryScalar();
            $entry = $found === false ? null : CatalogEntry::findOne($found);
        }
        $valid = $entry !== null && $entry->type === 'class' && $entry->is_published && $profile !== null
            && (new CatalogAvailability())->isAvailableInProfile($entry, $profile);
        if (!$valid) $issues[] = 'Выберите опубликованный класс, доступный профилю персонажа.';
        $definition = !$valid ? null : ClassDefinition::findOne($entry->id);
        $count = $definition === null ? null : $definition->skill_choice_count;
        $ids = $definition === null ? [] : ClassSkillOption::find()->select('skill_entry_id')
            ->where(['class_entry_id' => $entry->id])->orderBy('skill_entry_id')->column();
        $options = [];
        foreach ($ids as $id) {
            $skill = (new SkillOptions())->lockAndFind($id);
            if ($skill !== null && (new CatalogAvailability())->isAvailableInProfile($skill, $profile)) {
                $options[] = ['id' => $skill->id, 'title' => $skill->title];
            }
        }
        $configured = $count !== null && $count >= 0 && $count <= ClassSkillsForm::MAX_OPTIONS && count($options) >= $count;
        if ($valid && !$configured) $issues[] = 'Правило выбора навыков класса не настроено или в профиле не хватает доступных вариантов.';
        $selectedIds = !$matches ? [] : CharacterClassSkill::find()->select('skill_entry_id')
            ->where(['character_id' => $character->id])->orderBy('skill_entry_id')->column();
        $form = new ClassSkillChoicesForm();
        $form->skill_entry_ids = $selectedIds;
        $form->allowedIds = array_column($options, 'id');
        $form->choiceCount = $count ?? 0;
        $choicesValid = $configured && $form->validate();
        $needsReselection = ($saved !== null && !$matches) || ($matches && !$choicesValid);
        if ($needsReselection) $issues[] = 'Класс, профиль или разрешённые навыки изменились. Сохраните актуальный выбор.';
        $effectiveIds = $choicesValid ? $selectedIds : [];
        $complete = $choicesValid && count($effectiveIds) === $count;
        if ($configured && !$complete) $issues[] = 'Выберите навыки класса: требуется ' . $count . '.';
        return [
            'class_entry_id' => $character->class_entry_id, 'rule_profile_id' => $character->rule_profile_id,
            'revision' => $saved === null ? 0 : $saved->revision,
            'selection_valid' => $valid, 'configured' => $configured, 'choice_count' => $count,
            'options' => $options, 'selected_skill_entry_ids' => $selectedIds,
            'effective_skill_entry_ids' => $effectiveIds, 'needs_reselection' => $needsReselection,
            'complete' => $complete, 'issues' => $issues,
        ];
    }
}
