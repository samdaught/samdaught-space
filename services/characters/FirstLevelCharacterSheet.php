<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\catalog\{CatalogEntry, ClassDefinitionForm};
use app\models\characters\Character;
use app\models\rules\{ClassDefinition, ClassSavingThrowProficiency, RuleProfile, SkillDefinition, SpeciesDefinition};
use app\services\rules\{SkillOptions, CatalogAvailability};

class FirstLevelCharacterSheet
{
    public const SUPPORTED_LEVEL = 1;
    public const PROFICIENCY_BONUS = 2;

    public function get(Character $character, ?array $creation = null): array
    {
        $supported = $character->target_level === self::SUPPORTED_LEVEL;
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $species = $profile === null ? null : $this->findAvailableEntry($character->species_entry_id, 'species', $profile);
        $class = $profile === null ? null : $this->findAvailableEntry($character->class_entry_id, 'class', $profile);
        $speciesDefinition = $species === null ? null : SpeciesDefinition::findOne($species->id);
        $classDefinition = $class === null ? null : ClassDefinition::findOne($class->id);
        $saveCodes = $classDefinition === null ? [] : ClassSavingThrowProficiency::find()->select('ability_code')
            ->where(['class_entry_id' => $class->id])->orderBy('ability_code')->column();
        $savesConfigured = count($saveCodes) === ClassDefinitionForm::REQUIRED_SAVING_THROW_COUNT;
        $scores = (new AbilityScoreCalculator())->calculate($character);
        $benefits = (new CharacterBackgroundBenefits())->get($character);
        $classSkills = (new CharacterClassSkills())->get($character);
        $creation ??= (new CreationBenefits())->get($character);
        $issues = $scores['calculation']['issues'];
        if (!$supported) $issues[] = 'Базовый лист сейчас рассчитывается только для первого уровня.';
        if ($species === null) $issues[] = 'Выберите опубликованный вид, доступный профилю персонажа.';
        elseif ($speciesDefinition === null) $issues[] = 'Для вида не настроена базовая скорость ходьбы.';
        if ($class === null) $issues[] = 'Выберите опубликованный класс, доступный профилю персонажа.';
        elseif ($classDefinition === null || !$savesConfigured) $issues[] = 'Для класса не настроены кость хитов и два владения спасбросками.';
        if (!$scores['calculation']['base_scores_complete'] || !$scores['calculation']['background_increases_complete']) {
            $issues[] = 'Характеристики или увеличения предыстории ещё не заполнены полностью; доступные значения предварительные.';
        }
        if (!$benefits['skills_complete']) $issues[] = 'Владения навыками предыстории не определены полностью.';
        $issues = array_merge($issues, $classSkills['issues']);
        $backgroundSkillIds = array_column($benefits['skills'], 'id');
        if (array_intersect($backgroundSkillIds, $classSkills['effective_skill_entry_ids'])) {
            $issues[] = 'Некоторые навыки получены и от класса, и от предыстории. Бонус мастерства учтён один раз; можно выбрать другой разрешённый навык класса.';
        }

        $modifiers = array_column($scores['abilities'], 'total_modifier', 'code');
        $abilityTitles = array_column($scores['abilities'], 'title', 'code');
        $abilityTotals = array_column($scores['abilities'], 'total_score', 'code');
        $proficiency = $supported ? self::PROFICIENCY_BONUS : null;
        $dexterity = $modifiers['dex'] ?? null;
        $constitution = $modifiers['con'] ?? null;
        $baseHitPoints = $supported && $classDefinition !== null && $constitution !== null ? $classDefinition->hit_die_sides + $constitution : null;
        $hitPoints = $baseHitPoints === null ? null : $baseHitPoints + $creation['hit_point_bonus'];
        $savingThrows = [];
        foreach ($scores['abilities'] as $ability) {
            $proficient = $classDefinition !== null && $savesConfigured ? in_array($ability['code'], $saveCodes, true) : null;
            $bonus = $supported && $ability['total_modifier'] !== null && $proficient !== null
                ? $ability['total_modifier'] + ($proficient ? self::PROFICIENCY_BONUS : 0) : null;
            $savingThrows[] = ['code' => $ability['code'], 'title' => $ability['title'], 'ability_modifier' => $ability['total_modifier'],
                'class_proficient' => $proficient, 'base_bonus' => $bonus];
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
                $skills[] = ['id' => $entry->id, 'title' => $entry->title, 'ability_code' => $definition->ability_code,
                    'ability_title' => $abilityTitles[$definition->ability_code] ?? $definition->ability_code,
                    'background_proficient' => $backgroundProficient, 'class_proficient' => $classProficient,
                    'additional_proficient' => $additionalProficient, 'expertise' => $expertise,
                    'base_bonus' => $supported && $modifier !== null ? $modifier + ($proficient ? self::PROFICIENCY_BONUS * ($expertise ? 2 : 1) : 0) : null];
            }
        }
        return [
            'rule_profile_id' => $character->rule_profile_id,
            'species_entry_id' => $character->species_entry_id,
            'class_entry_id' => $character->class_entry_id,
            'background_entry_id' => $character->background_entry_id,
            'target_level' => $character->target_level,
            'class_skill_revision' => $classSkills['revision'],
            'class_skill_effective_entry_ids' => $classSkills['effective_skill_entry_ids'],
            'class_skills_complete' => $classSkills['complete'],
            'supported_level' => self::SUPPORTED_LEVEL,
            'calculation_supported' => $supported,
            'baseline_complete' => $supported && $speciesDefinition !== null && $classDefinition !== null && $savesConfigured
                && $scores['calculation']['base_scores_complete'] && $scores['calculation']['background_increases_complete'] && $benefits['skills_complete'] && $classSkills['complete'],
            'ability_totals' => $abilityTotals,
            'proficiency_bonus' => $proficiency,
            'base_hit_point_max' => $baseHitPoints,
            'hit_point_bonus' => $creation['hit_point_bonus'],
            'hit_point_max' => $hitPoints !== null && $hitPoints > 0 ? $hitPoints : null,
            'hit_die_sides' => $classDefinition === null ? null : $classDefinition->hit_die_sides,
            'base_speed_feet' => $supported && $speciesDefinition !== null ? $speciesDefinition->speed_feet : null,
            'base_initiative' => $supported ? $dexterity : null,
            'unarmored_base_ac' => $supported && $dexterity !== null ? 10 + $dexterity : null,
            'saving_throws' => $savingThrows,
            'skills' => $skills,
            'issues' => array_values(array_unique($issues)),
            'limitations' => [
                'Это базовые значения до эффектов особенностей вида, класса, черт и экипировки.',
                'В навыках учтены настроенные архивные владения и экспертиза. Условные эффекты особенностей читаются по описанию.',
                'Максимум хитов первого уровня: кость хитов класса + модификатор Телосложения + настроенные архивные бонусы. Текущие хиты и расходование ресурсов сайт не ведёт.',
            ],
        ];
    }

    private function findAvailableEntry(?int $id, string $type, RuleProfile $profile): ?CatalogEntry
    {
        if ($id === null) return null;
        $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id AND [[type]] = :type FOR SHARE',
            [':id' => $id, ':type' => $type])->queryScalar();
        $entry = $found === false ? null : CatalogEntry::findOne($found);
        return $entry !== null && $entry->is_published && (new CatalogAvailability())->isAvailableInProfile($entry, $profile) ? $entry : null;
    }
}
