<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use yii\db\{Query, JsonExpression};

class Rulebook
{
    public const TARGETS = ['ability_str', 'ability_dex', 'ability_con', 'ability_int', 'ability_wis', 'ability_cha', 'hit_points', 'armor_class', 'initiative', 'speed', 'spell_attack', 'spell_save', 'skill', 'saving_throw', 'saving_throw_str', 'saving_throw_dex', 'saving_throw_con', 'saving_throw_int', 'saving_throw_wis', 'saving_throw_cha', 'weapon_attack', 'weapon_damage', 'cantrip_count', 'known_count', 'prepared_count', 'ability_cap', 'ability_choice', 'ability_cap_choice'];
    public const RECOVERY = ['none', 'short_rest', 'long_rest', 'dawn', 'initiative'];
    public const FEATURES = ['general', 'class_feature', 'invocation', 'metamagic', 'maneuver', 'item_plan'];
    public static function empty(): array
    {
        return ['parent_entry_id' => null, 'variant_required' => false, 'subclass_level' => 3, 'selection_level' => 1, 'feature_kind' => 'general', 'repeatable' => false, 'distinct_choices' => [], 'prerequisites' => RuleMath::condition(), 'effects' => [], 'choices' => [], 'casting_choice_code' => null, 'casting_ability_code' => null, 'spell_list_class_ids' => [], 'unlocks' => [], 'progression' => [], 'spell_grants' => [], 'spell_picks' => [], 'resources' => [], 'item' => null, 'sources' => [], 'ready' => false];
    }
    public function read(int $id): array
    {
        $rules = (new Query())->from('{{%entry_rules}}')->where(['entry_id' => $id])->one();
        $data = self::empty();
        if ($rules !== false && $rules !== null) {
            $data = array_replace($data, [
                'variant_required' => (bool) $rules['variant_required'],
                'subclass_level' => (int) $rules['subclass_level'],
                'feature_kind' => $rules['feature_kind'],
                'repeatable' => (bool) $rules['repeatable'],
                'casting_ability_code' => $rules['casting_ability_code'],
                'casting_choice_code' => $rules['casting_choice_code'],
                'prerequisites' => $this->json($rules['prerequisites'], RuleMath::condition()),
                'ready' => (bool) $rules['ready'],
            ]);
        }

        $type = (new Query())->select('type')->from('{{%catalog_entry}}')->where(['id' => $id])->scalar();
        if (in_array($type, ['subclass', 'species_variant'], true)) {
            $table = $type === 'subclass' ? 'subclass_definition' : 'species_variant_definition';
            $parent = $type === 'subclass' ? 'class_entry_id' : 'species_entry_id';
            $definition = (new Query())->from('{{%' . $table . '}}')->where(['catalog_entry_id' => $id])->one();
            if ($definition) {
                $data['parent_entry_id'] = (int) $definition[$parent];
                $data['selection_level'] = (int) $definition['selection_level'];
            }
        }

        $choices = (new Query())->from('{{%entry_rule_choice}}')->where(['source_entry_id' => $id])->orderBy('code')->all();
        foreach ($choices as $choice) {
            $data['choices'][] = [
                'code' => $choice['code'],
                'title' => $choice['title'],
                'kind' => $choice['kind'],
                'count' => (int) $choice['choice_count'],
                'values' => (new Query())->select('value')->from('{{%entry_rule_choice_value}}')
                    ->where(['source_entry_id' => $id, 'choice_code' => $choice['code']])->orderBy('value')->column(),
            ];
        }
        $data['distinct_choices'] = (new Query())->select('choice_code')->from('{{%entry_rule_distinct_choice}}')
            ->where(['source_entry_id' => $id])->orderBy('choice_code')->column();
        foreach ((new Query())->from('{{%entry_rule_effect}}')->where(['source_entry_id' => $id])->orderBy('id')->all() as $effect) {
            $data['effects'][] = [
                'target' => $effect['target'],
                'target_entry_id' => $effect['target_entry_id'] === null ? null : (int) $effect['target_entry_id'],
                'choice_code' => $effect['choice_code'],
                'formula' => $this->json($effect['formula'], ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null]),
                'condition' => $this->json($effect['condition'], RuleMath::condition()),
            ];
        }
        $data['spell_list_class_ids'] = array_map('intval', (new Query())->select('class_entry_id')->from('{{%entry_spell_list_class}}')
            ->where(['source_entry_id' => $id])->orderBy('class_entry_id')->column());
        foreach ((new Query())->from('{{%entry_level_grant}}')->where(['source_entry_id' => $id])->orderBy(['level' => SORT_ASC, 'target_entry_id' => SORT_ASC])->all() as $grant) {
            $data['unlocks'][] = ['entry_id' => (int) $grant['target_entry_id'], 'level' => (int) $grant['level'], 'level_basis' => $grant['level_basis']];
        }
        foreach ((new Query())->from('{{%class_level_rule}}')->where(['class_entry_id' => $id])->orderBy('level')->all() as $row) {
            $data['progression'][] = [
                'level' => (int) $row['level'],
                'proficiency_bonus' => (int) $row['proficiency_bonus'],
                'kind' => $row['kind'],
                'cantrip_count' => (int) $row['cantrip_count'],
                'known_count' => (int) $row['known_count'],
                'prepared_count' => (int) $row['prepared_count'],
                'pact_level' => (int) $row['pact_level'],
                'recovery' => $row['recovery'],
                'change_on' => $row['change_on'],
                'slots' => array_map(static fn(array $slot): array => ['level' => (int) $slot['spell_level'], 'count' => (int) $slot['count']], (new Query())->select(['spell_level', 'count'])->from('{{%class_level_slot}}')->where(['class_entry_id' => $id, 'level' => $row['level']])->orderBy('spell_level')->all()),
            ];
        }
        foreach ((new Query())->from('{{%entry_spell_grant}}')->where(['source_entry_id' => $id])->orderBy('code')->all() as $grant) {
            $data['spell_grants'][] = [
                'code' => $grant['code'], 'spell_id' => (int) $grant['spell_entry_id'], 'level' => (int) $grant['level'],
                'level_basis' => $grant['level_basis'], 'always_prepared' => (bool) $grant['always_prepared'],
                'count_against_limit' => (bool) $grant['count_against_limit'], 'cast_with_slots' => (bool) $grant['cast_with_slots'],
                'free_uses' => $this->json($grant['free_uses'], ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null]),
                'recovery' => $grant['recovery'], 'cast_level' => $grant['cast_level'] === null ? null : (int) $grant['cast_level'],
            ];
        }
        foreach ((new Query())->from('{{%entry_spell_pick}}')->where(['source_entry_id' => $id])->orderBy('code')->all() as $pick) {
            $data['spell_picks'][] = [
                'code' => $pick['code'], 'title' => $pick['title'],
                'class_ids' => array_map('intval', (new Query())->select('class_entry_id')->from('{{%entry_spell_pick_class}}')->where(['source_entry_id' => $id, 'pick_code' => $pick['code']])->orderBy('class_entry_id')->column()),
                'list_choice_code' => $pick['list_choice_code'], 'spell_level' => (int) $pick['spell_level'], 'count' => (int) $pick['choice_count'],
                'ability_choice_code' => $pick['ability_choice_code'], 'ability_code' => $pick['ability_code'],
                'always_prepared' => (bool) $pick['always_prepared'], 'count_against_limit' => (bool) $pick['count_against_limit'],
                'cast_with_slots' => (bool) $pick['cast_with_slots'],
                'free_uses' => $this->json($pick['free_uses'], ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null]),
                'recovery' => $pick['recovery'], 'cast_level' => $pick['cast_level'] === null ? null : (int) $pick['cast_level'],
            ];
        }
        foreach ((new Query())->from('{{%entry_resource}}')->where(['source_entry_id' => $id])->orderBy('code')->all() as $resource) {
            $data['resources'][] = [
                'code' => $resource['code'], 'title' => $resource['title'],
                'maximum' => $this->json($resource['maximum'], ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null]),
                'recovery' => $resource['recovery'], 'level' => (int) $resource['level'],
            ];
        }
        $item = (new Query())->from('{{%entry_item_rule}}')->where(['item_entry_id' => $id])->one();
        if ($item) {
            $magic = $item['magic_rarity'] === null ? null : [
                'rarity' => $item['magic_rarity'], 'attunement' => (bool) $item['magic_attunement'],
                'charges' => $this->json($item['magic_charges'], ['base' => 0, 'terms' => [], 'minimum' => null, 'maximum' => null]),
                'recovery' => $item['magic_recovery'], 'plan_min_level' => (int) $item['magic_plan_min_level'],
                'prerequisites' => $this->json($item['magic_prerequisites'], RuleMath::condition()),
            ];
            $data['item'] = ['cost_cp' => (int) $item['cost_cp'], 'weight_grams' => (int) $item['weight_grams'], 'weapon' => null, 'armor' => null, 'magic' => $magic];
            $weapon = (new Query())->from('{{%weapon_definition}}')->where(['catalog_entry_id' => $id])->one();
            if ($weapon) {
                $data['item']['weapon'] = [
                    'category' => $weapon['category'], 'damage_count' => (int) $weapon['damage_count'], 'damage_die' => (int) $weapon['damage_die'],
                    'damage_type' => $weapon['damage_type'], 'properties' => (new Query())->select('code')->from('{{%weapon_property}}')->where(['item_entry_id' => $id])->orderBy('code')->column(),
                    'mastery' => $weapon['mastery'], 'range_normal' => (int) $weapon['range_normal'], 'range_long' => (int) $weapon['range_long'], 'versatile_die' => $weapon['versatile_die'] === null ? null : (int) $weapon['versatile_die'],
                ];
            }
            $armor = (new Query())->select(['category', 'strength_min', 'stealth_disadvantage'])->from('{{%armor_definition}}')->where(['catalog_entry_id' => $id])->one();
            if ($armor) $data['item']['armor'] = ['category' => $armor['category'], 'strength_min' => (int) $armor['strength_min'], 'stealth_disadvantage' => (bool) $armor['stealth_disadvantage']];
        }
        $data['sources'] = (new Query())->from('{{%entry_source_reference}}')->where(['entry_id' => $id])->orderBy(['source_id' => SORT_ASC, 'page' => SORT_ASC])->all();
        return $data;
    }
    public function options(?RuleProfile $profile): array
    {
        $entries = [];
        if ($profile) {
            foreach (CatalogEntry::find()->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC])->all() as $entry) {
                if ((new CatalogAvailability())->hasCompatibleOrigin($entry, $profile)) {
                    $entries[] = $entry->getAttributes(['id', 'type', 'title', 'is_published', 'revision']);
                }
            }
        }
        return ['entries' => $entries, 'vocabulary' => (new Query())->from('{{%rule_vocabulary}}')->orderBy(['kind' => SORT_ASC, 'title' => SORT_ASC])->all(), 'sources' => (new Query())->select(['id', 'title'])->from('{{%content_source}}')->orderBy('title')->all(), 'targets' => self::TARGETS, 'recovery' => self::RECOVERY, 'features' => self::FEATURES];
    }
    public function validate(CatalogEntry $entry, $input, ?string $pendingItemKind = null): array
    {
        if (!is_array($input) || array_diff(array_keys($input), array_keys(self::empty()))) {
            throw new \InvalidArgumentException('Неизвестные параметры правил книги.');
        }
        $data = array_replace(self::empty(), $input);
        $refs = [];
        RuleMath::number($data['selection_level'], 1, 20);
        RuleMath::number($data['subclass_level'], 1, 20);
        if (!is_bool($data['variant_required'])) {
            throw new \InvalidArgumentException('Укажите обязательность варианта вида.');
        }
        if (!in_array($data['feature_kind'], self::FEATURES, true) || !is_bool($data['repeatable']) || !is_bool($data['ready'])) {
            throw new \InvalidArgumentException('Проверьте категорию, повторяемость и готовность правила.');
        }
        if (in_array($entry->type, ['subclass', 'species_variant'], true)) {
            $type = $entry->type === 'subclass' ? 'class' : 'species';
            $this->reference($data['parent_entry_id'], [$type], $entry, $refs);
            $table = $type === 'class' ? 'class_definition' : 'species_definition';
            if (!(new Query())->from('{{%' . $table . '}}')->where(['catalog_entry_id' => $data['parent_entry_id']])->exists()) {
                throw new \InvalidArgumentException('Сначала настройте базовые свойства родительского класса или вида.');
            }
        } elseif ($data['parent_entry_id'] !== null) {
            throw new \InvalidArgumentException('Родитель задаётся только у подкласса или варианта вида.');
        }
        foreach (['effects', 'choices', 'unlocks', 'progression', 'spell_grants', 'spell_picks', 'resources', 'sources', 'distinct_choices'] as $key) {
            RuleMath::list($data[$key], $key === 'unlocks' ? 1000 : 100);
        }
        RuleMath::validateCondition($data['prerequisites']);
        $this->conditionReferences($data['prerequisites'], $entry, $refs);
        RuleMath::list($data['spell_list_class_ids'], 100);
        foreach ($data['spell_list_class_ids'] as $id) {
            $this->reference($id, ['class'], $entry, $refs);
        }
        if ($data['casting_ability_code'] !== null && !in_array($data['casting_ability_code'], ['str', 'dex', 'con', 'int', 'wis', 'cha'], true)) {
            throw new \InvalidArgumentException('Выберите магическую характеристику.');
        }
        if (($data['casting_ability_code'] !== null || $data['casting_choice_code'] !== null) && !in_array($entry->type, ['class', 'subclass'], true)) {
            throw new \InvalidArgumentException('Магическая характеристика таблицы развития задаётся только у класса или подкласса.');
        }
        $codes = [];
        foreach ($data['choices'] as $choice) {
            RuleMath::keys($choice, ['code', 'title', 'kind', 'count', 'values']);
            $this->code($choice['code']);
            RuleMath::text($choice['title']);
            RuleMath::number($choice['count'], 1, 100);
            RuleMath::list($choice['values'], 1000);
            if (!in_array($choice['kind'], ['ability', 'size', 'language', 'spell_list', 'mastery', 'weapon_mastery', 'proficiency'], true)) {
                throw new \InvalidArgumentException('Выберите тип выбора игрока.');
            }
            if (count($choice['values']) < $choice['count'] || count(array_unique($choice['values'], SORT_REGULAR)) !== count($choice['values'])) {
                throw new \InvalidArgumentException('В группе выбора недостаточно разных вариантов.');
            }
            if (in_array($choice['kind'], ['ability', 'size', 'spell_list'], true) && $choice['count'] !== 1) {
                throw new \InvalidArgumentException('Характеристика, размер и список магии выбираются по одному.');
            }
            foreach ($choice['values'] as $value) {
                RuleMath::text($value, 64);
                if ($choice['kind'] === 'ability' && !in_array($value, ['str', 'dex', 'con', 'int', 'wis', 'cha'], true)) {
                    throw new \InvalidArgumentException('Недопустимая характеристика выбора.');
                }
                if (in_array($choice['kind'], ['size', 'language', 'mastery'], true) && !(new Query())->from('{{%rule_vocabulary}}')->where(['kind' => $choice['kind'], 'code' => $value])->exists()) {
                    throw new \InvalidArgumentException('Выберите значение из справочника.');
                }
                if (in_array($choice['kind'], ['spell_list', 'proficiency', 'weapon_mastery'], true)) {
                    if (!ctype_digit($value)) {
                        throw new \InvalidArgumentException('Выберите связанную запись.');
                    }
                    $this->reference((int) $value, $choice['kind'] === 'spell_list' ? ['class'] : ($choice['kind'] === 'weapon_mastery' ? ['item'] : ['skill', 'item']), $entry, $refs);
                    if ($choice['kind'] === 'weapon_mastery' && !(new Query())->from('{{%weapon_definition}}')->where(['catalog_entry_id' => (int) $value])->exists()) {
                        throw new \InvalidArgumentException('Для мастерства выберите оружие с определением.');
                    }
                }
            }
            $codes[] = $choice['code'];
        }
        if (count(array_unique($codes)) !== count($codes)) {
            throw new \InvalidArgumentException('Коды выборов должны быть разными.');
        }
        foreach ($data['distinct_choices'] as $code) {
            if (!is_string($code) || !in_array($code, $codes, true)) {
                throw new \InvalidArgumentException('Для различающихся повторов выберите существующие группы.');
            }
        }
        if ($data['casting_choice_code'] !== null && !$this->choiceOf($data['choices'], $data['casting_choice_code'], 'ability')) {
            throw new \InvalidArgumentException('Выберите группу магической характеристики.');
        }
        foreach ($data['effects'] as $effect) {
            RuleMath::keys($effect, ['target', 'target_entry_id', 'choice_code', 'formula', 'condition']);
            if (!in_array($effect['target'], self::TARGETS, true)) {
                throw new \InvalidArgumentException('Выберите цель эффекта.');
            }
            if ($effect['target_entry_id'] !== null) {
                $this->reference($effect['target_entry_id'], in_array($effect['target'], ['weapon_attack', 'weapon_damage'], true) ? ['item'] : ['skill'], $entry, $refs);
            }
            if ($effect['target'] === 'skill' && $effect['target_entry_id'] === null) {
                throw new \InvalidArgumentException('Выберите навык, к которому применяется бонус.');
            }
            if (in_array($effect['target'], ['ability_choice', 'ability_cap_choice'], true) && $effect['choice_code'] === null) {
                throw new \InvalidArgumentException('Укажите группу выбора характеристики эффекта.');
            }
            if ($effect['choice_code'] !== null && !$this->choiceOf($data['choices'], $effect['choice_code'], 'ability')) {
                throw new \InvalidArgumentException('Выберите группу характеристики для эффекта.');
            }
            RuleMath::validateFormula($effect['formula']);
            RuleMath::validateCondition($effect['condition']);
            $this->conditionReferences($effect['condition'], $entry, $refs);
            $this->formulaReferences($effect['formula'], $entry, $refs);
        }
        $unlockKeys = [];
        foreach ($data['unlocks'] as $unlock) {
            RuleMath::keys($unlock, ['entry_id', 'level', 'level_basis']);
            $this->reference($unlock['entry_id'], ['feature', 'feat', 'spell', 'skill', 'item', 'species_variant'], $entry, $refs);
            $this->level($unlock);
            $unlockKeys[] = $unlock['entry_id'] . ':' . $unlock['level'] . ':' . $unlock['level_basis'];
        }
        if (count($unlockKeys) !== count(array_unique($unlockKeys))) {
            throw new \InvalidArgumentException('Уровневые выдачи не должны повторяться.');
        }
        if ($data['progression'] && !in_array($entry->type, ['class', 'subclass'], true)) {
            throw new \InvalidArgumentException('Таблица развития задаётся у класса.');
        }
        $levels = [];
        foreach ($data['progression'] as $row) {
            RuleMath::keys($row, ['level', 'proficiency_bonus', 'kind', 'cantrip_count', 'known_count', 'prepared_count', 'pact_level', 'recovery', 'change_on', 'slots']);
            RuleMath::number($row['level'], 1, 20);
            RuleMath::number($row['proficiency_bonus'], 2, 6);
            RuleMath::number($row['pact_level'], 0, 9);
            if (!in_array($row['kind'], ['none', 'spellcasting', 'pact'], true) || !in_array($row['recovery'], self::RECOVERY, true) || !in_array($row['change_on'], ['level', 'long_rest', 'short_rest'], true)) {
                throw new \InvalidArgumentException('Проверьте тип заклинательства и восстановление.');
            }
            foreach (['cantrip_count', 'known_count', 'prepared_count'] as $key) {
                RuleMath::number($row[$key], 0, 100);
            }
            if ($row['known_count'] && $row['prepared_count'] > $row['known_count']) {
                throw new \InvalidArgumentException('Подготовленных заклинаний больше, чем изученных.');
            }
            RuleMath::list($row['slots'], 9);
            $slotLevels = [];
            foreach ($row['slots'] as $slot) {
                RuleMath::keys($slot, ['level', 'count']);
                RuleMath::number($slot['level'], 1, 9);
                RuleMath::number($slot['count'], 0, 100);
                $slotLevels[] = $slot['level'];
            }
            if (count($slotLevels) !== count(array_unique($slotLevels))) {
                throw new \InvalidArgumentException('Круг ячеек указан повторно.');
            }
            if ($row['kind'] === 'none' && ($row['slots'] || $row['cantrip_count'] || $row['known_count'] || $row['prepared_count'])) {
                throw new \InvalidArgumentException('Для немагического уровня списки и ячейки должны быть пусты.');
            }
            if ($row['kind'] === 'pact' && ($row['pact_level'] < 1 || count($row['slots']) !== 1 || $row['slots'][0]['level'] !== $row['pact_level'])) {
                throw new \InvalidArgumentException('Укажите единый круг ячеек Pact Magic.');
            }
            $levels[] = $row['level'];
        }
        if (count($levels) !== count(array_unique($levels))) {
            throw new \InvalidArgumentException('Уровень класса указан повторно.');
        }
        $grantCodes = [];
        foreach ($data['spell_grants'] as $grant) {
            RuleMath::keys($grant, ['code', 'spell_id', 'level', 'level_basis', 'always_prepared', 'count_against_limit', 'cast_with_slots', 'free_uses', 'recovery', 'cast_level']);
            $this->code($grant['code']);
            $this->reference($grant['spell_id'], ['spell'], $entry, $refs);
            $this->level($grant);
            $this->spellFlags($grant);
            $this->formulaReferences($grant['free_uses'], $entry, $refs);
            $grantCodes[] = $grant['code'];
        }
        if (count($grantCodes) !== count(array_unique($grantCodes))) {
            throw new \InvalidArgumentException('Коды выдач заклинаний должны быть разными.');
        }
        $pickCodes = [];
        foreach ($data['spell_picks'] as $pick) {
            RuleMath::keys($pick, ['code', 'title', 'class_ids', 'list_choice_code', 'spell_level', 'count', 'ability_choice_code', 'ability_code', 'always_prepared', 'count_against_limit', 'cast_with_slots', 'free_uses', 'recovery', 'cast_level']);
            $this->code($pick['code']);
            RuleMath::text($pick['title']);
            RuleMath::number($pick['spell_level'], 0, 9);
            RuleMath::number($pick['count'], 1, 100);
            RuleMath::list($pick['class_ids'], 100);
            foreach ($pick['class_ids'] as $id) {
                $this->reference($id, ['class'], $entry, $refs);
            }
            if ($pick['list_choice_code'] !== null && !$this->choiceOf($data['choices'], $pick['list_choice_code'], 'spell_list')) {
                throw new \InvalidArgumentException('Выберите группу списка заклинаний.');
            }
            if (!$pick['class_ids'] && $pick['list_choice_code'] === null) {
                throw new \InvalidArgumentException('Для магии укажите списки классов или выбор списка.');
            }
            if ($pick['ability_choice_code'] !== null && !$this->choiceOf($data['choices'], $pick['ability_choice_code'], 'ability')) {
                throw new \InvalidArgumentException('Выберите группу магической характеристики.');
            }
            if ($pick['ability_code'] !== null && !in_array($pick['ability_code'], ['str', 'dex', 'con', 'int', 'wis', 'cha'], true)) {
                throw new \InvalidArgumentException('Неверная магическая характеристика.');
            }
            $this->spellFlags($pick);
            $this->formulaReferences($pick['free_uses'], $entry, $refs);
            $pickCodes[] = $pick['code'];
        }
        if (count($pickCodes) !== count(array_unique($pickCodes)) || array_intersect($pickCodes, $codes) || array_intersect($pickCodes, EntryMechanics::MAGIC_CODES)) {
            throw new \InvalidArgumentException('Коды выборов магии должны быть уникальны и не зарезервированы.');
        }
        $resourceCodes = [];
        foreach ($data['resources'] as $resource) {
            RuleMath::keys($resource, ['code', 'title', 'maximum', 'recovery', 'level']);
            $this->code($resource['code']);
            RuleMath::text($resource['title']);
            RuleMath::number($resource['level'], 1, 20);
            RuleMath::validateFormula($resource['maximum']);
            $this->formulaReferences($resource['maximum'], $entry, $refs);
            if (!in_array($resource['recovery'], self::RECOVERY, true)) {
                throw new \InvalidArgumentException('Выберите восстановление ресурса.');
            }
            $resourceCodes[] = $resource['code'];
        }
        if (count($resourceCodes) !== count(array_unique($resourceCodes))) {
            throw new \InvalidArgumentException('Коды ресурсов повторяются.');
        }
        if ($data['item'] !== null) {
            if (!is_array($data['item'])) {
                throw new \InvalidArgumentException('Передайте параметры предмета.');
            }
            $this->validateItem($entry, $data['item'], $pendingItemKind);
            if ($data['item']['magic'] !== null) {
                $this->conditionReferences($data['item']['magic']['prerequisites'], $entry, $refs);
                $this->formulaReferences($data['item']['magic']['charges'], $entry, $refs);
            }
        }
        $sourceKeys = [];
        foreach ($data['sources'] as $source) {
            RuleMath::keys($source, ['source_id', 'page', 'url', 'revision']);
            RuleMath::number($source['source_id'], 1, 2147483647);
            if (!(new Query())->from('{{%content_source}}')->where(['id' => $source['source_id']])->exists()) {
                throw new \InvalidArgumentException('Источник не найден.');
            }
            RuleMath::text($source['page'], 64, true);
            RuleMath::text($source['url'], 1000, true);
            RuleMath::text($source['revision'], 120, true);
            if ($source['url'] !== '' && !preg_match('~^https?://~i', $source['url'])) {
                throw new \InvalidArgumentException('Ссылка источника должна начинаться с http или https.');
            }
            $sourceKeys[] = $source['source_id'] . ':' . $source['page'];
        }
        if (count($sourceKeys) !== count(array_unique($sourceKeys))) {
            throw new \InvalidArgumentException('Ссылка на страницу источника повторяется.');
        }
        if ($data['ready'] && $entry->type === 'class' && count($levels) !== 20) {
            throw new \InvalidArgumentException('Для готового класса заполните все 20 уровней.');
        }
        $this->checkCycles($entry, $data['unlocks']);
        return $data;
    }
    private function validateItem(CatalogEntry $entry, array $item, ?string $pendingItemKind = null): void
    {
        if ($entry->type !== 'item') {
            throw new \InvalidArgumentException('Параметры предмета доступны только предмету.');
        }
        RuleMath::keys($item, ['cost_cp', 'weight_grams', 'weapon', 'armor', 'magic']);
        RuleMath::number($item['cost_cp'], 0, 1000000000);
        RuleMath::number($item['weight_grams'], 0, 1000000000);
        $itemKind = $pendingItemKind ?? (new Query())->select('kind_code')->from('{{%item_definition}}')->where(['catalog_entry_id' => $entry->id])->scalar();
        if ($item['weapon'] !== null && $itemKind !== 'weapon') {
            throw new \InvalidArgumentException('Боевые свойства доступны только предмету вида «оружие».');
        }
        if ($item['armor'] !== null && !in_array($itemKind, ['armor', 'shield'], true)) {
            throw new \InvalidArgumentException('Свойства доспеха доступны только броне или щиту.');
        }
        if ($item['weapon'] !== null) {
            $w = $item['weapon'];
            RuleMath::keys($w, ['category', 'damage_count', 'damage_die', 'damage_type', 'properties', 'mastery', 'range_normal', 'range_long', 'versatile_die']);
            if (!in_array($w['category'], ['simple_melee', 'simple_ranged', 'martial_melee', 'martial_ranged'], true)) {
                throw new \InvalidArgumentException('Выберите категорию оружия.');
            }
            RuleMath::number($w['damage_count'], 1, 20);
            if (!in_array($w['damage_die'], [1, 4, 6, 8, 10, 12, 20], true)) {
                throw new \InvalidArgumentException('Выберите кость урона.');
            }
            $this->vocabulary('damage', $w['damage_type']);
            if ($w['mastery'] !== null) {
                $this->vocabulary('mastery', $w['mastery']);
            }
            RuleMath::list($w['properties'], 30);
            foreach ($w['properties'] as $property) {
                $this->vocabulary('weapon_property', $property);
            }
            if (count($w['properties']) !== count(array_unique($w['properties']))) {
                throw new \InvalidArgumentException('Свойства оружия повторяются.');
            }
            RuleMath::number($w['range_normal'], 0, 100000);
            RuleMath::number($w['range_long'], $w['range_normal'], 100000);
            if ($w['versatile_die'] !== null && !in_array($w['versatile_die'], [4, 6, 8, 10, 12], true)) {
                throw new \InvalidArgumentException('Неверная кость универсального оружия.');
            }
        }
        if ($item['armor'] !== null) {
            RuleMath::keys($item['armor'], ['category', 'strength_min', 'stealth_disadvantage']);
            $this->vocabulary('armor', $item['armor']['category']);
            RuleMath::number($item['armor']['strength_min'], 0, 30);
            if (!is_bool($item['armor']['stealth_disadvantage'])) {
                throw new \InvalidArgumentException('Укажите помеху Скрытности.');
            }
        }
        if ($item['magic'] !== null) {
            $m = $item['magic'];
            RuleMath::keys($m, ['rarity', 'attunement', 'charges', 'recovery', 'plan_min_level', 'prerequisites']);
            if (!in_array($m['rarity'], ['common', 'uncommon', 'rare', 'very_rare', 'legendary', 'artifact'], true) || !is_bool($m['attunement']) || !in_array($m['recovery'], self::RECOVERY, true)) {
                throw new \InvalidArgumentException('Проверьте редкость, настройку и восстановление предмета.');
            }
            RuleMath::number($m['plan_min_level'], 1, 20);
            RuleMath::validateFormula($m['charges']);
            RuleMath::validateCondition($m['prerequisites']);
        }
    }
    public function save(CatalogEntry $entry, array $data): void
    {
        Yii::$app->db->transaction(function () use ($entry, $data): void {
            $this->saveNormalized($entry, $this->validate($entry, $data));
        });
    }
    private function saveNormalized(CatalogEntry $entry, array $data): void
    {
        $db = Yii::$app->db;
        $db->createCommand()->upsert('{{%entry_rules}}', [
            'entry_id' => $entry->id,
            'variant_required' => $data['variant_required'],
            'subclass_level' => $data['subclass_level'],
            'feature_kind' => $data['feature_kind'],
            'repeatable' => $data['repeatable'],
            'casting_ability_code' => $data['casting_ability_code'],
            'casting_choice_code' => $data['casting_choice_code'],
            'prerequisites' => new JsonExpression($data['prerequisites']),
            'ready' => $data['ready'],
        ])->execute();
        foreach (['entry_rule_effect', 'entry_rule_distinct_choice', 'entry_rule_choice_value', 'entry_rule_choice', 'entry_spell_list_class', 'entry_spell_pick_class', 'entry_spell_pick'] as $table) {
            $db->createCommand()->delete('{{%' . $table . '}}', ['source_entry_id' => $entry->id])->execute();
        }
        foreach ($data['choices'] as $choice) {
            $db->createCommand()->insert('{{%entry_rule_choice}}', [
                'source_entry_id' => $entry->id, 'code' => $choice['code'], 'title' => $choice['title'],
                'kind' => $choice['kind'], 'choice_count' => $choice['count'],
            ])->execute();
            foreach ($choice['values'] as $value) {
                $db->createCommand()->insert('{{%entry_rule_choice_value}}', [
                    'source_entry_id' => $entry->id, 'choice_code' => $choice['code'], 'value' => $value,
                ])->execute();
            }
        }
        foreach ($data['distinct_choices'] as $code) {
            $db->createCommand()->insert('{{%entry_rule_distinct_choice}}', ['source_entry_id' => $entry->id, 'choice_code' => $code])->execute();
        }
        foreach ($data['effects'] as $effect) {
            $db->createCommand()->insert('{{%entry_rule_effect}}', [
                'source_entry_id' => $entry->id, 'target' => $effect['target'], 'target_entry_id' => $effect['target_entry_id'],
                'choice_code' => $effect['choice_code'], 'formula' => new JsonExpression($effect['formula']),
                'condition' => new JsonExpression($effect['condition']),
            ])->execute();
        }
        foreach ($data['spell_list_class_ids'] as $classId) {
            $db->createCommand()->insert('{{%entry_spell_list_class}}', ['source_entry_id' => $entry->id, 'class_entry_id' => $classId])->execute();
        }
        foreach ($data['spell_picks'] as $pick) {
            $classes = $pick['class_ids'];
            unset($pick['class_ids']);
            $pick['choice_count'] = $pick['count'];
            unset($pick['count']);
            $pick['free_uses'] = new JsonExpression($pick['free_uses']);
            $db->createCommand()->insert('{{%entry_spell_pick}}', ['source_entry_id' => $entry->id] + $pick)->execute();
            foreach ($classes as $classId) {
                $db->createCommand()->insert('{{%entry_spell_pick_class}}', ['source_entry_id' => $entry->id, 'pick_code' => $pick['code'], 'class_entry_id' => $classId])->execute();
            }
        }
        $db->createCommand()->delete('{{%entry_item_rule}}', ['item_entry_id' => $entry->id])->execute();
        if ($entry->type === 'item' && $data['item'] !== null) {
            $item = $data['item'];
            $magic = $item['magic'];
            $db->createCommand()->insert('{{%entry_item_rule}}', [
                'item_entry_id' => $entry->id, 'cost_cp' => $item['cost_cp'], 'weight_grams' => $item['weight_grams'],
                'magic_rarity' => $magic['rarity'] ?? null, 'magic_attunement' => $magic['attunement'] ?? null,
                'magic_charges' => $magic === null ? null : new JsonExpression($magic['charges']),
                'magic_recovery' => $magic['recovery'] ?? null, 'magic_plan_min_level' => $magic['plan_min_level'] ?? null,
                'magic_prerequisites' => $magic === null ? null : new JsonExpression($magic['prerequisites']),
            ])->execute();
        }
        if (in_array($entry->type, ['subclass', 'species_variant'], true)) {
            $table = $entry->type === 'subclass' ? 'subclass_definition' : 'species_variant_definition';
            $parent = $entry->type === 'subclass' ? 'class_entry_id' : 'species_entry_id';
            $db->createCommand()->upsert('{{%' . $table . '}}', ['catalog_entry_id' => $entry->id, $parent => $data['parent_entry_id'], 'selection_level' => $data['selection_level']])->execute();
        }
        $db->createCommand()->delete('{{%entry_level_grant}}', ['source_entry_id' => $entry->id])->execute();
        foreach ($data['unlocks'] as $row) {
            $db->createCommand()->insert('{{%entry_level_grant}}', ['source_entry_id' => $entry->id, 'target_entry_id' => $row['entry_id'], 'level' => $row['level'], 'level_basis' => $row['level_basis']])->execute();
        }
        if (in_array($entry->type, ['class', 'subclass'], true)) {
            $db->createCommand()->delete('{{%class_level_slot}}', ['class_entry_id' => $entry->id])->execute();
            $db->createCommand()->delete('{{%class_level_rule}}', ['class_entry_id' => $entry->id])->execute();
            foreach ($data['progression'] as $row) {
                $slots = $row['slots'];
                unset($row['slots']);
                $db->createCommand()->insert('{{%class_level_rule}}', ['class_entry_id' => $entry->id] + $row)->execute();
                foreach ($slots as $slot) {
                    $db->createCommand()->insert('{{%class_level_slot}}', ['class_entry_id' => $entry->id, 'level' => $row['level'], 'spell_level' => $slot['level'], 'count' => $slot['count']])->execute();
                }
            }
        }
        foreach (['entry_resource', 'entry_spell_grant'] as $table) {
            $db->createCommand()->delete('{{%' . $table . '}}', ['source_entry_id' => $entry->id])->execute();
        }
        foreach ($data['resources'] as $resource) {
            $resource['maximum'] = new JsonExpression($resource['maximum']);
            $db->createCommand()->insert('{{%entry_resource}}', ['source_entry_id' => $entry->id] + $resource)->execute();
        }
        foreach ($data['spell_grants'] as $grant) {
            $grant['spell_entry_id'] = $grant['spell_id'];
            unset($grant['spell_id']);
            $grant['free_uses'] = new JsonExpression($grant['free_uses']);
            $db->createCommand()->insert('{{%entry_spell_grant}}', ['source_entry_id' => $entry->id] + $grant)->execute();
        }
        if ($entry->type === 'item') {
            $db->createCommand()->delete('{{%weapon_property}}', ['item_entry_id' => $entry->id])->execute();
            $db->createCommand()->delete('{{%weapon_definition}}', ['catalog_entry_id' => $entry->id])->execute();
            if ($data['item']['weapon'] ?? null) {
                $weapon = $data['item']['weapon'];
                $properties = $weapon['properties'];
                unset($weapon['properties']);
                $db->createCommand()->insert('{{%weapon_definition}}', ['catalog_entry_id' => $entry->id] + $weapon)->execute();
                foreach ($properties as $code) {
                    $db->createCommand()->insert('{{%weapon_property}}', ['item_entry_id' => $entry->id, 'code' => $code])->execute();
                }
            }
            if ($data['item']['armor'] ?? null) {
                $db->createCommand()->update('{{%armor_definition}}', $data['item']['armor'], ['catalog_entry_id' => $entry->id])->execute();
            }
        }
        $db->createCommand()->delete('{{%entry_source_reference}}', ['entry_id' => $entry->id])->execute();
        foreach ($data['sources'] as $source) {
            $db->createCommand()->insert('{{%entry_source_reference}}', ['entry_id' => $entry->id] + $source)->execute();
        }
        $refs = $data['spell_list_class_ids'];
        $this->conditionReferences($data['prerequisites'], $entry, $refs);
        if ($data['item']['magic'] ?? null) {
            $this->conditionReferences($data['item']['magic']['prerequisites'], $entry, $refs);
            $this->formulaReferences($data['item']['magic']['charges'], $entry, $refs);
        }
        foreach (array_merge($data['spell_grants'], $data['spell_picks']) as $grant) {
            $this->formulaReferences($grant['free_uses'], $entry, $refs);
        }
        foreach ($data['effects'] as $effect) {
            $this->conditionReferences($effect['condition'], $entry, $refs);
            $this->formulaReferences($effect['formula'], $entry, $refs);
            if ($effect['target_entry_id'] !== null) {
                $refs[] = $effect['target_entry_id'];
            }
        }
        foreach ($data['choices'] as $choice) {
            if (in_array($choice['kind'], ['spell_list', 'proficiency', 'weapon_mastery'], true)) {
                foreach ($choice['values'] as $id) {
                    $refs[] = (int) $id;
                }
            }
        }
        foreach ($data['spell_picks'] as $pick) {
            foreach ($pick['class_ids'] as $id) {
                $refs[] = $id;
            }
        }
        foreach ($data['resources'] as $resource) {
            $this->formulaReferences($resource['maximum'], $entry, $refs);
        }
        $db->createCommand()->delete('{{%entry_rule_reference}}', ['source_entry_id' => $entry->id])->execute();
        foreach (array_unique($refs) as $id) {
            $db->createCommand()->insert('{{%entry_rule_reference}}', ['source_entry_id' => $entry->id, 'target_entry_id' => $id])->execute();
        }
    }
    private function json($value, array $fallback): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        return is_array($value) ? $value : $fallback;
    }

    private function reference($id, array $types, CatalogEntry $entry, array &$refs): void
    {
        RuleMath::number($id, 1, 2147483647);
        $target = CatalogEntry::findOne($id);
        $profile = RuleProfile::findOne($entry->rule_profile_id);
        if (!$target || !in_array($target->type, $types, true) || !$profile || !(new CatalogAvailability())->hasCompatibleOrigin($target, $profile)) {
            throw new \InvalidArgumentException('Связанная запись имеет неподходящий тип или мир.');
        }
        $refs[] = $id;
    }
    private function conditionReferences(array $condition, CatalogEntry $entry, array &$refs): void
    {
        if (isset($condition['op'])) {
            foreach ($condition['conditions'] as $child) {
                $this->conditionReferences($child, $entry, $refs);
            }
        } elseif ($condition['entry_id'] !== null) {
            $this->reference($condition['entry_id'], $condition['kind'] === 'class_level' ? ['class'] : ['class', 'subclass', 'species', 'species_variant', 'feature', 'feat', 'skill', 'item', 'spell'], $entry, $refs);
        }
    }
    private function formulaReferences(array $formula, CatalogEntry $entry, array &$refs): void
    {
        foreach ($formula['terms'] as $term) {
            if ($term['entry_id'] !== null) {
                $this->reference($term['entry_id'], ['class'], $entry, $refs);
            }
        }
    }
    private function spellFlags(array $row): void
    {
        foreach (['always_prepared', 'count_against_limit', 'cast_with_slots'] as $key) {
            if (!is_bool($row[$key])) {
                throw new \InvalidArgumentException('Проверьте режим выдачи заклинания.');
            }
        }
        RuleMath::validateFormula($row['free_uses']);
        if (!in_array($row['recovery'], self::RECOVERY, true)) {
            throw new \InvalidArgumentException('Выберите восстановление магии.');
        }
        if ($row['cast_level'] !== null) {
            RuleMath::number($row['cast_level'], 0, 9);
        }
    }
    private function level(array $row): void
    {
        RuleMath::number($row['level'], 1, 20);
        if (!in_array($row['level_basis'], ['character', 'class'], true)) {
            throw new \InvalidArgumentException('Выберите основание уровня выдачи.');
        }
    }
    private function vocabulary(string $kind, $code): void
    {
        if (!is_string($code) || !(new Query())->from('{{%rule_vocabulary}}')->where(['kind' => $kind, 'code' => $code])->exists()) {
            throw new \InvalidArgumentException('Выберите значение из справочника.');
        }
    }
    private function code($code): void
    {
        if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code)) {
            throw new \InvalidArgumentException('Код правила должен начинаться с буквы и содержать латинские буквы, цифры или подчёркивания.');
        }
    }
    private function choiceOf(array $choices, $code, string $kind): bool
    {
        foreach ($choices as $choice) {
            if ($choice['code'] === $code && $choice['kind'] === $kind) {
                return true;
            }
        }
        return false;
    }
    private function checkCycles(CatalogEntry $entry, array $unlocks): void
    {
        if ($entry->isNewRecord) {
            return;
        }
        $path = [];
        $seen = [];
        $visit = function ($id, $depth) use (&$visit, &$path, &$seen, $entry) {
            if ($id === $entry->id || isset($path[$id]) || $depth > 12) {
                throw new \InvalidArgumentException('Уровневые выдачи образуют цикл или слишком глубоки.');
            }
            if (isset($seen[$id])) {
                return;
            }
            $path[$id] = true;
            $seen[$id] = true;
            $rule = (new CreationRules())->read($id);
            $ids = array_column($rule['grants'] ?? [], 'entry_id');
            foreach ($rule['choices'] ?? [] as $choice) {
                $ids = array_merge($ids, $choice['entry_ids']);
            }
            $ids = array_merge($ids, (new Query())->select('target_entry_id')->from('{{%entry_level_grant}}')->where(['source_entry_id' => $id])->column());
            foreach (array_unique($ids) as $child) {
                $visit($child, $depth + 1);
            }
            unset($path[$id]);
        };
        foreach ($unlocks as $unlock) {
            $visit($unlock['entry_id'], 1);
        }
    }
}