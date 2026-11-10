<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\characters\{Character, AbilityScoresForm, BackgroundAbilityIncreasesForm, BackgroundChoicesForm,
    ClassSkillChoicesForm, CharacterInventoryForm, CharacterItem, CharacterBackgroundChoice, CharacterClassSkillChoice,
    CharacterClassSkill, CharacterInventoryState, CharacterAbilityIncreaseSource, CharacterAbilityIncrease};
use app\models\rules\RuleProfile;
use app\services\rules\{CatalogAvailability, BackgroundToolOptions};
use yii\web\{BadRequestHttpException, ConflictHttpException, ForbiddenHttpException, NotFoundHttpException};
use app\models\characters\SheetDetailsForm;
use yii\db\{Query, JsonExpression};

class CharacterEditor
{
    public const CONTEXT_FIELDS = ['rule_profile_id', 'target_level', 'species_entry_id', 'class_entry_id', 'background_entry_id', 'subclass_entry_id', 'species_variant_entry_id'];
    private const INPUT_FIELDS = ['revision', 'name', 'rule_profile_id', 'target_level', 'species_entry_id', 'class_entry_id',
        'background_entry_id', 'subclass_entry_id', 'species_variant_entry_id', 'rule_choices', 'base_scores', 'background_increases', 'background_choices', 'class_skill_entry_ids', 'inventory_items', 'creation_choices', 'equipment_choices', 'starting_equipped_ids', 'sheet_details', 'finish'];

    public function read(int $id, int $userId): array
    {
        return Yii::$app->db->transaction(function () use ($id, $userId): array {
            $character = $this->findOwned($id, $userId, false);
            if ($character->status === Character::STATUS_READY) {
                $snapshot = $this->jsonData('character_sheet_snapshot', $id);
                if ($snapshot === null) throw new \RuntimeException('Не найден сохранённый завершённый лист.');
                return $snapshot;
            }
            $this->lockEntries($character);
            return $this->snapshot($character);
        });
    }

    public function options(array $context, int $userId, $choices = [], $classSkillIds = [], $ruleChoices = [], ?array $scores = null, ?array $increases = null): array
    {
        return Yii::$app->db->transaction(function () use ($context, $userId, $choices, $classSkillIds, $ruleChoices, $scores, $increases): array {
            $character = new Character();
            $character->rule_choices = $ruleChoices;
            $character->ruleContextScores = $scores;
            $character->ruleContextIncreases = $increases;
            $character->user_id = $userId;
            $character->name = 'Новый персонаж';
            $character->status = Character::STATUS_DRAFT;
            $character->ability_score_method = Character::ABILITY_METHOD_STANDARD_ARRAY;
            foreach (self::CONTEXT_FIELDS as $field) $character->$field = $context[$field] ?? ($field === 'target_level' ? 1 : null);
            $this->validateContextTypes($character, true);
            $this->lockEntries($character);

            $attributes = ['target_level'];
            if ($character->rule_profile_id !== null) $attributes[] = 'rule_profile_id';
            elseif ($character->species_entry_id !== null || $character->class_entry_id !== null || $character->background_entry_id !== null) {
                throw new CharacterEditorValidationException(['rule_profile_id' => ['Сначала выберите мир и правила.']]);
            }
            $attributes=array_merge($attributes,['subclass_entry_id','species_variant_entry_id']);
            if (!$character->validate($attributes)) throw new CharacterEditorValidationException($character->getErrors());
            return $this->sections($character, $choices, $classSkillIds);
        });
    }

    public function save(array $data, int $userId, ?int $id): array
    {
        $data['rule_choices'] ??= [];
        $data['subclass_entry_id'] ??= null;
        $data['species_variant_entry_id'] ??= null;
        $data['equipment_choices'] ??= [];
        $data['starting_equipped_ids'] ??= [];
        if (count($data) !== count(self::INPUT_FIELDS) || array_diff(self::INPUT_FIELDS, array_keys($data))) {
            throw new BadRequestHttpException('Передайте все поля единого редактора без дополнительных полей.');
        }
        if (!is_int($data['revision']) || $data['revision'] < 0 || $data['revision'] >= 2147483647) {
            throw new BadRequestHttpException('revision должна быть целым числом от 0 до 2147483646.');
        }
        if ($id === null && $data['target_level'] !== 1) {
            throw new CharacterEditorValidationException(['target_level' => ['В первой версии новый персонаж создаётся только на первом уровне.']]);
        }
        if (!is_bool($data['finish'])) throw new BadRequestHttpException('finish должен быть логическим значением.');
        return Yii::$app->db->transaction(function () use ($data, $userId, $id): array {
            $character = $id === null ? new Character() : $this->findOwned($id, $userId, true);
            if ($id !== null && $character->status !== Character::STATUS_DRAFT) throw new ForbiddenHttpException('Изменять можно только черновик персонажа.');
            if ($data['revision'] !== ($id === null ? 0 : $character->revision)) throw new ConflictHttpException('Черновик уже изменён. Загрузите актуальные данные перед сохранением.');
            foreach (array_merge(['name'], self::CONTEXT_FIELDS) as $field) $character->$field = $data[$field];
            $character->user_id = $userId;
            $character->status = Character::STATUS_DRAFT;
            $character->ability_score_method = Character::ABILITY_METHOD_STANDARD_ARRAY;
            $this->validateContextTypes($character, false);
            $this->lockEntries($character);
            if (!$character->validate()) throw new CharacterEditorValidationException($character->getErrors());

            $character->rule_choices = $data['rule_choices'];
            $context = clone $character;
            $context->id = null;
            $rules = $this->sections($context);
            $errors = [];
            $scores = new AbilityScoresForm(); $scores->base_scores = $data['base_scores'];
            $this->collectErrors($scores, $errors);
            $increases = new BackgroundAbilityIncreasesForm();
            $increases->increases = $data['background_increases'];
            $increases->allowedAbilityCodes = array_column($rules['background_abilities']['abilities'], 'code');
            if ($rules['background_abilities']['configured']) $this->collectErrors($increases, $errors);
            elseif ($data['background_increases'] !== []) $errors['increases'] = ['Для невыбранной или ненастроенной предыстории распределение должно быть пустым.'];

            $choicesData = $data['background_choices'];
            if (!is_array($choicesData) || count($choicesData) !== 2 || array_diff(['tool_entry_id', 'equipment_choice'], array_keys($choicesData))) {
                $errors['background_choices'] = ['Передайте tool_entry_id и equipment_choice; незаполненное значение — null.'];
                $choicesData = ['tool_entry_id' => null, 'equipment_choice' => null];
            }
            $choices = new BackgroundChoicesForm();
            $choices->tool_entry_id = $choicesData['tool_entry_id']; $choices->equipment_choice = $choicesData['equipment_choice'];
            $benefits = $rules['benefits'];
            $choices->requiresToolChoice = $benefits['tool']['requires_choice'];
            $choices->allowedToolIds = array_column($benefits['tool']['options'], 'id');
            $choices->packageAvailable = $benefits['equipment']['package_available'];
            $choices->moneyAvailable = $benefits['equipment']['money_available'];
            if (is_int($choices->tool_entry_id) && $choices->tool_entry_id > 0 && $choices->tool_entry_id <= 2147483647) {
                if ((new BackgroundToolOptions())->lockAndFind($choices->tool_entry_id) === null) $errors['tool_entry_id'] = ['Инструмент больше недоступен. Обновите варианты.'];
            }
            $this->collectErrors($choices, $errors);

            $skills = new ClassSkillChoicesForm(); $skills->skill_entry_ids = $data['class_skill_entry_ids'];
            $skills->allowedIds = array_column($rules['class_skills']['options'], 'id');
            $skills->choiceCount = $rules['class_skills']['configured'] ? $rules['class_skills']['choice_count'] : 0;
            $this->collectErrors($skills, $errors);
            if (is_array($skills->skill_entry_ids) && array_intersect($skills->skill_entry_ids,array_column($benefits['skills'],'id'))) $errors['class_skill_entry_ids']=['Выберите навыки класса, которые ещё не даёт предыстория.'];
            $inventory = new CharacterInventoryForm(); $inventory->items = $data['inventory_items'];
            $inventory->profile = RuleProfile::findOne($character->rule_profile_id);
            $inventory->ownedRows = $id === null ? [] : CharacterItem::find()->where(['character_id' => $id, 'source_kind' => CharacterItem::SOURCE_DRAFT])->indexBy('id')->all();
            $this->collectErrors($inventory, $errors);
            $details = new SheetDetailsForm(); $details->details = $data['sheet_details'];
            $this->collectErrors($details, $errors);
            if (!is_array($data['creation_choices'])) $errors['creation_choices'] = ['Передайте объект групп выбора. Для пустого выбора используйте пустой объект.'];
            $context->ruleContextScores = $scores->hasErrors()?[]:$scores->base_scores;
            $context->ruleContextIncreases = $increases->hasErrors()?[]:$increases->increases;
            $creation = (new CreationBenefits())->get($context, is_array($data['creation_choices']) ? $data['creation_choices'] : [], array_merge(array_column($benefits['skills'], 'id'), $skills->hasErrors() ? [] : $skills->skill_entry_ids), $benefits);
            if ($creation['validation_errors']) $errors['creation_choices'] = $creation['validation_errors'];
            $packages=(new StartingPackages())->get($context,$creation,$data['equipment_choices']);
            if ($packages['validation_errors']) $errors['equipment_choices']=$packages['validation_errors'];
            if ($errors) throw new CharacterEditorValidationException($errors);

            $character->revision = $data['revision'] + 1;
            if (!$character->save(false)) throw new \RuntimeException('Не удалось сохранить персонажа.');
            (new AbilityScoreWriter())->save($character, $scores->base_scores);
            if ($character->background_entry_id !== null) {
                (new BackgroundAbilityIncreaseWriter())->save($character, $increases->increases);
                $saved = CharacterBackgroundChoice::findOne($character->id);
                (new CharacterBackgroundChoiceWriter())->save($character, $choices->tool_entry_id, $choices->equipment_choice, $saved === null ? 0 : $saved->revision);
            } else {
                $source = CharacterAbilityIncreaseSource::findOne(['character_id' => $character->id, 'slot' => CharacterAbilityIncreaseSource::BACKGROUND_SLOT]);
                if ($source !== null) { CharacterAbilityIncrease::deleteAll(['source_id' => $source->id]); $source->delete(); }
                CharacterBackgroundChoice::deleteAll(['character_id' => $character->id]);
            }
            if ($character->class_entry_id !== null && $rules['class_skills']['configured']) {
                $saved = CharacterClassSkillChoice::findOne($character->id);
                (new CharacterClassSkillsWriter())->save($character, $skills->skill_entry_ids, $saved === null ? 0 : $saved->revision);
            } else {
                CharacterClassSkill::deleteAll(['character_id' => $character->id]);
                CharacterClassSkillChoice::deleteAll(['character_id' => $character->id]);
            }
            $saved = CharacterInventoryState::findOne($character->id);
            (new CharacterInventoryWriter())->save($character, $inventory->normalizedItems(), $saved === null ? 0 : $saved->revision);
            $db = Yii::$app->db;
            $db->createCommand()->delete('{{%character_creation_choice}}', ['character_id' => $character->id])->execute();
            foreach ($creation['selections'] as $key => $ids) {
                [$sourcePart, $code] = explode(':', $key, 2);
                [$sourceId,$instanceKey]=array_pad(explode('~',$sourcePart,2),2,'root');
                foreach ($ids as $entryId) $db->createCommand()->insert('{{%character_creation_choice}}', ['character_id' => $character->id, 'source_entry_id' => (int) $sourceId, 'instance_key'=>$instanceKey, 'code' => $code, 'entry_id' => $entryId])->execute();
            }
            $db->createCommand()->delete('{{%character_rule_choice}}',['character_id'=>$character->id])->execute();
            $db->createCommand()->delete('{{%character_rule_instance}}',['character_id'=>$character->id])->execute();
            foreach($creation['instances'] as $key=>$instance){$db->createCommand()->insert('{{%character_rule_instance}}',['character_id'=>$character->id]+$instance)->execute();foreach($creation['rule_selections'] as $group=>$values)if(str_starts_with($group,$key.':'))foreach($values as $value)$db->createCommand()->insert('{{%character_rule_choice}}',['character_id'=>$character->id,'source_entry_id'=>$instance['source_entry_id'],'instance_key'=>$instance['instance_key'],'code'=>substr($group,strlen((string)$key)+1),'value'=>$value])->execute();}
            $normalized = $details->details;
            foreach ($normalized as &$value) if (is_string($value)) $value = trim($value);
            unset($value);
            $db->createCommand()->upsert('{{%character_sheet_details}}', ['character_id' => $character->id, 'data' => new JsonExpression($normalized)])->execute();
            (new StartingPackages())->save($character,$packages['selections']);
            (new StartingEquipmentWriter())->save($character,$data['starting_equipped_ids']);
            $state = $this->snapshot($character);
            if ($data['finish']) {
                if (!$state['readiness']['complete']) throw new CharacterEditorValidationException(['finish' => $state['readiness']['issues']]);
                $character->status = Character::STATUS_READY;
                $character->finished_at = gmdate('Y-m-d H:i:s');
                if (!$character->save(false, ['status', 'finished_at'])) throw new \RuntimeException('Не удалось завершить создание листа.');
                $state['character']['status'] = Character::STATUS_READY;
                $state['character']['finished_at'] = $character->finished_at;
                $db->createCommand()->insert('{{%character_sheet_snapshot}}', ['character_id' => $character->id, 'data' => new JsonExpression($state)])->execute();
            }
            return $state;
        });
    }

    public function delete(int $id, int $userId, int $revision): void
    {
        Yii::$app->db->transaction(function () use ($id, $userId, $revision): void {
            $character = $this->findOwned($id, $userId, true);
            if ($character->revision !== $revision) throw new ConflictHttpException('Лист уже изменён. Загрузите актуальные данные перед удалением.');
            $db = Yii::$app->db;
            foreach (['character_rule_choice','character_rule_instance','character_equipment_package','character_sheet_snapshot', 'character_sheet_details', 'character_creation_choice', 'character_item', 'character_inventory_state', 'character_class_skill', 'character_class_skill_choice', 'character_background_choice', 'character_ability_score'] as $table) $db->createCommand()->delete('{{%' . $table . '}}', ['character_id' => $id])->execute();
            $sources = CharacterAbilityIncreaseSource::find()->select('id')->where(['character_id' => $id])->column();
            CharacterAbilityIncrease::deleteAll(['source_id' => $sources]);
            CharacterAbilityIncreaseSource::deleteAll(['character_id' => $id]);
            if ($character->delete() !== 1) throw new \RuntimeException('Не удалось удалить персонажа.');
        });
    }

    private function snapshot(Character $character): array
    {
        $state = $this->sections($character);
        $state['character'] = $character->getAttributes(['id', 'name', 'user_id', 'rule_profile_id', 'target_level', 'species_entry_id', 'class_entry_id', 'background_entry_id', 'subclass_entry_id', 'species_variant_entry_id', 'status', 'revision', 'finished_at']);
        $state['sheet_basics'] = (new FirstLevelCharacterSheet())->get($character, $state['creation']);
        $state['sheet_details'] = array_replace(SheetDetailsForm::defaults(), array_intersect_key($this->jsonData('character_sheet_details', $character->id) ?? [], SheetDetailsForm::defaults()));
        $issues = [];
        if ($character->target_level !== 1) $issues[] = 'Первая версия поддерживает завершение персонажа первого уровня.';
        if (!$state['sheet_basics']['baseline_complete']) $issues[] = 'Полностью заполните вид, класс, базовые характеристики, увеличения предыстории и навыки класса.';
        if (!$state['benefits']['complete']) $issues[] = 'Завершите выборы предыстории: навыки, черта, инструмент и снаряжение должны быть доступны.';
        if (!$state['creation']['complete']) $issues = array_merge($issues, ['Завершите все архивные выдачи и группы выбора вида, класса и черт.'], $state['creation']['issues']);
        if (!$state['inventory']['manual_items_valid']) $issues[] = 'В ручном инвентаре есть недоступные предметы.';
        if ($state['sheet_basics']['hit_point_max'] === null) $issues[] = 'Не удалось рассчитать положительный максимум хитов. Проверьте класс, Телосложение и архивные бонусы.';
        if (!$state['starting_packages']['complete']) $issues=array_merge($issues,$state['starting_packages']['issues']);
        if (!$state['sheet_basics']['equipment_valid']) $issues[]='Исправьте экипировку доспехов и щитов.';
        if ($state['sheet_basics']['armor_class']!==null && $state['sheet_basics']['armor_class']<1) $issues[]='Расчётная КД должна быть положительной. Проверьте бонусы и экипировку.';
        foreach (['armor_class' => 'КД', 'size' => 'размер', 'languages' => 'языки'] as $key => $label) if ($state['sheet_basics'][$key] === null || $state['sheet_basics'][$key] === '') $issues[] = 'Заполните ' . $label . ' в полях листа.';
        $state['readiness'] = ['complete' => !$issues, 'issues' => array_values(array_unique($issues))];
        return $state;
    }

    private function sections(Character $character, $choices = null, $classSkillIds = null): array
    {
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $origins = [];
        foreach (['species_entry_id', 'class_entry_id', 'background_entry_id', 'subclass_entry_id', 'species_variant_entry_id'] as $field) {
            $entry = $character->$field === null ? null : (new \app\services\rules\CreationRules())->findAvailable($character->$field, $profile);
            $origins[$field] = $entry === null ? null : $entry->getAttributes(['id', 'title', 'body']);
        }
        $options = ['species' => [], 'class' => [], 'background' => [], 'subclass'=>[], 'species_variant'=>[]];
        if ($profile !== null) {
            foreach (CatalogEntry::find()->where(['type' => array_keys($options), 'is_published' => true])->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC])->all() as $entry) {
                if(in_array($entry->type,['subclass','species_variant'],true)){$table=$entry->type==='subclass'?'subclass_definition':'species_variant_definition';$parent=$entry->type==='subclass'?'class_entry_id':'species_entry_id';$row=(new Query())->from('{{%'.$table.'}}')->where(['catalog_entry_id'=>$entry->id])->one();if(!$row||$row[$parent]!==$character->$parent||$row['selection_level']>$character->target_level)continue;}
                if ((new CatalogAvailability())->isAvailableInProfile($entry, $profile)) $options[$entry->type][] = $entry->getAttributes(['id', 'title']);
            }
        }
        $benefits = (new CharacterBackgroundBenefits())->get($character);
        $class = (new CharacterClassSkills())->get($character);
        $baseSkills = array_merge(array_column($benefits['skills'], 'id'), $classSkillIds === null ? $class['effective_skill_entry_ids'] : (is_array($classSkillIds) ? array_intersect(array_filter($classSkillIds, 'is_int'), array_column($class['options'], 'id')) : []));
        $creation=(new CreationBenefits())->get($character,$choices,$baseSkills,$benefits);
        return [
            'starting_packages'=>(new StartingPackages())->get($character,$creation),
            'context' => $character->getAttributes(self::CONTEXT_FIELDS), 'entry_options' => $options,
            'origins' => $origins, 'profile_title' => $profile?->title,
            'reference' => ['species_speed_feet' => $origins['species_entry_id'] === null ? null : \app\models\rules\SpeciesDefinition::findOne($character->species_entry_id)?->speed_feet,
                'hit_die_sides' => $origins['class_entry_id'] === null ? null : \app\models\rules\ClassDefinition::findOne($character->class_entry_id)?->hit_die_sides],
            'ability_scores' => (new AbilityScoreCalculator())->calculate($character,$creation),
            'background_abilities' => (new BackgroundAbilityDistribution())->get($character),
            'benefits' => $benefits,
            'class_skills' => $class,
            'creation' => $creation,
            'inventory' => (new CharacterInventory())->get($character),
        ];
    }

    private function jsonData(string $table, int $id): ?array
    {
        $value = (new Query())->select('data')->from('{{%' . $table . '}}')->where(['character_id' => $id])->scalar();
        return $value === false ? null : (is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value);
    }

    private function findOwned(int $id, int $userId, bool $update): Character
    {
        $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%character}} WHERE [[id]] = CAST(:id AS BIGINT) AND [[user_id]] = :user_id' . ($update ? ' FOR UPDATE' : ' FOR SHARE'), [':id' => $id, ':user_id' => $userId])->queryScalar();
        if ($found === false) throw new NotFoundHttpException('Персонаж не найден.');
        return Character::findOne($found);
    }

    private function lockEntries(Character $character): void
    {
        $ids = array_filter([$character->species_entry_id, $character->class_entry_id, $character->background_entry_id], static fn($id) => $id !== null);
        sort($ids, SORT_NUMERIC);
        foreach (array_unique($ids) as $id) Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = CAST(:id AS BIGINT) FOR SHARE', [':id' => $id])->queryScalar();
    }

    private function validateContextTypes(Character $character, bool $allowNoProfile): void
    {
        $errors = [];
        foreach (['rule_profile_id', 'species_entry_id', 'class_entry_id', 'background_entry_id', 'subclass_entry_id', 'species_variant_entry_id'] as $field) {
            $value = $character->$field;
            if ($value === null && ($field !== 'rule_profile_id' || $allowNoProfile)) continue;
            if (!is_int($value) || $value < 1 || $value > 2147483647) $errors[$field] = ['Выберите запись по корректному целому ID.'];
        }
        if (!is_int($character->target_level) || $character->target_level < 1 || $character->target_level > 20) $errors['target_level'] = ['Уровень должен быть целым числом от 1 до 20.'];
        if ($errors) throw new CharacterEditorValidationException($errors);
    }

    private function collectErrors(\yii\base\Model $form, array &$errors): void
    {
        if (!$form->validate()) $errors = array_merge($errors, $form->getErrors());
    }
}
