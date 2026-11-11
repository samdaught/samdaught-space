<?php

declare(strict_types=1);

namespace app\services\rules;

use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use yii\db\Query;

class SpellcastingRules
{
    public function get(int $classId, RuleProfile $profile, array $selections, int $targetLevel=1, array $adjustments=[], array $excluded=[], ?string $selectedAbility=null): array
    {
        $book = (new Rulebook())->read($classId);
        $progress = (new Query())->from('{{%class_level_rule}}')->where(['class_entry_id' => $classId, 'level' => $targetLevel])->one();
        if (!$progress) {
            return ['configured' => false, 'enabled' => false, 'ability_code' => null, 'slot_count' => 0, 'groups' => [], 'issues' => ['Не заполнена таблица развития для этого уровня класса.']];
        }
        $slots = (new Query())->select(['spell_level', 'count'])->from('{{%class_level_slot}}')->where(['class_entry_id' => $classId, 'level' => $targetLevel])->andWhere(['>', 'count', 0])->orderBy('spell_level')->all();
        $maxLevel = $slots ? max(array_column($slots, 'spell_level')) : 0;
        $rule = $progress;
        $rule['enabled'] = $progress['kind'] !== 'none';
        $rule['slot_count'] = array_sum(array_column($slots, 'count'));
        foreach (['cantrip_count', 'known_count', 'prepared_count'] as $field) $rule[$field] = max(0, min(100, $rule[$field] + ($adjustments[$field] ?? 0)));
        $rule['ability_code'] = $selectedAbility ?? $book['casting_ability_code'];
        if ($rule['enabled'] && $rule['ability_code'] === null) return ['configured' => false, 'enabled' => true, 'ability_code' => null, 'slot_count' => $rule['slot_count'], 'groups' => [], 'issues' => ['Настройте магическую характеристику для класса или подкласса.']];
        $groups = [];
        if ($rule['enabled']) {
            $options = [];
            foreach ((new Query())->select('spell_entry_id')->from('{{%class_spell}}')->where(['class_entry_id' => $book['spell_list_class_ids']?:[$classId]])->distinct()->orderBy('spell_entry_id')->column() as $id) {
                $spell = (new CreationRules())->findAvailable($id, $profile);
                $level = (new Query())->select('level')->from('{{%spell_definition}}')->where(['catalog_entry_id' => $id])->scalar();
                if ($spell && $spell->type === 'spell' && $level !== false && $level <= $maxLevel && !in_array((int)$id,$excluded,true)) {
                    $options[$id] = ['id' => $id, 'level' => $level];
                }
            }
            foreach (['cantrip_count' => ['magic_cantrips', 'Заговоры класса'], 'known_count' => ['magic_known', 'Изученные заклинания'], 'prepared_count' => ['magic_prepared', 'Подготовленные заклинания']] as $field => [$code, $title]) {
                if ($rule[$field] === 0) {
                    continue;
                }
                $ids = [];
                foreach ($options as $option) {
                    if (($field === 'cantrip_count') !== ($option['level'] === 0)) {
                        continue;
                    }
                    if ($field === 'prepared_count' && $rule['known_count'] > 0 && !in_array($option['id'], $selections[$classId . ':magic_known'] ?? [], true)) {
                        continue;
                    }
                    $ids[] = $option['id'];
                }
                $groups[] = ['code' => $code, 'title' => $title, 'count' => $rule[$field], 'mode' => 'grant', 'entry_ids' => $ids];
            }
        }
        return ['configured' => true, 'enabled' => $rule['enabled'], 'ability_code' => $rule['ability_code'], 'slot_count' => $rule['slot_count'], 'slot_level' => ($progress['pact_level']??0)?:1, 'slots'=>$slots,'kind'=>$progress['kind']??'spellcasting','recovery'=>$progress['recovery']??'long_rest','change_on'=>$progress['change_on']??'level', 'groups' => $groups, 'issues' => []];
    }
}
