<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\BackgroundAbilityIncreasesForm;
use app\models\characters\Character;
use app\models\characters\CharacterAbilityIncrease;
use app\models\characters\CharacterAbilityIncreaseSource;
use app\models\rules\AbilityDefinition;
use app\models\rules\BackgroundAbilityOption;

class BackgroundAbilityDistribution
{
    public function getAbilityOptions(?int $backgroundEntryId): array
    {
        if ($backgroundEntryId === null) {
            return [];
        }

        return BackgroundAbilityOption::find()
            ->alias('option')
            ->innerJoin(['ability' => AbilityDefinition::tableName()], '[[ability.code]] = [[option.ability_code]]')
            ->select(['ability.code', 'ability.title'])
            ->where(['option.background_entry_id' => $backgroundEntryId])
            ->orderBy(['ability.code' => SORT_ASC])
            ->asArray()
            ->all();
    }

    public function get(Character $character): array
    {
        $options = $this->getAbilityOptions($character->background_entry_id);
        $configured = count($options) === BackgroundAbilityIncreasesForm::ALLOWED_ABILITY_COUNT;
        $selectionValid = $character->background_entry_id !== null
            && $character->validate(['rule_profile_id', 'background_entry_id']);
        $issues = $character->background_entry_id !== null ? $character->getErrorSummary(true) : [];

        if ($character->background_entry_id !== null && !$configured) {
            $issues[] = 'Для выбранной предыстории не настроены три доступные характеристики.';
        }

        $increases = [];
        $needsRedistribution = false;
        $increaseSet = CharacterAbilityIncreaseSource::findOne([
            'character_id' => $character->id,
            'slot' => CharacterAbilityIncreaseSource::BACKGROUND_SLOT,
        ]);

        if ($increaseSet !== null) {
            $context = new AbilityIncreaseContext();

            if ($increaseSet->source_kind !== CharacterAbilityIncreaseSource::KIND_BACKGROUND
                || !$context->matches($increaseSet, $character)) {
                $needsRedistribution = true;
                $issues[] = 'Сохранённые увеличения не относятся к текущей предыстории. Распределите их заново.';
            } else {
                $increases = CharacterAbilityIncrease::find()->select('amount')
                    ->where(['source_id' => $increaseSet->id])->indexBy('ability_code')->column();
                $form = new BackgroundAbilityIncreasesForm();
                $form->allowedAbilityCodes = array_column($options, 'code');
                $form->increases = $increases;

                if (!$form->validate()) {
                    $needsRedistribution = true;
                    $issues = array_merge($issues, $form->getErrorSummary(true));
                    $increases = [];
                }
            }
        }

        $abilities = [];
        foreach ($options as $option) {
            $abilities[] = [
                'code' => $option['code'],
                'title' => $option['title'],
                'increase' => $increases[$option['code']] ?? 0,
            ];
        }

        return [
            'background_entry_id' => $character->background_entry_id,
            'rule_profile_id' => $character->rule_profile_id,
            'revision' => $increaseSet === null ? 0 : $increaseSet->revision,
            'configured' => $configured,
            'selection_valid' => $selectionValid,
            'complete' => $selectionValid && $configured && !$needsRedistribution
                && array_sum($increases) === BackgroundAbilityIncreasesForm::POINT_BUDGET,
            'point_budget' => BackgroundAbilityIncreasesForm::POINT_BUDGET,
            'max_per_ability' => BackgroundAbilityIncreasesForm::MAX_PER_ABILITY,
            'needs_redistribution' => $needsRedistribution,
            'issues' => array_values(array_unique($issues)),
            'abilities' => $abilities,
        ];
    }
}
