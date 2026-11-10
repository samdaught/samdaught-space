<?php

declare(strict_types=1);

namespace app\controllers\catalog;

use Yii;
use app\controllers\ApiController;
use app\models\catalog\CatalogEntry;
use app\models\catalog\BackgroundAbilityOptionsForm;
use app\models\catalog\BackgroundSkillsForm;
use app\models\rules\AbilityDefinition;
use app\models\rules\BackgroundAbilityOption;
use app\models\rules\BackgroundSkillProficiency;
use app\models\rules\BackgroundFeat;
use app\models\rules\BackgroundToolProficiency;
use app\models\rules\ToolGroup;
use app\models\rules\BackgroundStartingEquipment;
use app\models\rules\RuleProfile;
use app\services\rules\BackgroundAbilityOptionsWriter;
use app\services\rules\SkillOptions;
use app\services\rules\BackgroundFeatOptions;
use app\services\rules\BackgroundToolOptions;
use app\services\rules\StartingEquipmentItemOptions;
use app\services\rules\BackgroundStartingEquipmentReader;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class BackgroundAbilityApiController extends ApiController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['manageCatalog']],
                ],
                'denyCallback' => static function ($rule, $action): void {
                    if (Yii::$app->user->isGuest) {
                        throw new UnauthorizedHttpException('Войдите, чтобы управлять записями архива.');
                    }
                    throw new ForbiddenHttpException('Недостаточно прав для управления записями архива.');
                },
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'view' => ['GET'],
                    'options' => ['GET'],
                    'update' => ['PATCH'],
                ],
            ],
        ];
    }

    public function actionView(int $id): array
    {
        return ['data' => $this->getOptions($this->findBackground($id))];
    }

    public function actionOptions(): array
    {
        $input = Yii::$app->request->get('origin_profile_id');
        $profile = null;
        if ($input !== null) {
            $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new BadRequestHttpException('Профиль происхождения должен быть положительным целым числом.');
            $profile = RuleProfile::find()->where('[[id]] = CAST(:id AS BIGINT)', [':id' => $id])->one();
            if ($profile === null) throw new NotFoundHttpException('Профиль происхождения не найден.');
        }
        return ['data' => $this->getOptions(null, $profile)];
    }

    public function actionUpdate(int $id): array
    {
        $background = $this->findBackground($id);
        $data = Yii::$app->request->getBodyParams();

        if (!is_array($data)) {
            throw new BadRequestHttpException('Запрос не соответствует ожидаемой структуре.');
        }

        $form = new BackgroundAbilityOptionsForm();
        $form->ability_codes = $data['ability_codes'] ?? null;

        if (!$form->validate()) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте доступные характеристики предыстории.',
                    'fields' => $form->getErrors(),
                ],
            ];
        }

        $writer = new BackgroundAbilityOptionsWriter();
        $writer->save($background, $form->ability_codes);

        return ['data' => $this->getOptions($background)];
    }

    private function findBackground(int $id): CatalogEntry
    {
        $background = CatalogEntry::findOne(['id' => $id, 'type' => 'background']);

        if ($background === null) {
            throw new NotFoundHttpException('Предыстория не найдена.');
        }

        return $background;
    }

    private function getOptions(?CatalogEntry $background, ?RuleProfile $profile = null): array
    {
        $codes = $background === null ? [] : BackgroundAbilityOption::find()
            ->select('ability_code')
            ->where(['background_entry_id' => $background->id])
            ->orderBy(['ability_code' => SORT_ASC])
            ->column();

        if ($background !== null) $profile = RuleProfile::findOne($background->origin_profile_id);
        $skillIds = $background === null ? [] : BackgroundSkillProficiency::find()->select('skill_entry_id')
            ->where(['background_entry_id' => $background->id])->orderBy(['skill_entry_id' => SORT_ASC])->column();
        $featLink = $background === null ? null : BackgroundFeat::findOne(['background_entry_id' => $background->id]);
        $toolRule = $background === null ? null : BackgroundToolProficiency::findOne($background->id);

        return [
            'origin_profile_id' => $profile === null ? null : $profile->id,
            'required_count' => BackgroundAbilityOptionsForm::REQUIRED_COUNT,
            'ability_options' => AbilityDefinition::find()
                ->select(['code', 'title'])
                ->orderBy(['code' => SORT_ASC])
                ->asArray()
                ->all(),
            'ability_codes' => $codes,
            'configured' => count($codes) === BackgroundAbilityOptionsForm::REQUIRED_COUNT,
            'required_skill_count' => BackgroundSkillsForm::REQUIRED_COUNT,
            'skill_options' => (new SkillOptions())->get($profile),
            'skill_entry_ids' => $skillIds,
            'skills_configured' => count($skillIds) === BackgroundSkillsForm::REQUIRED_COUNT,
            'feat_options' => (new BackgroundFeatOptions())->get($profile),
            'feat_entry_id' => $featLink === null ? null : $featLink->feat_entry_id,
            'feat_configured' => $featLink !== null,
            'tool_options' => (new BackgroundToolOptions())->get($profile),
            'tool_groups' => ToolGroup::find()->select(['code', 'title'])->orderBy('title')->asArray()->all(),
            'background_tool' => $toolRule === null ? null : $toolRule->getAttributes(['tool_entry_id', 'tool_group_code']),
            'tool_configured' => $toolRule !== null,
            'equipment_item_options' => (new StartingEquipmentItemOptions())->get($profile),
            'starting_equipment' => (new BackgroundStartingEquipmentReader())->get($background),
            'equipment_max_items' => BackgroundStartingEquipment::MAX_ITEMS,
            'equipment_integer_max' => BackgroundStartingEquipment::MAX_INTEGER,
            'starting_equipment_defaults' => [
                'package_money_cp' => 0,
                'alternative_money_cp' => BackgroundStartingEquipment::DEFAULT_ALTERNATIVE_MONEY_CP,
                'include_background_tool' => false,
                'items' => [],
            ],
        ];
    }
}
