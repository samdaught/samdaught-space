<?php

declare(strict_types=1);

namespace app\controllers\catalog;

use Yii;
use app\controllers\ApiController;
use app\models\catalog\{CatalogEntry, CatalogEntryType, ContentSource};
use app\models\rules\RuleProfile;
use yii\filters\VerbFilter;
use yii\web\{BadRequestHttpException, NotFoundHttpException};

class ArchiveApiController extends ApiController
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => [
            'index' => ['GET'], 'view' => ['GET'], 'types' => ['GET'],
        ]]];
    }

    public function actionTypes(): array
    {
        return ['data' => CatalogEntryType::find()->select(['code', 'title'])->orderBy(['title' => SORT_ASC])->asArray()->all()];
    }

    public function actionIndex(): array
    {
        $page = $this->integerParameter('page', 1, 1000000);
        $perPage = $this->integerParameter('per_page', 20, 100);
        $type = Yii::$app->request->get('type', '');
        $search = Yii::$app->request->get('q', '');
        if (!is_string($type) || !is_string($search) || mb_strlen($search) > 100) {
            throw new BadRequestHttpException('Категория и поиск должны быть строками; поиск — до 100 символов.');
        }
        if ($type !== '' && !CatalogEntryType::find()->where(['code' => $type])->exists()) {
            throw new BadRequestHttpException('Неизвестная категория архива.');
        }
        $query = $this->publicQuery();
        if ($type !== '') $query->andWhere(['entry.type' => $type]);
        if (trim($search) !== '') $query->andWhere(['or', ['ilike', 'entry.title', trim($search)], ['ilike', 'entry.body', trim($search)]]);
        $total = (int) (clone $query)->count();
        $pageCount = max(1, (int) ceil($total / $perPage));
        if ($page > $pageCount) throw new NotFoundHttpException('Страница архива не найдена.');
        $rows = $query->orderBy(['entry.title' => SORT_ASC, 'entry.id' => SORT_ASC])
            ->limit($perPage)->offset(($page - 1) * $perPage)->asArray()->all();
        return ['data' => array_map(fn(array $row): array => $this->publicJson($row, false), $rows),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'page_count' => $pageCount]];
    }

    public function actionView(int $id): array
    {
        $row = $this->publicQuery()->andWhere(['entry.id' => $id])->asArray()->one();
        if ($row === null) throw new NotFoundHttpException('Опубликованная запись не найдена.');
        return ['data' => $this->publicJson($row, true)];
    }

    private function publicQuery(): \yii\db\ActiveQuery
    {
        return CatalogEntry::find()->alias('entry')
            ->innerJoin(['category' => CatalogEntryType::tableName()], '[[category.code]] = [[entry.type]]')
            ->innerJoin(['profile' => RuleProfile::tableName()], '[[profile.id]] = [[entry.origin_profile_id]]')
            ->leftJoin(['source' => ContentSource::tableName()], '[[source.id]] = [[entry.source_id]]')
            ->where(['entry.is_published' => true])
            ->select(['entry.id', 'entry.slug', 'entry.title', 'entry.type', 'entry.body',
                'type_title' => 'category.title', 'profile_title' => 'profile.title',
                'source_title' => 'source.title', 'source_url' => 'source.url', 'attribution_text' => 'source.attribution_text']);
    }

    private function publicJson(array $row, bool $details): array
    {
        $data = ['id' => (int) $row['id'], 'slug' => $row['slug'], 'title' => $row['title'],
            'type' => $row['type'], 'type_title' => $row['type_title'], 'profile_title' => $row['profile_title'],
            'source_title' => $row['source_title'], 'excerpt' => mb_strimwidth((string) $row['body'], 0, 240, '…')];
        if ($details) {
            $data['body'] = $row['body'];
            $data['source_url'] = $row['source_url'];
            $data['attribution_text'] = $row['attribution_text'];
        }
        return $data;
    }

    private function integerParameter(string $name, int $default, int $max): int
    {
        $value = Yii::$app->request->get($name, (string) $default);
        if (!is_scalar($value)) throw new BadRequestHttpException('Некорректный параметр ' . $name);
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
        if ($number === false) throw new BadRequestHttpException('Некорректный параметр ' . $name);
        return $number;
    }
}
