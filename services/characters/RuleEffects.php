<?php

declare(strict_types=1);

namespace app\services\characters;

use app\services\rules\RuleMath;

final class RuleEffects
{
    public static function calculate(array $effects, array $context, array $fixed): array
    {
        $bonuses = $fixed;
        $targeted = [];
        $caps = array_fill_keys(['str', 'dex', 'con', 'int', 'wis', 'cha'], 20);
        foreach ($effects as $effect) {
            $local = $context;
            $local['choices'] = $effect['choices'] ?? [];
            if (!RuleMath::matches($effect['condition'], $local)) {
                continue;
            }
            $target = $effect['target'];
            $value = RuleMath::value($effect['formula'], $context);
            if (in_array($target, ['ability_choice', 'ability_cap_choice'], true)) {
                $ability = $local['choices'][$effect['choice_code']] ?? [];
                if (count($ability) !== 1) {
                    continue;
                }
                $target = ($target === 'ability_choice' ? 'ability_' : 'ability_cap_') . $ability[0];
            }
            if ($target === 'ability_cap') {
                foreach ($caps as &$cap) {
                    $cap = max($cap, $value);
                }
                unset($cap);
            } elseif (str_starts_with($target, 'ability_cap_')) {
                $caps[substr($target, 12)] = max($caps[substr($target, 12)] ?? 20, $value);
            } elseif (in_array($target, ['skill', 'weapon_attack', 'weapon_damage', 'saving_throw'], true)) {
                $targeted[$target][$effect['target_entry_id'] ?? 0] = ($targeted[$target][$effect['target_entry_id'] ?? 0] ?? 0) + $value;
            } else {
                $bonuses[$target] = ($bonuses[$target] ?? 0) + $value;
            }
        }
        foreach ($caps as $code => $cap) {
            $context['abilities'][$code] = max(0, min($cap, ($context['base_abilities'][$code] ?? 0) + ($bonuses['ability_' . $code] ?? 0)));
        }
        return ['bonuses' => $bonuses, 'targeted' => $targeted, 'caps' => $caps, 'context' => $context];
    }
}
