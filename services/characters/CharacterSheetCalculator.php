<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\catalog\{CatalogEntry, ClassDefinitionForm};
use app\models\characters\Character;
use app\models\rules\{ClassDefinition, ClassSavingThrowProficiency, RuleProfile, SkillDefinition, SpeciesDefinition};
use app\services\rules\{SkillOptions, CatalogAvailability};
use yii\db\Query;

class CharacterSheetCalculator
{
    public function get(Character $character, ?array $creation = null): array
    {
        $targetLevel = max(1, (int) $character->target_level);
        $proficiency = 2 + intdiv($targetLevel - 1, 4);
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $species = $profile === null ? null : $this->findAvailableEntry($character->species_entry_id, 'species', $profile);
        $class = $profile === null ? null : $this->findAvailableEntry($character->class_entry_id, 'class', $profile);
        $speciesDefinition = $species === null ? null : SpeciesDefinition::findOne($species->id);
        $classDefinition = $class === null ? null : ClassDefinition::findOne($class->id);
        $saveCodes = $classDefinition === null
            ? []
            : ClassSavingThrowProficiency::find()
                ->select('ability_code')
                ->where(['class_entry_id' => $class->id])
                ->orderBy('ability_code')
                ->column();
        $savesConfigured = count($saveCodes) === ClassDefinitionForm::REQUIRED_SAVING_THROW_COUNT;
        $creation ??= (new CreationBenefits())->get($character);
        $scores = (new AbilityScoreCalculator())->calculate($character, $creation);
        $bonuses = $creation['bonuses'] ?? [];
        $benefits = (new CharacterBackgroundBenefits())->get($character);
        $classSkills = (new CharacterClassSkills())->get($character);
        $issues = $scores['calculation']['issues'];

        if ($species === null) {
            $issues[] = 'Выберите опубликованный вид, доступный в мире персонажа.';
        } elseif ($speciesDefinition === null) {
            $issues[] = 'Для вида не настроена базовая скорость ходьбы.';
        }

        if ($class === null) {
            $issues[] = 'Выберите опубликованный класс, доступный миру персонажа.';
        } elseif ($classDefinition === null || !$savesConfigured) {
            $issues[] = 'Для класса не настроены кость хитов и два владения спасбросками.';
        }

        if (
            !$scores['calculation']['base_scores_complete']
            || !$scores['calculation']['background_increases_complete']
        ) {
            $issues[] = 'Характеристики или увеличения предыстории ещё не заполнены полностью; доступные значения предварительные.';
        }
        if (!$benefits['skills_complete']) {
            $issues[] = 'Владения навыками предыстории не определены полностью.';
        }
        $issues = array_merge($issues, $classSkills['issues']);
        $backgroundSkillIds = array_column($benefits['skills'], 'id');
        if (array_intersect($backgroundSkillIds, $classSkills['effective_skill_entry_ids'])) {
            $issues[] = 'Некоторые навыки получены и от класса, и от предыстории. Бонус мастерства учтён один раз; можно выбрать другой разрешённый навык класса.';
        }

        $modifiers = array_column($scores['abilities'], 'total_modifier', 'code');
        $abilityTitles = array_column($scores['abilities'], 'title', 'code');
        $abilityTotals = array_column($scores['abilities'], 'total_score', 'code');
        $dexterity = $modifiers['dex'] ?? null;
        $constitution = $modifiers['con'] ?? null;
        $baseHitPoints = null;
        if ($classDefinition !== null && $constitution !== null) {
            $firstLevelHitPoints = max(1, $classDefinition->hit_die_sides + $constitution);
            $averageLaterLevelHitPoints = max(1, intdiv($classDefinition->hit_die_sides, 2) + 1 + $constitution);
            $baseHitPoints = $firstLevelHitPoints + (($targetLevel - 1) * $averageLaterLevelHitPoints);
        }
        $hitPoints = $baseHitPoints === null ? null : $baseHitPoints + $creation['hit_point_bonus'];
        $savingThrows = [];
        foreach ($scores['abilities'] as $ability) {
            $proficient = $classDefinition !== null && $savesConfigured
                ? in_array($ability['code'], $saveCodes, true)
                : null;
            $bonus = $ability['total_modifier'] !== null && $proficient !== null
                ? $ability['total_modifier']
                    + ($proficient ? $proficiency : 0)
                    + ($bonuses['saving_throw_' . $ability['code']] ?? 0)
                    + ($creation['targeted_bonuses']['saving_throw'][0] ?? 0)
                : null;

            $savingThrows[] = [
                'code' => $ability['code'],
                'title' => $ability['title'],
                'ability_modifier' => $ability['total_modifier'],
                'class_proficient' => $proficient,
                'base_bonus' => $bonus,
            ];
        }
        $skills = [];
        if ($profile !== null) {
            foreach ((new SkillOptions())->get($profile) as $option) {
                $entry = $this->findAvailableEntry($option['id'], 'skill', $profile);
                $definition = $entry === null ? null : SkillDefinition::findOne($entry->id);
                if ($definition === null) continue;
                $modifier = $modifiers[$definition->ability_code] ?? null;
                $backgroundProficient = in_array($entry->id, $backgroundSkillIds, true);
                $classProficient = in_array($entry->id, $classSkills['effective_skill_entry_ids'], true);
                $additionalProficient = in_array($entry->id, $creation['skill_entry_ids'], true);
                $expertise = in_array($entry->id, $creation['expertise_skill_entry_ids'], true);
                $proficient = $backgroundProficient || $classProficient || $additionalProficient;
                $skills[] = [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'ability_code' => $definition->ability_code,
                    'ability_title' => $abilityTitles[$definition->ability_code] ?? $definition->ability_code,
                    'background_proficient' => $backgroundProficient,
                    'class_proficient' => $classProficient,
                    'additional_proficient' => $additionalProficient,
                    'expertise' => $expertise,
                    'base_bonus' => $modifier !== null
                        ? $modifier
                            + ($proficient ? $proficiency * ($expertise ? 2 : 1) : 0)
                            + ($creation['targeted_bonuses']['skill'][$entry->id] ?? 0)
                        : null,
                ];
            }
        }
        $details = (new Query())
            ->select('data')
            ->from('{{%character_sheet_details}}')
            ->where(['character_id' => $character->id])
            ->scalar();
        $details = is_string($details) ? json_decode($details, true) : ($details ?: []);
        $armor = $dexterity === null ? null : 10 + $dexterity;
        $shield = 0;
        $shieldCount = 0;
        $equippedArmor = [];
        $speedPenalty = 0;
        $stealthDisadvantage = false;
        $armorUntrained = false;
        $training = array_column(
            array_filter($creation['traits'] ?? [], static fn($trait): bool => $trait['kind'] === 'armor'),
            'value',
        );
        $equippedItems = (new Query())
            ->select([
                'catalog_entry_id' => 'i.item_entry_id',
                'a.base_ac',
                'a.dexterity_cap',
                'a.is_shield',
                'a.category',
                'a.strength_min',
                'a.stealth_disadvantage',
                'e.title',
            ])
            ->from(['i' => '{{%character_item}}'])
            ->innerJoin(['e' => '{{%catalog_entry}}'], '[[e.id]] = [[i.item_entry_id]]')
            ->leftJoin(['a' => '{{%armor_definition}}'], '[[a.catalog_entry_id]] = [[i.item_entry_id]]')
            ->where([
                'i.character_id' => $character->id,
                'i.is_equipped' => true,
                'e.is_published' => true,
            ])
            ->all();
        foreach ($equippedItems as $item) {
            $available = $this->findAvailableEntry($item['catalog_entry_id'], 'item', $profile);
            if ($available === null) {
                continue;
            }

            if ($item['base_ac'] !== null) {
                $category = $item['is_shield'] ? 'shield' : $item['category'];
                if (!in_array($category, $training, true)) {
                    $armorUntrained = true;
                }
                if (($abilityTotals['str'] ?? 0) < $item['strength_min']) {
                    $speedPenalty = 10;
                }
                $stealthDisadvantage = $stealthDisadvantage || $item['stealth_disadvantage'];
            }

            if ($item['is_shield']) {
                $shield = max($shield, $item['base_ac']);
                ++$shieldCount;
            } elseif ($item['base_ac'] !== null) {
                $equippedArmor[] = $item;
            }

            $itemBonuses = (new Query())
                ->from('{{%entry_bonus}}')
                ->where(['source_entry_id' => $item['catalog_entry_id']])
                ->all();
            foreach ($itemBonuses as $bonus) {
                if (in_array($bonus['code'], ['armor_class', 'speed', 'initiative', 'spell_attack', 'spell_save'], true)) {
                    $bonuses[$bonus['code']] = ($bonuses[$bonus['code']] ?? 0) + $bonus['amount'];
                }
            }
        }

        if ($shieldCount > 1) {
            $issues[] = 'Экипировано несколько щитов. Оставьте один.';
        }
        if (count($equippedArmor) > 1) {
            $issues[] = 'Экипировано несколько доспехов. Оставьте один.';
        }
        if ($equippedArmor && $dexterity !== null) {
            $item = $equippedArmor[0];
            $armor = $item['base_ac'] + $this->armorDexterityBonus($item['dexterity_cap'], $dexterity);
        }
        if ($armor !== null) {
            $armor += $shield;
        }
        if (!$equippedArmor && $dexterity !== null) {
            foreach ($creation['unarmored_defenses'] ?? [] as $defense) {
                if ($shield > 0 && !$defense['allows_shield']) {
                    continue;
                }
                $additional = $defense['ability_code'] === null
                    ? 0
                    : ($modifiers[$defense['ability_code']] ?? null);
                if ($additional !== null) {
                    $armor = max($armor, $defense['base_ac'] + $dexterity + $additional + $shield);
                }
            }
        }
        if ($armor !== null) {
            $armor += $bonuses['armor_class'] ?? 0;
        }
        $casting = $creation['spellcasting'] ?? [];
        $spellSources = [];
        foreach ($creation['spell_sources'] ?? [] as $source) {
            $modifier = isset($source['ability_code'])
                ? ($modifiers[$source['ability_code']] ?? null)
                : null;
            $spellSources[] = $source + [
                'attack_bonus' => $modifier === null
                    ? null
                    : $proficiency + $modifier + ($bonuses['spell_attack'] ?? 0),
                'save_dc' => $modifier === null
                    ? null
                    : 8 + $proficiency + $modifier + ($bonuses['spell_save'] ?? 0),
            ];
        }
        if (!$casting['enabled'] && count($spellSources) === 1) {
            $casting['ability_code'] = $spellSources[0]['ability_code'];
        }

        $magicModifier = isset($casting['ability_code'])
            ? ($modifiers[$casting['ability_code']] ?? null)
            : null;
        $displayTraits = $creation['traits'] ?? [];
        foreach ($displayTraits as &$trait) {
            $kind = ['resistance' => 'damage'][$trait['kind']] ?? $trait['kind'];
            $title = (new Query())
                ->select('title')
                ->from('{{%rule_vocabulary}}')
                ->where(['kind' => $kind, 'code' => $trait['value']])
                ->scalar();
            if ($title !== false) {
                $trait['value'] = $title;
            }
        }
        unset($trait);

        $languages = array_values(array_unique(array_column(
            array_filter($displayTraits, static fn($trait): bool => $trait['kind'] === 'language'),
            'value',
        )));
        $sizes = array_values(array_unique(array_column(
            array_filter($displayTraits, static fn($trait): bool => $trait['kind'] === 'size'),
            'value',
        )));
        return [
            'weapons'=>(new WeaponSheet())->get($character,$creation,$modifiers,$proficiency),
            'resources'=>$creation['resources']??[],
            'armor_untrained'=>$armorUntrained, 'stealth_disadvantage'=>$stealthDisadvantage, 'armor_speed_penalty'=>$speedPenalty,
            'spellcasting_sources'=>$spellSources,
            'armor_class'=>$details['armor_class']??$armor,
            'speed_feet'=>$details['speed_feet']??($speciesDefinition===null?null:max(0,$speciesDefinition->speed_feet+($bonuses['speed']??0)-$speedPenalty)),
            'initiative'=>$details['initiative']??($dexterity===null?null:$dexterity+($bonuses['initiative']??0)),
            'spell_attack_bonus'=>$details['spell_attack_bonus']??($magicModifier===null?null:$proficiency+$magicModifier+($bonuses['spell_attack']??0)),
            'spell_save_dc'=>$details['spell_save_dc']??($magicModifier===null?null:8+$proficiency+$magicModifier+($bonuses['spell_save']??0)),
            'languages'=>implode(', ',array_filter([implode(', ',$languages),$details['languages']??''])),
            'size'=>($creation['selected_size']??null)??(($details['size']??'')!==''?$details['size']:(count($sizes)===1?$sizes[0]:'')),
            'traits'=>$displayTraits,
            'equipment_valid'=>count($equippedArmor)<=1 && $shieldCount<=1,
            'rule_profile_id' => $character->rule_profile_id,
            'species_entry_id' => $character->species_entry_id,
            'class_entry_id' => $character->class_entry_id,
            'background_entry_id' => $character->background_entry_id,
            'target_level' => $character->target_level,
            'class_skill_revision' => $classSkills['revision'],
            'class_skill_effective_entry_ids' => $classSkills['effective_skill_entry_ids'],
            'class_skills_complete' => $classSkills['complete'],
            'baseline_complete' => $speciesDefinition !== null && $classDefinition !== null && $savesConfigured
                && $scores['calculation']['base_scores_complete'] && $scores['calculation']['background_increases_complete'] && $benefits['skills_complete'] && $classSkills['complete'],
            'ability_totals' => $abilityTotals,
            'proficiency_bonus' => $proficiency,
            'base_hit_point_max' => $baseHitPoints,
            'hit_point_bonus' => $creation['hit_point_bonus'],
            'hit_point_max' => $hitPoints !== null && $hitPoints > 0 ? $hitPoints : null,
            'hit_die_sides' => $classDefinition === null ? null : $classDefinition->hit_die_sides,
            'base_speed_feet' => $speciesDefinition === null ? null : $speciesDefinition->speed_feet,
            'base_initiative' => $dexterity,
            'unarmored_base_ac' => $dexterity === null ? null : 10 + $dexterity,
            'saving_throws' => $savingThrows,
            'skills' => $skills,
            'issues' => array_values(array_unique($issues)),
            'limitations' => [
                'Учтены настроенные эффекты характеристик, владений и экипировки. Ситуативные условия вне листа требуют решения игрока.',
                'Без подготовки доспехи запрещают заклинательство и дают помеху проверкам Силы/Ловкости, спасброскам и атакам; соответствующий признак передаётся в лист.',
                'Максимум хитов рассчитан для ' . $targetLevel . '-го уровня: полный размер кости хитов на 1-м уровне, затем среднее значение кости + 1 за каждый следующий уровень, с модификатором Телосложения и настроенными архивными бонусами. Текущие хиты и расходование ресурсов сайт не ведёт.',
            ],
        ];
    }

    private function armorDexterityBonus($cap, int $dexterity): int
    {
        if ($cap === 0) {
            return 0;
        }

        return $cap === null ? $dexterity : min($dexterity, $cap);
    }

    private function findAvailableEntry(?int $id, string $type, ?RuleProfile $profile): ?CatalogEntry
    {
        if ($id === null || $profile === null) {
            return null;
        }

        $found = Yii::$app->db
            ->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id AND [[type]] = :type FOR SHARE',
                [':id' => $id, ':type' => $type],
            )
            ->queryScalar();
        $entry = $found === false ? null : CatalogEntry::findOne($found);

        return $entry !== null
            && $entry->is_published
            && (new CatalogAvailability())->isAvailableInProfile($entry, $profile)
            ? $entry
            : null;
    }
}
