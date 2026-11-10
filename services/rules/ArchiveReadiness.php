<?php

declare (strict_types=1);
namespace app\services\rules;

use app\models\catalog\CatalogEntry;
use yii\db\Query;
final class ArchiveReadiness
{
    public function inspect(CatalogEntry $entry): array
    {
        $issues = [];
        $service = new Rulebook();
        $data = $service->read($entry->id);
        try {
            $service->validate($entry, $data);
        } catch (\InvalidArgumentException $error) {
            $issues[] = $error->getMessage();
        }
        $definition = ['class' => 'class_definition', 'species' => 'species_definition', 'subclass' => 'subclass_definition', 'species_variant' => 'species_variant_definition', 'spell' => 'spell_definition', 'skill' => 'skill_definition', 'feat' => 'feat_definition', 'item' => 'item_definition'][$entry->type] ?? null;
        if ($definition && !(new Query())->from('{{%' . $definition . '}}')->where(['catalog_entry_id' => $entry->id])->exists()) {
            $issues[] = 'Не заполнено определение категории.';
        }
        if (!$data['ready']) {
            $issues[] = 'Запись не отмечена как механически заполненная.';
        }
        if ($entry->type === 'class' && count($data['progression']) !== 20) {
            $issues[] = 'Не заполнена прогрессия всех 20 уровней.';
        }
        if (in_array($entry->type, ['class', 'species'], true) && !(new CreationRules())->read($entry->id)) {
            $issues[] = 'Не настроены выдачи и группы выбора.';
        }
        if ($entry->type === 'item') {
            $kind = (new Query())->select('kind_code')->from('{{%item_definition}}')->where(['catalog_entry_id' => $entry->id])->scalar();
            if ($kind === 'weapon' && !($data['item']['weapon'] ?? null)) {
                $issues[] = 'Не заполнены урон, свойства и мастерство оружия.';
            }
            if (in_array($kind, ['armor', 'shield'], true) && (!($data['item']['armor'] ?? null) || !(new Query())->from('{{%armor_definition}}')->where(['catalog_entry_id' => $entry->id])->exists())) {
                $issues[] = 'Не заполнены КД и ограничения доспеха.';
            }
        }
        if ($entry->type === 'background') {
            foreach (['background_ability_option' => 3, 'background_skill_proficiency' => 2, 'background_feat' => 1, 'background_tool_proficiency' => 1, 'background_starting_equipment' => 1] as $table => $count) {
                if ((new Query())->from('{{%' . $table . '}}')->where(['background_entry_id' => $entry->id])->count() != $count) {
                    $issues[] = 'Не заполнены обязательные свойства предыстории: ' . $table . '.';
                }
            }
        }
        if ($entry->type === 'class') {
            if ((new Query())->from('{{%class_saving_throw_proficiency}}')->where(['class_entry_id' => $entry->id])->count() != 2) {
                $issues[] = 'Не заполнены два спасброска класса.';
            }
            $count = (new Query())->select('skill_choice_count')->from('{{%class_definition}}')->where(['catalog_entry_id' => $entry->id])->scalar();
            if ($count === false || (new Query())->from('{{%class_skill_option}}')->where(['class_entry_id' => $entry->id])->count() < $count) {
                $issues[] = 'Недостаточно вариантов навыков класса.';
            }
        }
        $mechanics = (new EntryMechanics())->read($entry);
        if ($entry->type === 'spell' && !$mechanics['class_ids'] && !(new Query())->from('{{%entry_spell_grant}}')->where(['spell_entry_id' => $entry->id])->exists() && !(new Query())->from('{{%entry_creation_grant}}')->where(['entry_id' => $entry->id])->exists()) {
            $issues[] = 'Заклинание не связано ни со списком класса, ни со специальной выдачей.';
        }
        $ids = (new Query())->select('target_entry_id')->from('{{%entry_rule_reference}}')->where(['source_entry_id' => $entry->id])->column();
        $ids = array_merge($ids, array_filter([$data['parent_entry_id']]), $mechanics['spell_ids'], $mechanics['class_ids'], array_column($data['unlocks'], 'entry_id'), array_column($data['spell_grants'], 'spell_id'));
        $creation = (new CreationRules())->read($entry->id);
        if ($creation) {
            $ids = array_merge($ids, array_column($creation['grants'], 'entry_id'));
            foreach ($creation['choices'] as $group) {
                $ids = array_merge($ids, $group['entry_ids']);
            }
        }
        foreach (CatalogEntry::find()->where(['id' => array_unique($ids), 'is_published' => false])->all() as $target) {
            $issues[] = 'Связанная запись не опубликована: ' . $target->title . '.';
        }
        return ['id' => $entry->id, 'title' => $entry->title, 'type' => $entry->type, 'complete' => !$issues, 'issues' => array_values(array_unique($issues))];
    }
}
