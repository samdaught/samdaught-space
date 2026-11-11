<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\RuleProfile;
use app\services\rules\CreationRules;
use app\services\rules\EntryMechanics;
use yii\base\Model;
use yii\db\Query;

class CreationRulesForm extends Model
{
    public $creation_rules = null;
    public ?int $sourceId = null;
    public ?int $ruleProfileId = null;

    public function rules(): array
    {
        return [['creation_rules', 'validateRule', 'skipOnEmpty' => false]];
    }

    public function validateRule($attribute, $params): void
    {
        $rule = $this->$attribute;
        if ($this->hasLegacyRuleShape($rule)) {
            $rule['hit_point_bonus'] = 0;
        }

        if (!$this->hasValidRuleShape($rule)) {
            $this->addError(
                $attribute,
                'Передайте списки фиксированных выдач и групп выбора допустимого размера.',
            );
            return;
        }

        if (!is_int($rule['hit_point_bonus']) || !$this->isIntegerRange($rule['hit_point_bonus'])) {
            $this->addError($attribute, 'Бонус максимальных хитов должен быть целым числом.');
            return;
        }

        $this->$attribute = $rule;
        $targets = [];
        $grantIds = [];
        $codes = [];

        foreach ($rule['grants'] as $grant) {
            if (!$this->hasExactKeys($grant, ['entry_id', 'mode'])
                || !$this->isEntryId($grant['entry_id'])
                || !$this->isGrantMode($grant['mode'])) {
                $this->addError($attribute, 'В каждой выдаче выберите запись и режим.');
                return;
            }

            $grantIds[] = $grant['entry_id'];
            $targets[] = [$grant['entry_id'], $grant['mode']];
        }

        if (count(array_unique($grantIds)) !== count($grantIds)) {
            $this->addError($attribute, 'Фиксированные выдачи не должны повторяться.');
            return;
        }

        foreach ($rule['choices'] as $choice) {
            if (!$this->hasValidChoice($choice)) {
                $this->addError(
                    $attribute,
                    'Для группы нужны уникальный код латиницей, название, количество от 1 до 100 и достаточный список вариантов.',
                );
                return;
            }

            if (in_array($choice['code'], EntryMechanics::MAGIC_CODES, true)) {
                $this->addError($attribute, 'Этот код группы зарезервирован для заклинательства класса.');
                return;
            }

            $codes[] = $choice['code'];
            foreach ($choice['entry_ids'] as $id) {
                if (!$this->isEntryId($id)) {
                    $this->addError($attribute, 'Выберите варианты группы из архива.');
                    return;
                }

                $targets[] = [$id, $choice['mode']];
            }

            if (count(array_unique($choice['entry_ids'])) !== count($choice['entry_ids'])) {
                $this->addError($attribute, 'Варианты внутри группы не должны повторяться.');
                return;
            }
        }

        if (count(array_unique($codes)) !== count($codes)) {
            $this->addError($attribute, 'Коды групп должны быть уникальны внутри записи.');
            return;
        }

        $profile = $this->ruleProfileId === null ? null : RuleProfile::findOne($this->ruleProfileId);
        $rules = new CreationRules();
        usort($targets, static fn(array $left, array $right): int => $left[0] <=> $right[0]);

        foreach ($targets as [$id, $mode]) {
            $entry = $rules->findAvailable($id, $profile);
            if (
                $id === $this->sourceId
                || $entry === null
                || !$rules->targetAvailable($entry)
                || ($mode === 'expertise' && $entry->type !== 'skill')
            ) {
                $this->addError(
                    $attribute,
                    'Выберите опубликованные доступные записи подходящего типа. Экспертиза разрешена только навыку; запись не может выдавать саму себя.',
                );
                return;
            }
        }

        $seen = [];
        $path = [];
        foreach (array_unique(array_column($targets, 0)) as $id) {
            if ($this->isCyclic((int) $id, $rules, $seen, $path, 1)) {
                $this->addError(
                    $attribute,
                    'Выдачи образуют цикл или превышают допустимую глубину. Измените связи особенностей и черт.',
                );
                return;
            }
        }
    }

    private function isCyclic(int $id, CreationRules $rules, array &$seen, array &$path, int $depth): bool
    {
        if ($id === $this->sourceId || isset($path[$id]) || $depth > 12 || count($seen) > 10000) {
            return true;
        }
        if (isset($seen[$id])) {
            return false;
        }

        $seen[$id] = true;
        $path[$id] = true;
        $rule = $rules->read($id);
        $children = array_merge(
            array_column($rule['grants'] ?? [], 'entry_id'),
            (new Query())
                ->select('target_entry_id')
                ->from('{{%entry_level_grant}}')
                ->where(['source_entry_id' => $id])
                ->column(),
        );
        foreach ($rule['choices'] ?? [] as $choice) {
            $children = array_merge($children, $choice['entry_ids']);
        }

        foreach (array_unique($children) as $child) {
            if ($this->isCyclic((int) $child, $rules, $seen, $path, $depth + 1)) {
                return true;
            }
        }

        unset($path[$id]);
        return false;
    }

    private function hasLegacyRuleShape($rule): bool
    {
        return $this->hasExactKeys($rule, ['grants', 'choices']);
    }

    private function hasValidRuleShape($rule): bool
    {
        return $this->hasExactKeys($rule, ['grants', 'choices', 'hit_point_bonus'])
            && is_array($rule['grants'])
            && array_is_list($rule['grants'])
            && is_array($rule['choices'])
            && array_is_list($rule['choices'])
            && count($rule['grants']) <= CreationRules::MAX_ROWS
            && count($rule['choices']) <= CreationRules::MAX_ROWS;
    }

    private function hasValidChoice($choice): bool
    {
        return $this->hasExactKeys($choice, ['code', 'title', 'count', 'mode', 'entry_ids'])
            && is_string($choice['code'])
            && preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $choice['code'])
            && is_string($choice['title'])
            && trim($choice['title']) !== ''
            && mb_strlen($choice['title']) <= 120
            && is_int($choice['count'])
            && $choice['count'] >= 1
            && $choice['count'] <= CreationRules::MAX_ROWS
            && $this->isGrantMode($choice['mode'])
            && is_array($choice['entry_ids'])
            && array_is_list($choice['entry_ids'])
            && count($choice['entry_ids']) <= CreationRules::MAX_OPTIONS
            && count($choice['entry_ids']) >= $choice['count'];
    }

    private function hasExactKeys($value, array $keys): bool
    {
        return is_array($value) && count($value) === count($keys) && !array_diff($keys, array_keys($value));
    }

    private function isEntryId($value): bool
    {
        return is_int($value) && $value > 0 && $value <= 2147483647;
    }

    private function isIntegerRange(int $value): bool
    {
        return $value >= -2147483648 && $value <= 2147483647;
    }

    private function isGrantMode($value): bool
    {
        return $value === 'grant' || $value === 'expertise';
    }
}
