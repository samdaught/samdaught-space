<?php

declare (strict_types=1);
namespace app\services\characters;

use app\models\catalog\CatalogEntry;
use app\models\characters\Character;
use app\models\rules\RuleProfile;
use app\services\rules\{CreationRules, Rulebook, RuleMath, SpellcastingRules};
use yii\db\Query;
class CreationBenefits
{
    public function get(Character $character, $selection = null, ?array $baseSkillIds = null, ?array $background = null): array
    {
        if ($selection === null) {
            $selection = [];
            foreach ((new Query())->from('{{%character_creation_choice}}')->where(['character_id' => $character->id])->all() as $row) {
                $source = $row['source_entry_id'] . ($row['instance_key'] === 'root' ? '' : '~' . $row['instance_key']);
                $selection[$source . ':' . $row['code']][] = (int) $row['entry_id'];
            }
        }
        $errors = [];
        $issues = [];
        $complete = true;
        $configured = true;
        $choices = [];
        if (!is_array($selection) || count($selection) > 300) {
            $errors[] = 'Передайте допустимый набор групп выбора.';
        } else {
            foreach ($selection as $key => $ids) {
                if (!is_string($key) || !preg_match('/^[1-9][0-9]{0,9}(?:~[a-f0-9]{12})?:[a-z][a-z0-9_]{0,31}$/D', $key) || !is_array($ids) || !array_is_list($ids) || count($ids) > 100) {
                    $errors[] = 'Некорректная группа выбора.';
                    continue;
                }
                foreach ($ids as $id) {
                    if (!is_int($id) || $id < 1 || $id > 2147483647) {
                        $errors[] = 'Выбирайте записи по целым ID.';
                        continue 2;
                    }
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    $errors[] = 'Выборы внутри группы не должны повторяться.';
                    continue;
                }
                $choices[$key] = $ids;
            }
        }
        $scalar = $character->rule_choices ?? [];
        if (is_string($scalar)) {
            $scalar = json_decode($scalar, true);
        }
        if (!is_array($scalar) || count($scalar) > 300) {
            $errors[] = 'Некорректные значения выборов.';
            $scalar = [];
        }
        foreach ($scalar as $key => $values) {
            if (!is_string($key) || !is_array($values) || !array_is_list($values) || count($values) > 100 || count(array_unique($values, SORT_REGULAR)) !== count($values) || array_filter($values, static fn($value) => !is_string($value) || strlen($value) > 64)) {
                $errors[] = 'Некорректная группа значений.';
                unset($scalar[$key]);
            }
        }
        $context = RuleContext::build($character, $character->ruleContextScores, $character->ruleContextIncreases);
        $background ??= (new CharacterBackgroundBenefits())->get($character);
        $baseSkillIds ??= array_merge(array_column($background['skills'], 'id'), (new CharacterClassSkills())->get($character)['effective_skill_entry_ids']);
        $proficient = array_fill_keys($baseSkillIds, true);
        $expertise = [];
        $entries = [];
        $groups = [];
        $scalarGroups = [];
        $instances = [];
        $seen = [];
        $stack = [];
        $cache = [];
        $books = [];
        $bonuses = [];
        $fixedSources = [];
        $traits = [];
        $sources = [];
        $defenses = [];
        $effects = [];
        $resourceRules = [];
        $grantRules = [];
        $requirements = [];
        $hitPoints = 0;
        $casting = ['configured' => false, 'enabled' => false, 'ability_code' => null, 'slot_count' => 0, 'groups' => [], 'issues' => []];
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $rules = new CreationRules();
        $rulebook = new Rulebook();
        $book = function (int $id) use (&$books, $rulebook): array {
            return $books[$id] ??= $rulebook->read($id);
        };
        $find = function (int $id) use (&$cache, $rules, $profile): ?CatalogEntry {
            return $cache[$id] ??= $rules->findAvailable($id, $profile);
        };
        $scalarGroup = function (string $key, string $sourceTitle, array $group) use (&$scalarGroups, $scalar, &$complete, &$issues, &$errors, $find): array {
            $selected = $scalar[$key] ?? [];
            $values = $group['values'];
            $valid = count($selected) <= $group['count'] && !array_diff($selected, $values);
            $options = [];
            foreach ($values as $value) {
                $title = $value;
                if (in_array($group['kind'], ['spell_list', 'proficiency', 'weapon_mastery'], true)) {
                    $title = $find((int) $value)?->title ?? $value;
                } else {
                    $title = (new Query())->select('title')->from('{{%rule_vocabulary}}')->where(['kind' => $group['kind'], 'code' => $value])->scalar() ?: ['str' => 'Сила', 'dex' => 'Ловкость', 'con' => 'Телосложение', 'int' => 'Интеллект', 'wis' => 'Мудрость', 'cha' => 'Харизма'][$value] ?? $value;
                }
                $options[] = ['value' => $value, 'title' => $title];
            }
            $scalarGroups[$key] = ['key' => $key, 'source_title' => $sourceTitle, 'title' => $group['title'], 'kind' => $group['kind'], 'count' => $group['count'], 'options' => $options, 'selected_values' => $selected];
            if (!$valid) {
                $errors[] = 'Выберите допустимые значения: ' . $group['title'] . '.';
            }
            if (!$valid || count($selected) !== $group['count']) {
                $complete = false;
                $issues[] = 'Не завершён выбор «' . $group['title'] . '» (' . $sourceTitle . ').';
            }
            return $valid ? $selected : [];
        };
        // The common language and two additional standard languages are creation rules, not species traits.
        $languages = (new Query())->select('code')->from('{{%rule_vocabulary}}')->where(['kind' => 'language', 'group_code' => 'standard'])->andWhere(['<>', 'code', 'common'])->column();
        $chosenLanguages = $scalarGroup('profile:languages', 'Правила 2024', ['title' => 'Два стандартных языка', 'kind' => 'language', 'count' => 2, 'values' => $languages]);
        foreach (array_merge(['common'], $chosenLanguages) as $language) {
            $traits[] = ['source_id' => 0, 'source_title' => 'Правила 2024', 'kind' => 'language', 'value' => (new Query())->select('title')->from('{{%rule_vocabulary}}')->where(['kind' => 'language', 'code' => $language])->scalar()];
        }
        $visit = null;
        $activate = null;
        $activate = function (CatalogEntry $entry, string $mode, int $depth, ?CatalogEntry $source = null, string $sourceKey = '', string $origin = 'root') use (&$visit, &$entries, &$proficient, &$expertise, &$complete, &$issues, &$sources, $book): void {
            if ($mode === 'expertise') {
                $expertise[$entry->id] = true;
                return;
            }
            $repeat = $book($entry->id)['repeatable'];
            if ($entry->type === 'feat' && isset($entries[$entry->id]) && !$repeat) {
                $complete = false;
                $issues[] = 'Черта «' . $entry->title . '» выбрана повторно.';
                return;
            }
            $entries[$entry->id] = $entry->getAttributes(['id', 'type', 'title', 'body']);
            if ($entry->type === 'skill') {
                $proficient[$entry->id] = true;
            }
            if ($entry->type === 'spell' && $source !== null) {
                $sources[$sourceKey] ??= ['source_id' => $source->id, 'source_title' => $source->title, 'instance_key' => $sourceKey, 'ability_code' => null, 'spells' => []];
                $sources[$sourceKey]['spells'][$entry->id] = ['id' => $entry->id, 'title' => $entry->title];
                $entries[$entry->id]['spell'] = (new Query())->from('{{%spell_definition}}')->where(['catalog_entry_id' => $entry->id])->one();
            }
            if (in_array($entry->type, CreationRules::SOURCE_TYPES, true)) {
                $visit($entry, $depth, $repeat ? substr(hash('sha256', $origin), 0, 12) : 'root', $origin);
            }
        };
        $processGroup = function (CatalogEntry $source, string $sourceKey, array $choice, int $depth) use (&$groups, $choices, &$errors, &$complete, &$issues, $find, $rules, &$activate, $book, &$requirements): void {
            $key = $sourceKey . ':' . $choice['code'];
            $options = [];
            foreach ($choice['entry_ids'] as $id) {
                $entry = $find((int) $id);
                if ($entry && $rules->targetAvailable($entry) && ($choice['mode'] !== 'expertise' || $entry->type === 'skill')) {
                    $options[$entry->id] = $entry;
                }
            }
            $selected = $choices[$key] ?? [];
            $valid = count($selected) <= $choice['count'] && !array_diff($selected, array_keys($options));
            $spellIds = array_keys(array_filter($options, static fn($entry) => $entry->type === 'spell'));
            $spells = $spellIds ? (new Query())->from('{{%spell_definition}}')->where(['catalog_entry_id' => $spellIds])->indexBy('catalog_entry_id')->all() : [];
            $optionData = array_map(static function ($entry) use ($spells): array {
                $data = $entry->getAttributes(['id', 'type', 'title']);
                if ($entry->type === 'spell') {
                    $data['body'] = $entry->body;
                    $data['spell'] = $spells[$entry->id] ?? null;
                }
                return $data;
            }, array_values($options));
            $groups[$key] = ['key' => $key, 'source_title' => $source->title, 'title' => $choice['title'], 'count' => $choice['count'], 'mode' => $choice['mode'], 'options' => $optionData, 'selected_entry_ids' => $selected];
            if (!$valid) {
                $errors[] = 'Группа «' . $choice['title'] . '»: выберите допустимые записи.';
            }
            if (!$valid || count($selected) !== $choice['count'] || count($options) < $choice['count']) {
                $complete = false;
                $issues[] = 'Не завершён выбор «' . $choice['title'] . '» (' . $source->title . ').';
            }
            if ($valid) {
                foreach ($selected as $id) {
                    $requirements[] = ['entry' => $options[$id], 'condition' => $book($id)['prerequisites'], 'choices' => []];
                    $activate($options[$id], $choice['mode'], $depth + 1, $source, $sourceKey, $key . ':' . $id);
                }
            }
        };
        $visit = function (CatalogEntry $source, int $depth, string $instance = 'root', string $origin = 'root') use (&$visit, &$activate, $processGroup, $scalarGroup, &$seen, &$stack, &$instances, &$complete, &$issues, &$hitPoints, &$bonuses, &$fixedSources, &$traits, &$sources, &$defenses, &$effects, &$resourceRules, &$grantRules, &$requirements, $context, $rules, $find, $book, $profile, &$casting, $choices, $character, &$proficient): void {
            $sourceKey = $source->id . ($instance === 'root' ? '' : '~' . $instance);
            if (isset($stack[$source->id]) || $depth > 12 || count($seen) >= 300) {
                $complete = false;
                $issues[] = 'В выдачах обнаружен цикл или слишком глубокая связь.';
                return;
            }
            if (isset($seen[$sourceKey])) {
                return;
            }
            $seen[$sourceKey] = true;
            $stack[$source->id] = true;
            $instances[$sourceKey] = ['source_entry_id' => $source->id, 'instance_key' => $instance, 'origin' => $origin];
            $data = $book($source->id);
            $localChoices = [];
            foreach ($data['choices'] as $group) {
                $values = $group['values'];
                if (in_array($group['kind'], ['spell_list', 'proficiency', 'weapon_mastery'], true)) {
                    $group['values'] = array_values(array_filter($values, static fn($id) => $find((int) $id) !== null));
                }
                $selected = $scalarGroup($sourceKey . ':' . $group['code'], $source->title, $group);
                $localChoices[$group['code']] = $selected;
                if ($group['kind'] === 'proficiency') {
                    foreach ($selected as $value) {
                        if ($find((int) $value)?->type === 'skill') {
                            $proficient[(int) $value] = true;
                        }
                    }
                }
                if (in_array($group['kind'], ['size', 'language', 'mastery', 'weapon_mastery', 'proficiency'], true)) {
                    foreach ($selected as $value) {
                        $traits[] = ['source_id' => $source->id, 'source_title' => $source->title, 'kind' => $group['kind'], 'value' => $value];
                    }
                }
            }
            $requirements[] = ['entry' => $source, 'condition' => $data['prerequisites'], 'choices' => $localChoices];
            foreach ($data['effects'] as $effect) {
                $effects[] = $effect + ['choices' => $localChoices, 'source_id' => $source->id];
            }
            foreach ($data['resources'] as $resource) {
                if ($resource['level'] <= $context['level']) {
                    $resourceRules[] = $resource + ['source_id' => $source->id, 'source_title' => $source->title, 'instance_key' => $sourceKey];
                }
            }
            foreach ((new Query())->from('{{%entry_bonus}}')->where(['source_entry_id' => $source->id])->all() as $bonus) {
                $bonuses[$bonus['code']] = ($bonuses[$bonus['code']] ?? 0) + $bonus['amount'];
                $fixedSources[$sourceKey]['source_id'] = $source->id;
                $fixedSources[$sourceKey]['bonuses'][$bonus['code']] = $bonus['amount'];
            }
            foreach ((new Query())->from('{{%entry_trait}}')->where(['source_entry_id' => $source->id])->all() as $trait) {
                $traits[] = ['source_id' => $source->id, 'source_title' => $source->title, 'kind' => $trait['kind'], 'value' => $trait['value']];
            }
            $ability = (new Query())->select('ability_code')->from('{{%entry_spellcasting}}')->where(['source_entry_id' => $source->id])->scalar();
            if ($data['casting_ability_code'] !== null) {
                $ability = $data['casting_ability_code'];
            }
            if ($data['casting_choice_code'] !== null) {
                $ability = $localChoices[$data['casting_choice_code']][0] ?? null;
            }
            if ($ability !== false) {
                $sources[$sourceKey] = ['source_id' => $source->id, 'source_title' => $source->title, 'instance_key' => $sourceKey, 'ability_code' => $ability, 'spells' => $sources[$sourceKey]['spells'] ?? []];
            }
            $defense = (new Query())->from('{{%entry_unarmored_defense}}')->where(['source_entry_id' => $source->id])->one();
            if ($defense) {
                $defenses[] = $defense + ['source_title' => $source->title];
            }
            $rule = $rules->read($source->id) ?? ['hit_point_bonus' => 0, 'grants' => [], 'choices' => []];
            $hitPoints += $rule['hit_point_bonus'];
            foreach ($data['unlocks'] as $unlock) {
                if ($unlock['level'] <= ($unlock['level_basis'] === 'class' ? $context['class_levels'][$character->class_entry_id] ?? 0 : $context['level'])) {
                    $rule['grants'][] = ['entry_id' => $unlock['entry_id'], 'mode' => 'grant'];
                }
            }
            foreach ($data['spell_grants'] as $grant) {
                if ($grant['level'] <= $context['level']) {
                    $rule['grants'][] = ['entry_id' => $grant['spell_id'], 'mode' => 'grant'];
                    $grantRules[] = $grant + ['source_key' => $sourceKey, 'choices' => $localChoices];
                }
            }
            foreach ($rule['grants'] as $i => $grant) {
                $entry = $find((int) $grant['entry_id']);
                if (!$entry || !$rules->targetAvailable($entry)) {
                    $complete = false;
                    $issues[] = 'Недоступная выдача записи «' . $source->title . '».';
                    continue;
                }
                $activate($entry, $grant['mode'], $depth + 1, $source, $sourceKey, $sourceKey . ':grant:' . $i);
            }
            foreach ($rule['choices'] as $choice) {
                $processGroup($source, $sourceKey, $choice, $depth);
            }
            foreach ($data['spell_picks'] as $pick) {
                $classes = $pick['list_choice_code'] === null ? $pick['class_ids'] : array_map('intval', $localChoices[$pick['list_choice_code']] ?? []);
                $ids = (new Query())->select('s.catalog_entry_id')->distinct()->from(['s' => '{{%spell_definition}}'])->innerJoin(['c' => '{{%class_spell}}'], '[[c.spell_entry_id]]=[[s.catalog_entry_id]]')->where(['c.class_entry_id' => $classes, 's.level' => $pick['spell_level']])->column();
                $processGroup($source, $sourceKey, ['code' => $pick['code'], 'title' => $pick['title'], 'count' => $pick['count'], 'mode' => 'grant', 'entry_ids' => $ids], $depth);
                foreach ($choices[$sourceKey . ':' . $pick['code']] ?? [] as $id) {
                    $grantRules[] = $pick + ['spell_id' => $id, 'source_key' => $sourceKey, 'choices' => $localChoices];
                }
                $castAbility = $pick['ability_choice_code'] === null ? $pick['ability_code'] : $localChoices[$pick['ability_choice_code']][0] ?? null;
                if ($castAbility !== null) {
                    $sources[$sourceKey] ??= ['source_id' => $source->id, 'source_title' => $source->title, 'instance_key' => $sourceKey, 'ability_code' => null, 'spells' => []];
                    $sources[$sourceKey]['ability_code'] = $castAbility;
                }
            }
            unset($stack[$source->id]);
        };
        foreach (['species_entry_id' => 'species', 'class_entry_id' => 'class', 'background_entry_id' => 'background', 'subclass_entry_id' => 'subclass', 'species_variant_entry_id' => 'species_variant'] as $field => $type) {
            $id = $character->{$field};
            if ($id === null) {
                if (in_array($type, ['species', 'class', 'background'], true)) {
                    $configured = false;
                    $complete = false;
                }
                continue;
            }
            $source = $find($id);
            if (!$source || $source->type !== $type) {
                $configured = false;
                $complete = false;
                continue;
            }
            $visit($source, 0);
        }
        if ($character->species_entry_id !== null && $book($character->species_entry_id)['variant_required'] && $character->species_variant_entry_id === null) {
            $complete = false;
            $issues[] = 'Выберите обязательный вариант вида.';
        }
        if ($character->class_entry_id !== null && $character->target_level >= $book($character->class_entry_id)['subclass_level'] && $character->subclass_entry_id === null) {
            $complete = false;
            $issues[] = 'На этом уровне выберите подкласс.';
        }
        if ($background['feat'] !== null && $entry = $find($background['feat']['id'])) {
            $activate($entry, 'grant', 0, null, '', 'background:feat');
        }
        // Abilities are applied first. Other formulae use the final ability modifiers.
        $context['entry_ids'] = array_values(array_unique(array_merge($context['entry_ids'], array_keys($entries))));
        $context['proficiency_ids'] = array_map('intval', array_keys($proficient));
        $context['proficiencies'] = array_column(array_filter($traits, static fn($t) => in_array($t['kind'], ['armor', 'weapon', 'proficiency'], true)), 'value');
        $classMagic = $character->class_entry_id === null ? null : (new Query())->from('{{%class_level_rule}}')->where(['class_entry_id' => $character->class_entry_id, 'level' => $character->target_level])->one();
        $context['spellcasting'] = (($classMagic['kind'] ?? 'none') !== 'none') || !empty($sources);
        $baseContext = $context;
        $abilityEffects = array_filter($effects, static fn($e) => str_starts_with($e['target'], 'ability_'));
        $otherEffects = array_filter($effects, static fn($e) => !str_starts_with($e['target'], 'ability_'));
        $abilityResult = RuleEffects::calculate($abilityEffects, $context, $bonuses);
        $context = $abilityResult['context'];
        $result = RuleEffects::calculate($otherEffects, $context, $abilityResult['bonuses']);
        $bonuses = $result['bonuses'];
        $casters = [];
        foreach (array_filter([$character->class_entry_id, $character->subclass_entry_id]) as $casterId) {
            if ($profile !== null && $source = $find($casterId)) {
                if ($casterId === $character->subclass_entry_id && !(new Query())->from('{{%class_level_rule}}')->where(['class_entry_id' => $casterId])->exists()) {
                    continue;
                }
                $excluded = [];
                foreach ($grantRules as $grant) {
                    if ($grant['always_prepared'] && !$grant['count_against_limit']) {
                        $excluded[] = $grant['spell_id'];
                    }
                }
                $current = (new SpellcastingRules())->get($casterId, $profile, $choices, (int) $character->target_level, $bonuses, $excluded, $sources[$casterId]['ability_code'] ?? null);
                if (!$current['configured']) {
                    $complete = false;
                    $issues = array_merge($issues, $current['issues']);
                }
                $casters[] = $current + ['source_id' => $casterId, 'source_title' => $source->title];
                if ($casterId === $character->class_entry_id || $current['enabled']) {
                    $casting = $current;
                }
                if ($current['enabled']) {
                    $sourceKey = (string) $source->id;
                    $sources[$sourceKey] ??= ['source_id' => $source->id, 'source_title' => $source->title, 'ability_code' => $current['ability_code'], 'spells' => []];
                    $sources[$sourceKey]['ability_code'] = $current['ability_code'];
                }
                foreach ($current['groups'] as $group) {
                    $processGroup($source, (string) $source->id, $group, 0);
                }
            }
        }
        foreach ($requirements as $requirement) {
            $local = $context;
            $fixed = [];
            foreach ($fixedSources as $sourceBonuses) {
                if ($sourceBonuses['source_id'] !== $requirement['entry']->id) {
                    foreach ($sourceBonuses['bonuses'] as $code => $amount) {
                        $fixed[$code] = ($fixed[$code] ?? 0) + $amount;
                    }
                }
            }
            $otherAbilityEffects = array_filter($abilityEffects, static fn($effect) => $effect['source_id'] !== $requirement['entry']->id);
            $local['abilities'] = RuleEffects::calculate($otherAbilityEffects, $baseContext, $fixed)['context']['abilities'];
            $local['choices'] = $requirement['choices'];
            if (!RuleMath::matches($requirement['condition'], $local)) {
                $complete = false;
                $issues[] = 'Не выполнены требования записи «' . $requirement['entry']->title . '».';
            }
        }
        $distinct = [];
        foreach ($instances as $key => $instance) {
            $data = $book($instance['source_entry_id']);
            if (!$data['repeatable'] || !$data['distinct_choices']) {
                continue;
            }
            $values = [];
            foreach ($data['distinct_choices'] as $code) {
                $values[$code] = $scalar[$key . ':' . $code] ?? [];
            }
            if (in_array([], $values, true)) {
                continue;
            }
            $signature = $instance['source_entry_id'] . ':' . json_encode($values);
            if (isset($distinct[$signature])) {
                $complete = false;
                $issues[] = 'Повторное получение требует другого варианта выбора.';
            }
            $distinct[$signature] = true;
        }
        foreach ($choices as $key => $ids) {
            if (!isset($groups[$key]) && $ids !== []) {
                $errors[] = 'Сохранён выбор неактивной группы «' . $key . '».';
            }
        }
        foreach ($scalar as $key => $values) {
            if (!isset($scalarGroups[$key]) && $values !== []) {
                $errors[] = 'Сохранено значение неактивной группы «' . $key . '».';
            }
        }
        foreach (array_keys($expertise) as $id) {
            if (!isset($proficient[$id])) {
                unset($expertise[$id]);
                $errors[] = 'Экспертиза требует владения навыком.';
            }
        }
        foreach ($grantRules as $grant) {
            if (isset($sources[$grant['source_key']]['spells'][$grant['spell_id']])) {
                $sources[$grant['source_key']]['spells'][$grant['spell_id']]['grants'][] = ['always_prepared' => $grant['always_prepared'], 'count_against_limit' => $grant['count_against_limit'], 'cast_with_slots' => $grant['cast_with_slots'], 'free_uses' => max(0, RuleMath::value($grant['free_uses'], $context)), 'recovery' => $grant['recovery'], 'cast_level' => $grant['cast_level']];
            }
        }
        foreach ($sources as &$source) {
            $source['spells'] = array_values($source['spells']);
            if ($source['spells'] && $source['ability_code'] === null) {
                $complete = false;
                $issues[] = 'Выберите магическую характеристику: ' . $source['source_title'] . '.';
            }
        }
        unset($source);
        $resources = [];
        foreach ($resourceRules as $resource) {
            $resources[] = ['source_id' => $resource['source_id'], 'source_title' => $resource['source_title'], 'instance_key' => $resource['instance_key'], 'code' => $resource['code'], 'title' => $resource['title'], 'maximum' => max(0, RuleMath::value($resource['maximum'], $context)), 'recovery' => $resource['recovery']];
        }
        $selectedSize = [];
        foreach ($scalarGroups as $group) {
            if ($group['kind'] === 'size') {
                $selectedSize = array_merge($selectedSize, $group['selected_values']);
            }
        }
        return ['casters' => $casters, 'instances' => $instances, 'rule_selections' => $scalar, 'scalar_groups' => array_values($scalarGroups), 'resources' => $resources, 'targeted_bonuses' => $result['targeted'], 'ability_caps' => $abilityResult['caps'], 'selected_size' => isset($selectedSize[0]) ? (new Query())->select('title')->from('{{%rule_vocabulary}}')->where(['kind' => 'size', 'code' => $selectedSize[0]])->scalar() ?: $selectedSize[0] : null, 'spell_sources' => array_values($sources), 'unarmored_defenses' => $defenses, 'configured' => $configured, 'complete' => $complete && !$errors, 'selections' => $choices, 'groups' => array_values($groups), 'hit_point_bonus' => $hitPoints + ($bonuses['hit_points'] ?? 0), 'bonuses' => $bonuses, 'traits' => $traits, 'spellcasting' => $casting, 'entries' => array_values($entries), 'skill_entry_ids' => array_values(array_diff(array_map('intval', array_keys($proficient)), $baseSkillIds)), 'proficient_skill_entry_ids' => array_map('intval', array_keys($proficient)), 'expertise_skill_entry_ids' => array_map('intval', array_keys($expertise)), 'validation_errors' => array_values(array_unique($errors)), 'issues' => array_values(array_unique(array_merge($issues, $errors)))];
    }
}
