<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\catalog\{BackgroundSkillsForm, CatalogEntry};
use app\models\characters\{Character, CharacterBackgroundChoice};
use app\models\rules\{BackgroundFeat, BackgroundSkillProficiency, BackgroundToolProficiency, FeatCategory, FeatDefinition, RuleProfile, SkillDefinition, ToolDefinition, ToolGroup};
use app\services\rules\{BackgroundStartingEquipmentReader, BackgroundToolOptions, CatalogAvailability};

class CharacterBackgroundBenefits
{

    public function get(Character $character): array
    {
        $saved = CharacterBackgroundChoice::findOne($character->id);
        $contextMatches = $saved !== null && $saved->background_entry_id === $character->background_entry_id
            && $saved->rule_profile_id === $character->rule_profile_id;
        $needsReselection = $saved !== null && !$contextMatches;
        $issues = $needsReselection ? ['Предыстория или профиль изменились. Сохраните личные выборы для текущей предыстории.'] : [];
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $background = $profile === null || $character->background_entry_id === null ? null
            : $this->findAvailableEntry($character->background_entry_id, 'background', $profile);
        $valid = $background !== null;
        if (!$valid) {
            $issues[] = 'Выберите опубликованную предысторию, доступную профилю персонажа.';
        }

        $skills = [];
        $skillIds = !$valid ? [] : BackgroundSkillProficiency::find()->select('skill_entry_id')
            ->where(['background_entry_id' => $background->id])->orderBy('skill_entry_id')->column();
        if (count($skillIds) !== BackgroundSkillsForm::REQUIRED_COUNT) {
            $skillIds = [];
        }
        foreach ($skillIds as $id) {
            $entry = $this->findAvailableEntry($id, 'skill', $profile);
            $definition = $entry === null ? null : SkillDefinition::findOne($entry->id);
            if ($definition !== null) {
                $skills[] = $this->entryData($entry) + ['ability_code' => $definition->ability_code];
            }
        }
        $skillsComplete = $valid && count($skillIds) === BackgroundSkillsForm::REQUIRED_COUNT
            && count($skills) === BackgroundSkillsForm::REQUIRED_COUNT;
        if ($valid && !$skillsComplete) {
            $issues[] = 'Два владения навыками предыстории не настроены или недоступны в этом профиле.';
        }

        $feat = null;
        $featRule = !$valid ? null : BackgroundFeat::findOne($background->id);
        if ($featRule !== null) {
            $entry = $this->findAvailableEntry($featRule->feat_entry_id, 'feat', $profile);
            $definition = $entry === null ? null : FeatDefinition::findOne($entry->id);
            if ($definition !== null && $definition->category_code === FeatCategory::ORIGIN) {
                $feat = $this->entryData($entry);
            }
        }
        if ($valid && $feat === null) {
            $issues[] = 'Черта происхождения не настроена или недоступна в этом профиле.';
        }

        $toolRule = !$valid ? null : BackgroundToolProficiency::findOne($background->id);
        $requiresChoice = $toolRule !== null && $toolRule->tool_group_code !== null;
        $group = !$requiresChoice ? null : ToolGroup::findOne($toolRule->tool_group_code);
        $toolConfigured = $toolRule !== null && ($toolRule->tool_entry_id !== null || $group !== null);
        $toolOptions = [];
        if ($requiresChoice && $group !== null) {
            $groupIds = ToolDefinition::find()->select('catalog_entry_id')->where(['group_code' => $group->code])->column();
            foreach ((new BackgroundToolOptions())->get($profile) as $option) {
                if (in_array($option['id'], $groupIds, true)) {
                    $toolOptions[] = $option;
                }
            }
        }
        $selectedToolId = $requiresChoice && $contextMatches ? $saved->tool_entry_id : null;
        $effectiveTool = null;
        $toolId = $requiresChoice ? $selectedToolId : ($toolRule === null ? null : $toolRule->tool_entry_id);
        if ($toolId !== null && (!$requiresChoice || in_array($toolId, array_column($toolOptions, 'id'), true))) {
            $entry = (new BackgroundToolOptions())->lockAndFind($toolId);
            if ($entry !== null && (new CatalogAvailability())->isAvailableInProfile($entry, $profile)) {
                $effectiveTool = $this->entryData($entry);
            }
        }
        if ($valid && $effectiveTool === null) {
            $issues[] = $requiresChoice ? 'Выберите доступный инструмент из разрешённой группы.'
                : 'Фиксированный инструмент предыстории не настроен или недоступен.';
        }
        if ($selectedToolId !== null && $effectiveTool === null) {
            $needsReselection = true;
        }

        $equipmentChoice = $contextMatches ? $saved->equipment_choice : null;
        $equipment = $profile === null ? $this->emptyEquipment() : (new CharacterStartingEquipmentPreview())->get(
            (new BackgroundStartingEquipmentReader())->get($background), $effectiveTool, $equipmentChoice, $toolConfigured, $profile
        );
        $issues = array_merge($issues, $equipment['issues']);

        return [
            'background_entry_id' => $character->background_entry_id,
            'rule_profile_id' => $character->rule_profile_id,
            'revision' => $saved === null ? 0 : $saved->revision,
            'selection_valid' => $valid,
            'needs_reselection' => $needsReselection,
            'skills' => $skills,
            'skills_complete' => $skillsComplete,
            'feat' => $feat,
            'feat_complete' => $feat !== null,
            'tool' => [
                'configured' => $toolConfigured,
                'requires_choice' => $requiresChoice,
                'group_title' => $group === null ? null : $group->title,
                'options' => $toolOptions,
                'selected_tool_entry_id' => $selectedToolId,
                'effective_tool' => $effectiveTool,
                'complete' => $effectiveTool !== null,
            ],
            'equipment' => $equipment,
            'complete' => $valid && $skillsComplete && $feat !== null && $effectiveTool !== null && $equipment['complete'],
            'issues' => array_values(array_unique($issues)),
        ];
    }

    private function findAvailableEntry(int $id, string $type, RuleProfile $profile): ?CatalogEntry
    {
        $found = Yii::$app->db->createCommand(
            'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = CAST(:id AS BIGINT) AND [[type]] = :type FOR SHARE',
            [':id' => $id, ':type' => $type]
        )->queryScalar();
        $entry = $found === false ? null : CatalogEntry::findOne($found);
        return $entry !== null && $entry->is_published && (new CatalogAvailability())->isAvailableInProfile($entry, $profile) ? $entry : null;
    }

    private function entryData(CatalogEntry $entry): array
    {
        return $entry->getAttributes(['id', 'title', 'body']);
    }

    private function emptyEquipment(): array
    {
        return ['configured' => false, 'source_text' => null, 'choice' => null, 'package_available' => false, 'money_available' => false,
            'package' => null, 'alternative_money_cp' => null, 'preview' => null, 'complete' => false, 'issues' => []];
    }
}
