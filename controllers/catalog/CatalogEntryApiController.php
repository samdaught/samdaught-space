<?php

namespace app\controllers\catalog;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

use app\controllers\ApiController;
use app\models\catalog\CatalogEntry;
use app\models\catalog\CatalogEntryType;
use app\models\catalog\BackgroundAbilityOptionsForm;
use app\models\catalog\SkillDefinitionForm;
use app\models\catalog\BackgroundSkillsForm;
use app\models\catalog\BackgroundFeatForm;
use app\models\catalog\FeatDefinitionForm;
use app\models\catalog\ItemDefinitionForm;
use app\models\catalog\BackgroundToolForm;
use app\models\catalog\BackgroundStartingEquipmentForm;
use app\models\catalog\SpeciesDefinitionForm;
use app\models\catalog\ClassDefinitionForm;
use app\models\rules\AbilityDefinition;
use app\models\rules\SkillDefinition;
use app\models\rules\BackgroundSkillProficiency;
use app\models\rules\BackgroundFeat;
use app\models\rules\FeatCategory;
use app\models\rules\FeatDefinition;
use app\models\rules\ItemDefinition;
use app\models\rules\ItemKind;
use app\models\rules\ToolDefinition;
use app\models\rules\ToolGroup;
use app\models\rules\BackgroundToolProficiency;
use app\models\rules\SpeciesDefinition;
use app\models\rules\ClassDefinition;
use app\models\rules\ClassSavingThrowProficiency;
use app\models\rules\HitDieDefinition;
use app\services\rules\BackgroundAbilityOptionsWriter;
use app\services\rules\SkillDefinitionWriter;
use app\services\rules\BackgroundSkillsWriter;
use app\services\rules\BackgroundFeatWriter;
use app\services\rules\FeatDefinitionWriter;
use app\services\rules\ItemDefinitionWriter;
use app\services\rules\BackgroundToolWriter;
use app\services\rules\BackgroundStartingEquipmentReader;
use app\services\rules\BackgroundStartingEquipmentWriter;
use app\services\rules\SpeciesDefinitionWriter;
use app\services\rules\ClassDefinitionWriter;
use app\models\catalog\ClassSkillsForm;
use app\models\rules\{ClassSkillOption, RuleProfile};
use app\services\rules\{ClassSkillsWriter, SkillOptions};
use app\services\rules\CreationRules;
use app\models\catalog\CreationRulesForm;

class CatalogEntryApiController extends ApiController
{

    public function behaviors(): array
    {

        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['manageCatalog'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    if (Yii::$app->user->isGuest) {
                        throw new \yii\web\UnauthorizedHttpException('Вы должны быть авторизованы! А вдруг вы кентавр?');
                    } else {
                        throw new \yii\web\ForbiddenHttpException('Не достаточно прав! Совсем как у кентавра!');
                    }
                },
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['POST'],
                    'copy-to-world' => ['POST'],
                    'publish' => ['POST'],
                    'unpublish' => ['POST'],
                    'update' => ['PATCH'],
                    'types' => ['GET'],
                    'ability-options' => ['GET'],
                    'feat-categories' => ['GET'],
                    'item-options' => ['GET'],
                    'basic-rule-options' => ['GET'],
                    'creation-rule-options' => ['GET'],
                    'mechanics-options' => ['GET'],
                    'rulebook-options' => ['GET'],
                    'audit' => ['GET'],
                ],
            ]
        ];
    }

    public function actionIndex(): array
    {
        $pageInput = Yii::$app->request->get('page', '1');
        $perPageInput = Yii::$app->request->get('per_page', '20');
        $page = filter_var(
            $pageInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $perPage = filter_var(
            $perPageInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );

        if ($page === false || $perPage === false) {
            throw new \yii\web\BadRequestHttpException(
                'page должен быть целым числом от 1, per_page — от 1 до 100.'
            );
        }

        $query = CatalogEntry::find()
            ->alias('entry')
            ->innerJoin(
                ['category' => CatalogEntryType::tableName()],
                '[[category.code]] = [[entry.type]]'
            )
            ->leftJoin(['rules'=>'{{%entry_rules}}'],'[[rules.entry_id]]=[[entry.id]]')
            ->innerJoin(['profile' => RuleProfile::tableName()], '[[profile.id]] = [[entry.rule_profile_id]]')
            ->select([
                'entry.id',
                'mechanics_status'=>new \yii\db\Expression("CASE WHEN [[rules.ready]] THEN 'ready' WHEN [[rules.entry_id]] IS NOT NULL THEN 'partial' ELSE 'text' END"),
                'entry.type',
                'entry.slug',
                'entry.title',
                'entry.body',
                'entry.rule_profile_id',
                'rule_profile_title' => 'profile.title',
                'entry.source_id',
                'entry.is_published',
                'type_title' => 'category.title',
            ]);

        $total = (int) (clone $query)->count();

        $pageCount = max(1, (int) ceil($total / $perPage));

        if ($page > $pageCount) {
            throw new \yii\web\NotFoundHttpException('Страница списка не найдена.');
        }

        $offset = ($page - 1) * $perPage;

        $entries = $query
            ->orderBy(['entry.id' => SORT_DESC])
            ->limit($perPage)
            ->offset($offset)
            ->asArray()
            ->all();

        return [
            'data' => $entries,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'page_count' => $pageCount,
            ],
        ];
    }

    public function actionAudit(): array
    {
        $report=[];foreach(CatalogEntry::find()->orderBy('id')->each() as $entry)$report[]=(new \app\services\rules\ArchiveReadiness())->inspect($entry);
        return ['data'=>$report];
    }

    public function actionView(int $id): array
    {
        $entry = CatalogEntry::findOne($id);

        if ($entry === null) {
            throw new \yii\web\NotFoundHttpException('Запись не найдена');
        }

        return ['data' => $this->getEntryData($entry)];
    }

    public function actionCreate(): array
    {
        $data = Yii::$app->request->getBodyParams();

        if (!is_array($data)) {
            throw new \yii\web\BadRequestHttpException('Запрос не соответствует ожидаемой структуре');
        }

        $entry = new CatalogEntry();
        $entry->load($data, '');
        $entry->revision = 1;

        $saved = $this->saveEntry($entry, $data, true);

        if ($saved === false) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте поля формы.',
                    'fields' => $entry->getErrors(),
                ],
            ];
        }

        Yii::$app->response->setStatusCode(201);

        return ['data' => $this->getEntryData($entry)];
    }

    public function actionCopyToWorld(int $id): array
    {
        $source = CatalogEntry::findOne($id);
        if ($source === null) {
            throw new \yii\web\NotFoundHttpException('Запись не найдена.');
        }
        if (in_array($source->type, ['background', 'subclass', 'species_variant'], true)) {
            throw new \yii\web\BadRequestHttpException('Эту зависимую запись пока нельзя переносить отдельно. Сначала создайте её основу в целевом мире.');
        }
        $request = Yii::$app->request->getBodyParams();
        $targetId = is_array($request) ? ($request['rule_profile_id'] ?? null) : null;
        if (!is_int($targetId) || $targetId < 1) {
            throw new \yii\web\BadRequestHttpException('Выберите целевой мир.');
        }
        $target = RuleProfile::findOne($targetId);
        if ($target === null) {
            throw new \yii\web\NotFoundHttpException('Целевой мир не найден.');
        }
        if ((int) $source->rule_profile_id === (int) $target->id) {
            throw new \yii\web\BadRequestHttpException('Запись уже принадлежит этому миру.');
        }

        $payload = $this->copyPayload($this->getEntryData($source), $target->id);
        $copy = new CatalogEntry();
        $copy->load($payload, '');
        $copy->revision = 1;
        if (!$this->saveEntry($copy, $payload, true)) {
            Yii::$app->response->setStatusCode(422);
            return ['error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Не удалось подготовить копию. Проверьте связи записи.',
                'fields' => $copy->getErrors(),
            ]];
        }
        Yii::$app->response->setStatusCode(201);
        return ['data' => $this->getEntryData($copy)];
    }
    public function actionUpdate(int $id): array
    {
        return Yii::$app->db->transaction(function () use ($id): array {
            Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $id]
            )->queryScalar();

            return $this->updateEntry($id);
        });
    }

    private function updateEntry(int $id): array
    {
        $entry = CatalogEntry::findOne($id);

        if ($entry === null) {
            throw new \yii\web\NotFoundHttpException('Запись не найдена');
        }

        $data = Yii::$app->request->getBodyParams();

        if (!is_array($data)) {
            throw new \yii\web\BadRequestHttpException('Неверное тело запроса');
        }

        if (!isset($data['revision']) || !is_int($data['revision']) || $data['revision'] !== $entry->revision) throw new \yii\web\ConflictHttpException('Запись или её связи изменились. Перезагрузите запись перед сохранением.');
        $entry->revision++;

        foreach (['title', 'body', 'source_id'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $entry->$attribute = $data[$attribute];
            }
        }

        $saved = $this->saveEntry($entry, $data, false);

        if ($saved === false) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте поля формы.',
                    'fields' => $entry->getErrors(),
                ],
            ];
        }

        return ['data' => $this->getEntryData($entry)];
    }

    public function actionAbilityOptions(): array
    {
        return ['data' => AbilityDefinition::find()->select(['code', 'title'])
            ->orderBy(['code' => SORT_ASC])->asArray()->all()];
    }

    public function actionFeatCategories(): array
    {
        return ['data' => FeatCategory::find()->select(['code', 'title'])
            ->orderBy(['title' => SORT_ASC])->asArray()->all()];
    }

    public function actionItemOptions(): array
    {
        return ['data' => [
            'item_kinds' => ItemKind::find()->select(['code', 'title'])->orderBy('title')->asArray()->all(),
            'tool_kind_code' => ItemKind::TOOL,
            'tool_groups' => ToolGroup::find()->select(['code', 'title'])->orderBy('title')->asArray()->all(),
            'ability_options' => AbilityDefinition::find()->select(['code', 'title'])->orderBy('code')->asArray()->all(),
        ]];
    }

    public function actionBasicRuleOptions(): array
    {
        $input = Yii::$app->request->get('rule_profile_id');
        $profile = null;
        if ($input !== null) {
            $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new \yii\web\BadRequestHttpException('Мир должен быть положительным целым числом.');
            $profile = RuleProfile::find()->where('[[id]] = CAST(:id AS BIGINT)', [':id' => $id])->one();
            if ($profile === null) throw new \yii\web\NotFoundHttpException('Мир не найден.');
        }
        return ['data' => [
            'skill_options' => (new SkillOptions())->get($profile),
            'max_class_skill_options' => ClassSkillsForm::MAX_OPTIONS,
            'hit_dice' => HitDieDefinition::find()->select(['sides', 'title'])->orderBy('sides')->asArray()->all(),
            'ability_options' => AbilityDefinition::find()->select(['code', 'title'])->orderBy('code')->asArray()->all(),
            'required_saving_throw_count' => ClassDefinitionForm::REQUIRED_SAVING_THROW_COUNT,
            'max_speed_feet' => SpeciesDefinition::MAX_SPEED_FEET,
        ]];
    }

    public function actionCreationRuleOptions(): array
    {
        $input = Yii::$app->request->get('rule_profile_id');
        $profile = null;
        if ($input !== null) {
            $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id === false) throw new \yii\web\BadRequestHttpException('Некорректный мир.');
            $profile = RuleProfile::findOne($id);
            if ($profile === null) throw new \yii\web\NotFoundHttpException('Мир не найден.');
        }
        return ['data' => (new CreationRules())->options($profile)];
    }

    public function actionMechanicsOptions(): array
    {
        $id=Yii::$app->request->get('rule_profile_id');
        $profile=null;
        if ($id!==null) {
            $id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false || ($profile=RuleProfile::findOne($id))===null) throw new \yii\web\BadRequestHttpException('Выберите существующий мир.');
        }
        return ['data'=>(new \app\services\rules\EntryMechanics())->options($profile)];
    }

    public function actionRulebookOptions(): array
    {
        $id=Yii::$app->request->get('rule_profile_id');$profile=null;
        if($id!==null){$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false||($profile=RuleProfile::findOne($id))===null)throw new \yii\web\BadRequestHttpException('Выберите существующий мир.');}
        return ['data'=>(new \app\services\rules\Rulebook())->options($profile)];
    }

    private function getEntryData(CatalogEntry $entry): array
    {
        $data = $entry->getAttributes([
            'id', 'type', 'slug', 'title', 'body', 'rule_profile_id', 'source_id', 'is_published',
        ]);
        if (in_array($entry->type, CreationRules::SOURCE_TYPES, true)) $data['creation_rules'] = (new CreationRules())->read($entry->id);
        if ($entry->type === 'skill') {
            $definition = SkillDefinition::findOne(['catalog_entry_id' => $entry->id]);
            $data['skill_ability_code'] = $definition === null ? null : $definition->ability_code;
        }
        if ($entry->type === 'background') {
            $data['skill_entry_ids'] = BackgroundSkillProficiency::find()->select('skill_entry_id')
                ->where(['background_entry_id' => $entry->id])->orderBy(['skill_entry_id' => SORT_ASC])->column();
            $link = BackgroundFeat::findOne(['background_entry_id' => $entry->id]);
            $data['feat_entry_id'] = $link === null ? null : $link->feat_entry_id;
            $tool = BackgroundToolProficiency::findOne($entry->id);
            $data['background_tool'] = $tool === null ? null : $tool->getAttributes(['tool_entry_id', 'tool_group_code']);
            $data['starting_equipment'] = (new BackgroundStartingEquipmentReader())->get($entry);
        }
        if ($entry->type === 'feat') {
            $definition = FeatDefinition::findOne(['catalog_entry_id' => $entry->id]);
            $data['feat_category_code'] = $definition === null ? null : $definition->category_code;
        }
        if ($entry->type === 'item') {
            $item = ItemDefinition::findOne($entry->id);
            $tool = ToolDefinition::findOne($entry->id);
            $data['item_kind_code'] = $item === null ? null : $item->kind_code;
            $data['tool_group_code'] = $tool === null ? null : $tool->group_code;
            $data['tool_ability_code'] = $tool === null ? null : $tool->ability_code;
        }
        if ($entry->type === 'species') {
            $definition = SpeciesDefinition::findOne($entry->id);
            $data['species_speed_feet'] = $definition === null ? null : $definition->speed_feet;
            $data['species_card'] = (new \app\services\rules\SpeciesCard())->read($entry->id);
        }
        if ($entry->type === 'class') {
            $definition = ClassDefinition::findOne($entry->id);
            $data['class_card'] = (new \app\services\rules\ClassCard())->read($entry->id);
            $data['class_hit_die_sides'] = $definition === null ? null : $definition->hit_die_sides;
            $data['class_skill_choice_count'] = $definition === null ? null : $definition->skill_choice_count;
            $data['class_skill_entry_ids'] = ClassSkillOption::find()->select('skill_entry_id')
                ->where(['class_entry_id' => $entry->id])->orderBy('skill_entry_id')->column();
            $data['class_saving_throw_codes'] = ClassSavingThrowProficiency::find()->select('ability_code')
                ->where(['class_entry_id' => $entry->id])->orderBy('ability_code')->column();
        }
        $data['revision'] = $entry->revision;
        $data['rulebook'] = (new \app\services\rules\Rulebook())->read($entry->id);
        $data['mechanics'] = (new \app\services\rules\EntryMechanics())->read($entry);
        return $data;
    }

    /**
     * Копия не сохраняет связи с записями исходного мира. Она создаётся
     * неопубликованным черновиком, а мастер связывает записи целевого мира явно.
     */
    private function copyPayload(array $data, int $targetProfileId): array
    {
        unset($data['id'], $data['revision'], $data['slug']);
        $data['rule_profile_id'] = $targetProfileId;
        $data['is_published'] = false;

        if (isset($data['creation_rules'])) {
            $data['creation_rules'] = ['grants' => [], 'choices' => [], 'hit_point_bonus' => 0];
        }
        if ($data['type'] === 'class') {
            // Навыки принадлежат миру. Черновик остаётся валидным, а мастер
            // явно настраивает число выборов и навыки целевого мира.
            $data['class_skill_choice_count'] = 0;
            $data['class_skill_entry_ids'] = [];
        }
        if (isset($data['mechanics']) && is_array($data['mechanics'])) {
            $data['mechanics']['equipment'] = [];
            $data['mechanics']['spell_ids'] = [];
            $data['mechanics']['class_ids'] = [];
        }
        if (isset($data['rulebook']) && is_array($data['rulebook'])) {
            $rules = $data['rulebook'];
            $rules['parent_entry_id'] = null;
            $rules['selection_level'] = 1;
            $rules['prerequisites'] = ['op' => 'all', 'conditions' => []];
            $rules['choices'] = [];
            $rules['distinct_choices'] = [];
            $rules['effects'] = [];
            $rules['casting_choice_code'] = null;
            $rules['spell_list_class_ids'] = [];
            $rules['unlocks'] = [];
            $rules['spell_grants'] = [];
            $rules['spell_picks'] = [];
            $rules['resources'] = [];
            $rules['ready'] = false;
            if (isset($rules['item']) && is_array($rules['item'])) {
                $rules['item']['magic'] = null;
            }
            $data['rulebook'] = $rules;
        }
        return $data;
    }

    private function saveEntry(CatalogEntry $entry, array $data, bool $creating): bool
    {
        try{return Yii::$app->db->transaction(function () use ($entry, $data, $creating): bool {
            return $this->validateAndSaveEntry($entry, $data, $creating);
        });}catch(\InvalidArgumentException $error){$entry->addError('rulebook',$error->getMessage());return false;}
    }

    private function validateAndSaveEntry(CatalogEntry $entry, array $data, bool $creating): bool
    {
        $form = null;
        $skillForm = null;
        $backgroundSkillsForm = null;
        $featForm = null;
        $backgroundFeatForm = null;
        $itemForm = null;
        $backgroundToolForm = null;
        $equipmentForm = null;
        $speciesForm = null;
        $classForm = null;
        $classSkillsForm = null;
        $creationForm = null;
        $hasCodes = array_key_exists('ability_codes', $data);
        $valid = $entry->validate();

        if ($entry->type === 'background' && ($creating || $hasCodes)) {
            $form = new BackgroundAbilityOptionsForm();
            $form->ability_codes = $data['ability_codes'] ?? null;
            if (!$form->validate()) {
                $entry->addErrors($form->getErrors());
                $valid = false;
            }
        } elseif ($hasCodes) {
            $entry->addError('ability_codes', 'Характеристики предыстории доступны только для категории background.');
            $valid = false;
        }

        $hasSkillAbility = array_key_exists('skill_ability_code', $data);
        if ($entry->type === 'skill' && ($creating || $hasSkillAbility)) {
            $skillForm = new SkillDefinitionForm();
            $skillForm->skill_ability_code = $data['skill_ability_code'] ?? null;
            if (!$skillForm->validate()) {
                $entry->addErrors($skillForm->getErrors());
                $valid = false;
            }
        } elseif ($hasSkillAbility) {
            $entry->addError('skill_ability_code', 'Характеристика навыка доступна только для категории skill.');
            $valid = false;
        }

        $hasSkillIds = array_key_exists('skill_entry_ids', $data);
        if ($entry->type === 'background' && ($creating || $hasSkillIds)) {
            $backgroundSkillsForm = new BackgroundSkillsForm();
            $backgroundSkillsForm->ruleProfileId = $entry->hasErrors('rule_profile_id')
                ? null : (int) $entry->rule_profile_id;
            $backgroundSkillsForm->skill_entry_ids = $data['skill_entry_ids'] ?? null;
            if (!$backgroundSkillsForm->validate()) {
                $entry->addErrors($backgroundSkillsForm->getErrors());
                $valid = false;
            }
        } elseif ($hasSkillIds) {
            $entry->addError('skill_entry_ids', 'Выдаваемые навыки доступны только для категории background.');
            $valid = false;
        }

        $hasFeatCategory = array_key_exists('feat_category_code', $data);
        if ($entry->type === 'feat' && ($creating || $hasFeatCategory)) {
            $featForm = new FeatDefinitionForm();
            $featForm->catalogEntryId = $creating ? null : $entry->id;
            $featForm->feat_category_code = $data['feat_category_code'] ?? null;
            if (!$featForm->validate()) {
                $entry->addErrors($featForm->getErrors());
                $valid = false;
            }
        } elseif ($hasFeatCategory) {
            $entry->addError('feat_category_code', 'Категория черты доступна только для записи feat.');
            $valid = false;
        }

        $hasFeatId = array_key_exists('feat_entry_id', $data);
        if ($entry->type === 'background' && ($creating || $hasFeatId)) {
            $backgroundFeatForm = new BackgroundFeatForm();
            $backgroundFeatForm->ruleProfileId = $entry->hasErrors('rule_profile_id')
                ? null : (int) $entry->rule_profile_id;
            $backgroundFeatForm->feat_entry_id = $data['feat_entry_id'] ?? null;
            if (!$backgroundFeatForm->validate()) {
                $entry->addErrors($backgroundFeatForm->getErrors());
                $valid = false;
            }
        } elseif ($hasFeatId) {
            $entry->addError('feat_entry_id', 'Выдаваемая черта доступна только для предыстории.');
            $valid = false;
        }

        $itemFields = ['item_kind_code', 'tool_group_code', 'tool_ability_code'];
        $hasItemFields = count(array_intersect_key($data, array_flip($itemFields))) > 0;
        if ($entry->type === 'item' && ($creating || $hasItemFields)) {
            $item = $creating ? null : ItemDefinition::findOne($entry->id);
            $tool = $creating ? null : ToolDefinition::findOne($entry->id);
            $kind = array_key_exists('item_kind_code', $data) ? $data['item_kind_code'] : ($item === null ? null : $item->kind_code);
            $previous = [
                'item_kind_code' => $item === null ? null : $item->kind_code,
                'tool_group_code' => $kind !== ItemKind::TOOL || $tool === null ? null : $tool->group_code,
                'tool_ability_code' => $kind !== ItemKind::TOOL || $tool === null ? null : $tool->ability_code,
            ];
            $itemForm = new ItemDefinitionForm();
            $itemForm->catalogEntryId = $creating ? null : $entry->id;
            foreach ($itemFields as $field) {
                $itemForm->$field = array_key_exists($field, $data) ? $data[$field] : $previous[$field];
            }
            if (!$itemForm->validate()) {
                $entry->addErrors($itemForm->getErrors());
                $valid = false;
            }
        } elseif ($hasItemFields) {
            $entry->addError('item_kind_code', 'Свойства предмета доступны только для категории item.');
            $valid = false;
        }

        $hasBackgroundTool = array_key_exists('background_tool', $data);
        if ($entry->type === 'background' && ($creating || $hasBackgroundTool)) {
            $backgroundToolForm = new BackgroundToolForm();
            $backgroundToolForm->ruleProfileId = $entry->hasErrors('rule_profile_id')
                ? null : (int) $entry->rule_profile_id;
            $backgroundToolForm->background_tool = $data['background_tool'] ?? null;
            if (!$backgroundToolForm->validate()) {
                $entry->addErrors($backgroundToolForm->getErrors());
                $valid = false;
            }
        } elseif ($hasBackgroundTool) {
            $entry->addError('background_tool', 'Правило владения инструментом доступно только для предыстории.');
            $valid = false;
        }

        $hasEquipment = array_key_exists('starting_equipment', $data);
        if ($entry->type === 'background' && ($creating || $hasEquipment)) {
            $equipmentForm = new BackgroundStartingEquipmentForm();
            $equipmentForm->ruleProfileId = $entry->hasErrors('rule_profile_id') ? null : (int) $entry->rule_profile_id;
            $equipmentForm->backgroundToolConfigured = $backgroundToolForm !== null
                ? !$backgroundToolForm->hasErrors()
                : BackgroundToolProficiency::find()->where(['background_entry_id' => $entry->id])->exists();
            $equipmentForm->starting_equipment = $data['starting_equipment'] ?? null;
            if (!$equipmentForm->validate()) {
                $entry->addErrors($equipmentForm->getErrors());
                $valid = false;
            }
        } elseif ($hasEquipment) {
            $entry->addError('starting_equipment', 'Стартовый комплект доступен только для предыстории.');
            $valid = false;
        }

        $hasSpeed = array_key_exists('species_speed_feet', $data);
        if ($entry->type === 'species' && ($creating || $hasSpeed)) {
            $speciesForm = new SpeciesDefinitionForm();
            $speciesForm->species_speed_feet = $data['species_speed_feet'] ?? null;
            if (!$speciesForm->validate()) {
                $entry->addErrors($speciesForm->getErrors());
                $valid = false;
            }
        } elseif ($hasSpeed) {
            $entry->addError('species_speed_feet', 'Базовая скорость вида доступна только для категории species.');
            $valid = false;
        }
        $classFields = ['class_hit_die_sides', 'class_saving_throw_codes'];
        $hasClassFields = count(array_intersect_key($data, array_flip($classFields))) > 0;
        if ($entry->type === 'class' && ($creating || $hasClassFields)) {
            $definition = $creating ? null : ClassDefinition::findOne($entry->id);
            $previous = [
                'class_hit_die_sides' => $definition === null ? null : $definition->hit_die_sides,
                'class_saving_throw_codes' => $creating ? [] : ClassSavingThrowProficiency::find()->select('ability_code')
                    ->where(['class_entry_id' => $entry->id])->orderBy('ability_code')->column(),
            ];
            $classForm = new ClassDefinitionForm();
            foreach ($classFields as $field) {
                $classForm->$field = array_key_exists($field, $data) ? $data[$field] : $previous[$field];
            }
            if (!$classForm->validate()) {
                $entry->addErrors($classForm->getErrors());
                $valid = false;
            }
        } elseif ($hasClassFields) {
            $entry->addError('class_hit_die_sides', 'Базовые свойства класса доступны только для категории class.');
            $valid = false;
        }

        $skillFields = ['class_skill_choice_count', 'class_skill_entry_ids'];
        $hasClassSkills = count(array_intersect_key($data, array_flip($skillFields))) > 0;
        if ($entry->type === 'class' && ($creating || $hasClassSkills)) {
            $definition = $creating ? null : ClassDefinition::findOne($entry->id);
            $previous = [
                'class_skill_choice_count' => $definition === null ? null : $definition->skill_choice_count,
                'class_skill_entry_ids' => $creating ? [] : ClassSkillOption::find()->select('skill_entry_id')
                    ->where(['class_entry_id' => $entry->id])->orderBy('skill_entry_id')->column(),
            ];
            $classSkillsForm = new ClassSkillsForm();
            if (!$creating && $definition === null && $classForm === null) {
                $entry->addError('class_hit_die_sides', 'Для старого класса заполните также кость хитов и спасброски.');
                $valid = false;
            }
            $classSkillsForm->ruleProfileId = $entry->rule_profile_id;
            foreach ($skillFields as $field) {
                $classSkillsForm->$field = array_key_exists($field, $data) ? $data[$field] : $previous[$field];
            }
            if (!$classSkillsForm->validate()) {
                $entry->addErrors($classSkillsForm->getErrors());
                $valid = false;
            }
        } elseif ($hasClassSkills) {
            $entry->addError('class_skill_entry_ids', 'Выборы навыков класса доступны только для категории class.');
            $valid = false;
        }

        $hasCreationRules = array_key_exists('creation_rules', $data);
        if (in_array($entry->type, CreationRules::SOURCE_TYPES, true) && ($creating || $hasCreationRules)) {
            $creationForm = new CreationRulesForm();
            $creationForm->sourceId = $creating ? null : $entry->id;
            $creationForm->ruleProfileId = $entry->rule_profile_id;
            $creationForm->creation_rules = $hasCreationRules ? $data['creation_rules'] : ['grants' => [], 'choices' => []];
            if (!$creationForm->validate()) { $entry->addErrors($creationForm->getErrors()); $valid = false; }
        } elseif ($hasCreationRules) { $entry->addError('creation_rules', 'Эта категория не может задавать выдачи при создании.'); $valid = false; }

        $speciesCard = null;
        if (array_key_exists('species_card', $data)) {
            try {
                $speciesCard = (new \app\services\rules\SpeciesCard())->validate($entry, $data['species_card']);
            } catch (\InvalidArgumentException $error) {
                $entry->addError('species_card', $error->getMessage());
                $valid = false;
            }
        }

        $classCard = null;
        if (array_key_exists('class_card', $data)) {
            try {
                $classCard = (new \app\services\rules\ClassCard())->validate($entry, $data['class_card']);
            } catch (\InvalidArgumentException $error) {
                $entry->addError('class_card', $error->getMessage());
                $valid = false;
            }
        }

        $rulebook = null;
        if (array_key_exists('rulebook',$data) || $creating && in_array($entry->type,['subclass','species_variant'],true)) {
            try { $rulebook=(new \app\services\rules\Rulebook())->validate($entry,$data['rulebook']??[],$itemForm?->item_kind_code); }
            catch (\InvalidArgumentException $error) { $entry->addError('rulebook',$error->getMessage()); $valid=false; }
        }
        $mechanics = null;
        if (array_key_exists('mechanics',$data)) {
            try { $mechanics=(new \app\services\rules\EntryMechanics())->validate($entry,$data['mechanics'],$itemForm?->item_kind_code); }
            catch (\InvalidArgumentException $error) { $entry->addError('mechanics',$error->getMessage()); $valid=false; }
        } elseif ($creating && in_array($entry->type,['spell','class'],true)) {
            $entry->addError('mechanics','Заполните механику заклинания или заклинательство класса.'); $valid=false;
        }
        if (!$valid) {
            return false;
        }

        if (!$entry->save(false)) {
            throw new \RuntimeException('Не удалось сохранить запись архива.');
        }
        if ($form !== null) {
            (new BackgroundAbilityOptionsWriter())->save($entry, $form->ability_codes);
        }
        if ($skillForm !== null) {
            (new SkillDefinitionWriter())->save($entry, $skillForm->skill_ability_code);
        }
        if ($backgroundSkillsForm !== null) {
            (new BackgroundSkillsWriter())->save($entry, $backgroundSkillsForm->skill_entry_ids);
        }
        if ($featForm !== null) {
            (new FeatDefinitionWriter())->save($entry, $featForm->feat_category_code);
        }
        if ($backgroundFeatForm !== null) {
            (new BackgroundFeatWriter())->save($entry, $backgroundFeatForm->feat_entry_id);
        }
        if ($itemForm !== null) {
            (new ItemDefinitionWriter())->save($entry, $itemForm->item_kind_code, $itemForm->tool_group_code, $itemForm->tool_ability_code);
        }
        if ($backgroundToolForm !== null) {
            (new BackgroundToolWriter())->save($entry, $backgroundToolForm->background_tool);
        }
        if ($equipmentForm !== null) {
            (new BackgroundStartingEquipmentWriter())->save($entry, $equipmentForm->starting_equipment);
        }
        if ($speciesForm !== null) {
            (new SpeciesDefinitionWriter())->save($entry, $speciesForm->species_speed_feet);
        }
        if ($classForm !== null) {
            (new ClassDefinitionWriter())->save($entry, $classForm->class_hit_die_sides, $classForm->class_saving_throw_codes);
        }
        if ($classSkillsForm !== null) {
            (new ClassSkillsWriter())->save($entry, $classSkillsForm->class_skill_choice_count, $classSkillsForm->class_skill_entry_ids);
        }
        if ($creationForm !== null) (new CreationRules())->save($entry, $creationForm->creation_rules);
        if ($mechanics !== null) (new \app\services\rules\EntryMechanics())->save($entry,$mechanics);
        if ($rulebook !== null) (new \app\services\rules\Rulebook())->save($entry,$rulebook);
        if ($speciesCard !== null) (new \app\services\rules\SpeciesCard())->save($entry->id, $speciesCard);
        if ($classCard !== null) (new \app\services\rules\ClassCard())->save($entry->id, $classCard);
        return true;
    }

    public function actionPublish(int $id): array
    {
        $entry = CatalogEntry::findOne($id);

        if ($entry === null) {
            throw new \yii\web\NotFoundHttpException('Запись не найдена');
        }

        $entry->is_published = true;

        $saved = $entry->save();

        if ($saved === false) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте поля формы.',
                    'fields' => $entry->getErrors(),
                ],
            ];
        }

        return [
            'data' => $entry->getAttributes([
                'id',
                'type',
                'slug',
                'title',
                'body',
                'rule_profile_id',
                'source_id',
                'is_published',
            ]),
        ];
    }

    public function actionUnpublish(int $id): array
    {
        $entry = CatalogEntry::findOne($id);

        if ($entry === null) {
            throw new \yii\web\NotFoundHttpException('Запись не найдена');
        }

        $entry->is_published = false;

        $saved = $entry->save();

        if ($saved === false) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте поля формы.',
                    'fields' => $entry->getErrors(),
                ],
            ];
        }

        return [
            'data' => $entry->getAttributes([
                'id',
                'type',
                'slug',
                'title',
                'body',
                'rule_profile_id',
                'source_id',
                'is_published',
            ]),
        ];
    }

    public function actionTypes(): array
    {
        $types = CatalogEntryType::find()
            ->select(['code', 'title'])
            ->orderBy(['code' => SORT_ASC])
            ->asArray()
            ->all();

        return ['data' => $types];
    }
}
