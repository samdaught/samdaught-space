<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\catalog\CatalogEntry;
use app\models\characters\Character;
use app\models\rules\RuleProfile;
use app\services\rules\CreationRules;
use yii\db\Query;

class CreationBenefits
{
    public function get(Character $character, $selection = null, ?array $baseSkillIds = null, ?array $background = null): array
    {
        if ($selection === null) {
            $selection = [];
            foreach ((new Query())->from('{{%character_creation_choice}}')->where(['character_id' => $character->id])->orderBy(['source_entry_id' => SORT_ASC, 'code' => SORT_ASC, 'entry_id' => SORT_ASC])->all() as $row) $selection[$row['source_entry_id'] . ':' . $row['code']][] = (int) $row['entry_id'];
        }
        $errors = []; $issues = []; $complete = true; $configured = true;
        $hitPointBonus = 0;
        $choices = [];
        if (!is_array($selection) || count($selection) > CreationRules::MAX_ROWS) $errors[] = 'Передайте допустимый набор групп выбора.';
        else foreach ($selection as $key => $ids) {
            if (!is_string($key) || !preg_match('/^([1-9][0-9]{0,9}):[a-z][a-z0-9_]{0,31}$/D', $key, $match) || (int) $match[1] > 2147483647
                || !is_array($ids) || !array_is_list($ids) || count($ids) > CreationRules::MAX_ROWS) { $errors[] = 'Некорректная группа выбора.'; continue; }
            foreach ($ids as $id) if (!is_int($id) || $id < 1 || $id > 2147483647) { $errors[] = 'Выбирайте записи по целым ID из предложенного списка.'; continue 2; }
            if (count(array_unique($ids)) !== count($ids)) { $errors[] = 'Выборы внутри группы не должны повторяться.'; continue; }
            $choices[$key] = $ids;
        }
        $background ??= (new CharacterBackgroundBenefits())->get($character);
        $baseSkillIds ??= array_merge(array_column($background['skills'], 'id'), (new CharacterClassSkills())->get($character)['effective_skill_entry_ids']);
        $proficient = array_fill_keys($baseSkillIds, true); $expertise = []; $entries = []; $groups = []; $seen = []; $stack = []; $cache = [];
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $rules = new CreationRules();
        $find = function (int $id) use (&$cache, $rules, $profile): ?CatalogEntry {
            if (!array_key_exists($id, $cache)) $cache[$id] = $rules->findAvailable($id, $profile);
            return $cache[$id];
        };
        $visit = null;
        $activate = function (CatalogEntry $entry, string $mode, int $depth) use (&$activate, &$visit, &$entries, &$proficient, &$expertise, &$issues, &$complete): void {
            if ($mode === 'expertise') { $expertise[$entry->id] = true; return; }
            if ($entry->type === 'feat' && isset($entries[$entry->id])) { $complete = false; $issues[] = 'Черта «' . $entry->title . '» выбрана повторно. Выберите другую разрешённую черту.'; }
            $entries[$entry->id] = $entry->getAttributes(['id', 'type', 'title', 'body']);
            if ($entry->type === 'skill') $proficient[$entry->id] = true;
            if (in_array($entry->type, CreationRules::SOURCE_TYPES, true)) $visit($entry, $depth);
        };
        $visit = function (CatalogEntry $source, int $depth) use (&$visit, &$activate, &$groups, &$seen, &$stack, &$complete, &$issues, &$errors, &$hitPointBonus, $choices, $rules, $find): void {
            if (isset($stack[$source->id]) || $depth > 12 || count($seen) >= 300) { $complete = false; $issues[] = 'В архивных выдачах обнаружена циклическая или слишком глубокая связь. Администратору нужно исправить правило.'; return; }
            if (isset($seen[$source->id])) return;
            $seen[$source->id] = true; $stack[$source->id] = true;
            $rule = $rules->read($source->id);
            if ($rule !== null) {
                $hitPointBonus += $rule['hit_point_bonus'];
                foreach ($rule['grants'] as $grant) {
                    $entry = $find((int) $grant['entry_id']);
                    if ($entry === null || !$rules->targetAvailable($entry) || ($grant['mode'] === 'expertise' && $entry->type !== 'skill')) { $complete = false; $issues[] = 'Одна из выдач записи «' . $source->title . '» недоступна. Исправьте публикацию или правило профиля.'; continue; }
                    $activate($entry, $grant['mode'], $depth + 1);
                }
                foreach ($rule['choices'] as $choice) {
                    $key = $source->id . ':' . $choice['code']; $options = [];
                    foreach ($choice['entry_ids'] as $id) {
                        $entry = $find((int) $id);
                        if ($entry !== null && $rules->targetAvailable($entry) && ($choice['mode'] !== 'expertise' || $entry->type === 'skill')) $options[$entry->id] = $entry;
                    }
                    $selected = $choices[$key] ?? [];
                    $valid = count($selected) <= $choice['count'] && !array_diff($selected, array_keys($options));
                    $groups[$key] = ['key' => $key, 'source_title' => $source->title, 'title' => $choice['title'], 'count' => $choice['count'], 'mode' => $choice['mode'],
                        'options' => array_map(static fn($entry) => $entry->getAttributes(['id', 'type', 'title']), array_values($options)), 'selected_entry_ids' => $selected];
                    if (!$valid) $errors[] = 'Группа «' . $choice['title'] . '»: выберите не больше ' . $choice['count'] . ' разных доступных записей.';
                    if (!$valid || count($selected) !== $choice['count'] || count($options) < $choice['count']) { $complete = false; $issues[] = 'Не завершён выбор «' . $choice['title'] . '» (' . $source->title . ').'; }
                    if ($valid) foreach ($selected as $id) $activate($options[$id], $choice['mode'], $depth + 1);
                }
            }
            unset($stack[$source->id]);
        };
        foreach (['species_entry_id' => 'species', 'class_entry_id' => 'class', 'background_entry_id' => 'background'] as $field => $type) {
            $source = $character->$field === null ? null : $find($character->$field);
            if ($source === null || $source->type !== $type) { $configured = false; $complete = false; continue; }
            if (in_array($type, ['species', 'class'], true) && $rules->read($source->id) === null) { $configured = false; $complete = false; $issues[] = 'Для записи «' . $source->title . '» не настроены выдачи при создании персонажа.'; }
            $visit($source, 0);
        }
        if ($background['feat'] !== null) {
            $entry = $find($background['feat']['id']); if ($entry !== null) $activate($entry, 'grant', 0);
        }
        foreach ($choices as $key => $ids) if (!isset($groups[$key]) && $ids !== []) $errors[] = 'Сохранён выбор для неактивной группы «' . $key . '». Сбросьте его после смены вида, класса или черты.';
        foreach (array_keys($expertise) as $id) if (!isset($proficient[$id])) { unset($expertise[$id]); $errors[] = 'Экспертизу можно выбрать только для навыка, которым персонаж уже владеет.'; }
        return ['configured' => $configured, 'complete' => $complete && !$errors, 'selections' => $choices, 'groups' => array_values($groups), 'hit_point_bonus' => $hitPointBonus,
            'entries' => array_values($entries), 'skill_entry_ids' => array_values(array_diff(array_map('intval', array_keys($proficient)), $baseSkillIds)), 'proficient_skill_entry_ids' => array_map('intval', array_keys($proficient)), 'expertise_skill_entry_ids' => array_map('intval', array_keys($expertise)),
            'validation_errors' => array_values(array_unique($errors)), 'issues' => array_values(array_unique(array_merge($issues, $errors)))];
    }
}
