<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\Character;
use app\models\characters\CharacterAbilityIncreaseSource;
use app\models\characters\CharacterAbilityScore;
use app\models\rules\AbilityDefinition;

class AbilityScoreCalculator
{

    public function calculate(Character $character): array
    {
        $definitions = AbilityDefinition::find()->select(['code', 'title'])
            ->orderBy(['code' => SORT_ASC])->asArray()->all();
        $savedScores = CharacterAbilityScore::find()->select('base_score')
            ->where(['character_id' => $character->id])->indexBy('ability_code')->column();
        $baseScores = [];
        foreach ($definitions as $definition) {
            $baseScores[$definition['code']] = $savedScores[$definition['code']] ?? null;
        }

        $background = (new BackgroundAbilityDistribution())->get($character);
        $issues = $background['issues'];
        $baseValid = $character->ability_score_method === Character::ABILITY_METHOD_STANDARD_ARRAY
            && (new StandardArray())->isValidAllocation($baseScores);

        if (!$baseValid) {
            $issues[] = 'Сохранённые базовые характеристики не соответствуют поддерживаемому стандартному массиву.';
        }

        $increases = [];
        if ($background['selection_valid'] && $background['configured'] && !$background['needs_redistribution']) {
            $increases = array_column($background['abilities'], 'increase', 'code');
        }

        foreach ((new AbilityIncreaseContext())->findMatchingSets($character) as $increaseSet) {
            if ($increaseSet->slot !== CharacterAbilityIncreaseSource::BACKGROUND_SLOT
                || $increaseSet->source_kind !== CharacterAbilityIncreaseSource::KIND_BACKGROUND) {
                $issues[] = 'Увеличения при развитии пока не рассчитываются. В итог включена только поддержанная предыстория.';
                break;
            }
        }

        $modifier = new AbilityModifier();
        $abilities = [];
        foreach ($definitions as $definition) {
            $code = $definition['code'];
            $base = $baseScores[$code];
            $increase = $increases[$code] ?? 0;
            $total = $baseValid && $base !== null ? $base + $increase : null;
            $abilities[] = [
                'code' => $code,
                'title' => $definition['title'],
                'base_score' => $base,
                'base_modifier' => $modifier->calculate($base),
                'applied_increase' => $increase,
                'total_score' => $total,
                'total_modifier' => $modifier->calculate($total),
            ];
        }

        return [
            'method' => $character->ability_score_method,
            'available_values' => StandardArray::VALUES,
            'calculation' => [
                'base_scores_complete' => $baseValid && !in_array(null, $baseScores, true),
                'background_increases_complete' => $background['complete'],
                'issues' => array_values(array_unique($issues)),
            ],
            'abilities' => $abilities,
        ];
    }
}
