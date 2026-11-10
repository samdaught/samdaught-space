<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\Character;
use app\models\rules\RuleProfile;
use app\services\rules\CreationRules;
use yii\db\Query;

final class WeaponSheet
{
    public function get(Character $character, array $creation, array $modifiers, ?int $proficiency): array
    {
        $result = [];
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $traits = $creation['traits'] ?? [];
        $training = array_column(array_filter($traits, static fn($t) => $t['kind'] === 'weapon'), 'value');
        $specific = array_map('intval', array_column(array_filter($traits, static fn($t) => $t['kind'] === 'proficiency'), 'value'));
        $masteries = array_map('intval', array_column(array_filter($traits, static fn($t) => $t['kind'] === 'weapon_mastery'), 'value'));
        foreach ((new Query())->select(['w.*', 'e.title'])->distinct()->from(['i' => '{{%character_item}}'])->innerJoin(['w' => '{{%weapon_definition}}'], '[[w.catalog_entry_id]]=[[i.item_entry_id]]')->innerJoin(['e' => '{{%catalog_entry}}'], '[[e.id]]=[[w.catalog_entry_id]]')->where(['i.character_id' => $character->id, 'i.is_equipped' => true])->all() as $weapon) {
            $id = $weapon['catalog_entry_id'];
            if (!(new CreationRules())->findAvailable($id, $profile)) {
                continue;
            }
            $properties = (new Query())->select('code')->from('{{%weapon_property}}')->where(['item_entry_id' => $id])->column();
            $category = $weapon['category'];
            $ability = str_ends_with($category, 'ranged') ? 'dex' : 'str';
            if (in_array('finesse', $properties, true) && ($modifiers['dex'] ?? -100) > ($modifiers['str'] ?? -100)) {
                $ability = 'dex';
            }
            $modifier = $modifiers[$ability] ?? null;
            $proficient = in_array($id, $specific, true) || in_array(str_starts_with($category, 'simple') ? 'simple' : 'martial', $training, true) || in_array($category, $training, true);
            $attack = $modifier === null || $proficiency === null ? null : $modifier + ($proficient ? $proficiency : 0) + ($creation['targeted_bonuses']['weapon_attack'][0] ?? 0) + ($creation['targeted_bonuses']['weapon_attack'][$id] ?? 0);
            $damage = $modifier === null ? null : $modifier + ($creation['targeted_bonuses']['weapon_damage'][0] ?? 0) + ($creation['targeted_bonuses']['weapon_damage'][$id] ?? 0);
            $result[] = ['id' => $id, 'title' => $weapon['title'], 'ability_code' => $ability, 'attack_bonus' => $attack, 'damage' => $weapon['damage_count'] . 'd' . $weapon['damage_die'] . ($damage === null ? '' : ($damage >= 0 ? '+' : '') . $damage), 'damage_type' => $weapon['damage_type'], 'properties' => $properties, 'range_normal' => $weapon['range_normal'], 'range_long' => $weapon['range_long'], 'versatile_die' => $weapon['versatile_die'], 'mastery' => $weapon['mastery'], 'mastery_available' => $proficient && in_array($id, $masteries, true), 'proficient' => $proficient];
        }
        return $result;
    }
}
