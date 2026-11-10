<?php

declare(strict_types=1);

namespace app\controllers\characters;

use Yii;
use yii\web\UnauthorizedHttpException;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;

use app\controllers\ApiController;
use app\models\characters\Character;
use app\models\rules\RuleProfile;

class CharacterApiController extends ApiController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => static function ($rule, $action): void {
                    throw new UnauthorizedHttpException('Войдите, чтобы работать со своими персонажами.');
                },
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'editor-options' => ['POST'],
                    'create' => ['POST'],
                    'view' => ['GET'],
                    'update' => ['PATCH'],
                    'delete' => ['DELETE'],
                ],
            ],
        ];
    }

    public function actionIndex(): array
    {
        $page = filter_var(
            Yii::$app->request->get('page', '1'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $perPage = filter_var(
            Yii::$app->request->get('per_page', '20'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );

        if ($page === false || $perPage === false) {
            throw new \yii\web\BadRequestHttpException(
                'page должен быть целым числом от 1, per_page — от 1 до 100.'
            );
        }

        $query = Character::find()
            ->alias('character')
            ->innerJoin(
                ['profile' => RuleProfile::tableName()],
                '[[profile.id]] = [[character.rule_profile_id]]'
            )
            ->where(['character.user_id' => Yii::$app->user->id]);

        $total = (int) (clone $query)->count();
        $pageCount = max(1, (int) ceil($total / $perPage));

        if ($page > $pageCount) {
            throw new \yii\web\NotFoundHttpException('Страница списка не найдена.');
        }

        $characters = $query
            ->select([
                'character.id',
                'character.name',
                'character.rule_profile_id',
                'rule_profile_title' => 'profile.title',
                'character.target_level',
                'character.status',
                'character.revision',
            ])
            ->orderBy(['character.id' => SORT_DESC])
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->asArray()
            ->all();

        return [
            'data' => $characters,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'page_count' => $pageCount,
            ],
        ];
    }

    public function actionEditorOptions(): array
    {
        return $this->editorResponse(function (): array {
            $data = Yii::$app->request->getBodyParams();
            if (!is_array($data) || count($data) !== 3 || array_diff(['context', 'creation_choices', 'class_skill_entry_ids'], array_keys($data)) || !is_array($data['context']) || array_diff(array_keys($data['context']), \app\services\characters\CharacterEditor::CONTEXT_FIELDS)) throw new \yii\web\BadRequestHttpException('Передайте контекст и локальные выборы редактора.');
            return (new \app\services\characters\CharacterEditor())->options($data['context'], Yii::$app->user->id, $data['creation_choices'], $data['class_skill_entry_ids']);
        });
    }

    public function actionCreate(): array
    {
        return $this->saveEditor(null);
    }

    public function actionView(int $id): array
    {
        return $this->editorResponse(fn(): array => (new \app\services\characters\CharacterEditor())->read($id, Yii::$app->user->id));
    }

    public function actionUpdate(int $id): array
    {
        return $this->saveEditor($id);
    }

    public function actionDelete(int $id): array
    {
        $data = Yii::$app->request->getBodyParams();
        if (!is_array($data) || array_keys($data) !== ['revision'] || !is_int($data['revision']) || $data['revision'] < 1 || $data['revision'] > 2147483647) throw new \yii\web\BadRequestHttpException('Передайте текущую revision листа.');
        (new \app\services\characters\CharacterEditor())->delete($id, Yii::$app->user->id, $data['revision']);
        return ['data' => ['deleted' => true]];
    }

    private function saveEditor(?int $id): array
    {
        return $this->editorResponse(function () use ($id): array {
            $data = Yii::$app->request->getBodyParams();
            if (!is_array($data)) throw new \yii\web\BadRequestHttpException('Передайте объект редактора персонажа.');
            $state = (new \app\services\characters\CharacterEditor())->save($data, Yii::$app->user->id, $id);
            Yii::$app->response->statusCode = $id === null ? 201 : 200;
            return $state;
        });
    }

    private function editorResponse(callable $operation): array
    {
        try { return ['data' => $operation()]; }
        catch (\app\services\characters\CharacterEditorValidationException $error) {
            Yii::$app->response->statusCode = 422;
            return ['error' => ['code' => 'VALIDATION_FAILED', 'message' => $error->getMessage(), 'fields' => $error->fields]];
        }
    }
}
