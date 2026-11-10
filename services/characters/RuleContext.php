<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\{Character, CharacterAbilityScore};
use yii\db\Query;

final class RuleContext
{
    public static function build(Character $character, ?array $scores = null, ?array $increases = null): array
    {
        $scores ??= CharacterAbilityScore::find()->select('base_score')->where(['character_id' => $character->id])->indexBy('ability_code')->column();
        $increases ??= array_column((new BackgroundAbilityDistribution())->get($character)['abilities'], 'increase', 'code');
        $abilities = [];
        foreach (['str', 'dex', 'con', 'int', 'wis', 'cha'] as $code) {
            $abilities[$code] = is_int($scores[$code] ?? null) ? $scores[$code] + ($increases[$code] ?? 0) : 0;
        }
        $armor = (new Query())->select(new \yii\db\Expression("CASE WHEN [[a.is_shield]] THEN 'shield' ELSE [[a.category]] END"))->from(['i' => '{{%character_item}}'])->innerJoin(['a' => '{{%armor_definition}}'], '[[a.catalog_entry_id]]=[[i.item_entry_id]]')->where(['i.character_id' => $character->id, 'i.is_equipped' => true])->column();
        return ['level' => (int) $character->target_level, 'class_id' => $character->class_entry_id, 'class_levels' => $character->class_entry_id === null ? [] : [$character->class_entry_id => (int) $character->target_level], 'abilities' => $abilities, 'base_abilities' => $abilities, 'proficiency_bonus' => 2 + (int) floor(((int) $character->target_level - 1) / 4), 'entry_ids' => array_values(array_filter([$character->species_entry_id, $character->class_entry_id, $character->background_entry_id, $character->subclass_entry_id, $character->species_variant_entry_id])), 'proficiency_ids' => [], 'proficiencies' => [], 'armor' => $armor, 'choices' => [], 'spellcasting' => false];
    }
}
