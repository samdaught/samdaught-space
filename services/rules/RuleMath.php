<?php

declare(strict_types=1);

namespace app\services\rules;

final class RuleMath
{
    public const TERM_KINDS = ['ability_modifier', 'proficiency_bonus', 'character_level', 'class_level'];
    public const CONDITION_KINDS = ['level', 'class_level', 'ability', 'entry', 'proficiency', 'spellcasting', 'armor', 'choice'];
    public static function formula(): array
    {
        return ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null];
    }
    public static function condition(): array
    {
        return ['op' => 'all', 'conditions' => []];
    }
    public static function validateFormula($formula): void
    {
        self::keys($formula, ['base', 'terms', 'minimum', 'maximum']);
        self::number($formula['base'], -10000, 10000);
        foreach (['minimum', 'maximum'] as $key) {
            if ($formula[$key] !== null) {
                self::number($formula[$key], -10000, 10000);
            }
        }
        if ($formula['minimum'] !== null && $formula['maximum'] !== null && $formula['minimum'] > $formula['maximum']) {
            throw new \InvalidArgumentException('Минимум формулы больше максимума.');
        }
        self::list($formula['terms'], 10);
        foreach ($formula['terms'] as $term) {
            self::keys($term, ['kind', 'code', 'entry_id', 'multiplier', 'divisor', 'round']);
            if (!in_array($term['kind'], self::TERM_KINDS, true) || !in_array($term['round'], ['floor', 'ceil'], true)) {
                throw new \InvalidArgumentException('Выберите допустимый вид слагаемого и округления.');
            }
            if ($term['code'] !== null && (!is_string($term['code']) || strlen($term['code']) > 64)) {
                throw new \InvalidArgumentException('Некорректный код слагаемого.');
            }
            if ($term['kind'] === 'ability_modifier' && !in_array($term['code'], ['str', 'dex', 'con', 'int', 'wis', 'cha'], true)) {
                throw new \InvalidArgumentException('Выберите характеристику формулы.');
            }
            if ($term['entry_id'] !== null) {
                self::number($term['entry_id'], 1, 2147483647);
            }
            self::number($term['multiplier'], -100, 100);
            self::number($term['divisor'], 1, 100);
        }
    }
    public static function validateCondition($condition, int $depth = 0): void
    {
        if ($depth > 8 || !is_array($condition)) {
            throw new \InvalidArgumentException('Слишком сложное условие.');
        }
        if (isset($condition['op'])) {
            self::keys($condition, ['op', 'conditions']);
            if (!in_array($condition['op'], ['all', 'any', 'not'], true)) {
                throw new \InvalidArgumentException('Выберите логическую операцию.');
            }
            self::list($condition['conditions'], 20);
            if ($condition['op'] === 'not' && count($condition['conditions']) !== 1) {
                throw new \InvalidArgumentException('Отрицание должно содержать одно условие.');
            }
            foreach ($condition['conditions'] as $child) {
                self::validateCondition($child, $depth + 1);
            }
        } else {
            self::keys($condition, ['kind', 'code', 'entry_id', 'value']);
            if (!in_array($condition['kind'], self::CONDITION_KINDS, true) || !is_string($condition['code']) || strlen($condition['code']) > 64 || !is_scalar($condition['value'])) {
                throw new \InvalidArgumentException('Проверьте параметры требования.');
            }
            if ($condition['entry_id'] !== null) {
                self::number($condition['entry_id'], 1, 2147483647);
            }
            if (in_array($condition['kind'], ['level', 'class_level', 'ability'], true)) {
                self::number($condition['value'], 0, 30);
            }
            if ($condition['kind'] === 'ability' && !in_array($condition['code'], ['str', 'dex', 'con', 'int', 'wis', 'cha'], true)) {
                throw new \InvalidArgumentException('Выберите характеристику требования.');
            }
            if (in_array($condition['kind'], ['entry', 'proficiency'], true) && $condition['entry_id'] === null && $condition['code'] === '') {
                throw new \InvalidArgumentException('Выберите требуемую запись или владение.');
            }
        }
    }
    public static function value(array $formula, array $context): int
    {
        $value = $formula['base'];
        foreach ($formula['terms'] as $term) {
            $amount = match ($term['kind']) {
                'ability_modifier' => isset($context['abilities'][$term['code']]) ? (int) floor(($context['abilities'][$term['code']] - 10) / 2) : 0,
                'proficiency_bonus' => $context['proficiency_bonus'] ?? 2,
                'character_level' => $context['level'] ?? 1,
                'class_level' => $context['class_levels'][$term['entry_id'] ?? $context['class_id'] ?? 0] ?? 0,
                default => 0,
            };
            $number = $amount * $term['multiplier'] / $term['divisor'];
            $value += (int) ($term['round'] === 'ceil' ? ceil($number) : floor($number));
        }
        if ($formula['minimum'] !== null) {
            $value = max($value, $formula['minimum']);
        }
        if ($formula['maximum'] !== null) {
            $value = min($value, $formula['maximum']);
        }
        return $value;
    }
    public static function matches(array $condition, array $context): bool
    {
        if (isset($condition['op'])) {
            $values = array_map(static fn($child) => self::matches($child, $context), $condition['conditions']);
            return match ($condition['op']) {
                'all' => !in_array(false, $values, true),
                'any' => in_array(true, $values, true),
                'not' => !$values[0],
                default => false,
            };
        }
        $value = $condition['value'];
        $id = $condition['entry_id'];
        $code = $condition['code'];
        return match ($condition['kind']) {
            'level' => ($context['level'] ?? 1) >= $value,
            'class_level' => ($context['class_levels'][$id ?? $context['class_id'] ?? 0] ?? 0) >= $value,
            'ability' => ($context['abilities'][$code] ?? 0) >= $value,
            'entry' => in_array($id, $context['entry_ids'] ?? [], true),
            'proficiency' => $id !== null ? in_array($id, $context['proficiency_ids'] ?? [], true) : in_array($code, $context['proficiencies'] ?? [], true),
            'spellcasting' => (bool) ($context['spellcasting'] ?? false) === (bool) $value,
            'armor' => $code === 'none' ? !array_diff($context['armor'] ?? [], ['shield']) : ($code === 'any' ? (bool) array_diff($context['armor'] ?? [], ['shield']) : in_array($code, $context['armor'] ?? [], true)),
            'choice' => in_array((string) $value, $context['choices'][$code] ?? [], true),
            default => false,
        };
    }
    public static function keys($data, array $keys): void
    {
        if (!is_array($data) || count($data) !== count($keys) || array_diff($keys, array_keys($data))) {
            throw new \InvalidArgumentException('Передайте все параметры правила без лишних полей.');
        }
    }
    public static function list($value, int $max = 100): void
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > $max) {
            throw new \InvalidArgumentException('Проверьте размер и структуру списка.');
        }
    }
    public static function number($value, int $min, int $max): void
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException('Числовой параметр вне допустимого диапазона.');
        }
    }
    public static function text($value, int $max = 120, bool $empty = false): void
    {
        if (!is_string($value) || mb_strlen($value) > $max || !$empty && trim($value) === '') {
            throw new \InvalidArgumentException('Заполните текстовый параметр допустимой длины.');
        }
    }
}
