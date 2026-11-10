<?php

declare(strict_types=1);

namespace app\services\news;

use Yii;
use app\models\news\News;
use yii\web\{BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

class NewsManager
{

    public function listing(int $page, int $perPage, bool $publicOnly): array
    {
        $query = News::find();
        if ($publicOnly) $query->where(['is_published' => true]);
        $total = (int) (clone $query)->count();
        $pageCount = max(1, (int) ceil($total / $perPage));
        if ($page > $pageCount) throw new NotFoundHttpException('Страница новостей не найдена.');
        $query->orderBy($publicOnly ? ['published_at' => SORT_DESC, 'id' => SORT_DESC] : ['id' => SORT_DESC]);
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->all();
        return ['data' => array_map(static fn(News $news) => $publicOnly ? $news->publicData(false) : $news->adminData(), $rows),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'page_count' => $pageCount]];
    }

    public function read(int $id, bool $publicOnly): array
    {
        $query = News::find()->where('[[id]] = CAST(:id AS BIGINT)', [':id' => $id]);
        if ($publicOnly) $query->andWhere(['is_published' => true]);
        $news = $query->one();
        if ($news === null) throw new NotFoundHttpException('Новость не найдена.');
        return $publicOnly ? $news->publicData(true) : $news->adminData();
    }

    public function save(array $data, int $authorId, ?int $id): array
    {
        $keys = $id === null ? ['title', 'summary', 'body'] : ['title', 'summary', 'body', 'revision'];
        $this->assertKeys($data, $keys);
        if ($id !== null) $this->assertRevisionType($data['revision']);
        return Yii::$app->db->transaction(function () use ($data, $authorId, $id): array {
            $news = $id === null ? new News() : $this->lock($id, $data['revision']);
            $now = gmdate('Y-m-d H:i:s');
            if ($id === null) {
                $news->author_id = $authorId; $news->created_at = $now;
                $news->is_published = false; $news->revision = 1;
            } else $this->advanceRevision($news);
            foreach (['title', 'summary', 'body'] as $field) $news->$field = $data[$field];
            $news->updated_at = $now;
            $this->persist($news);
            return $news->adminData();
        });
    }

    public function publish(int $id, array $data, bool $published): array
    {
        $this->assertKeys($data, ['revision']); $this->assertRevisionType($data['revision']);
        return Yii::$app->db->transaction(function () use ($id, $data, $published): array {
            $news = $this->lock($id, $data['revision']);
            if ($news->is_published === $published) return $news->adminData();
            $news->is_published = $published;
            if ($published && $news->published_at === null) $news->published_at = gmdate('Y-m-d H:i:s');
            $news->updated_at = gmdate('Y-m-d H:i:s');
            $this->advanceRevision($news);
            $this->persist($news);
            return $news->adminData();
        });
    }

    public function delete(int $id, array $data): void
    {
        $this->assertKeys($data, ['revision']); $this->assertRevisionType($data['revision']);
        Yii::$app->db->transaction(function () use ($id, $data): void {
            $news = $this->lock($id, $data['revision']);
            if ($news->is_published) throw new ConflictHttpException('Сначала снимите новость с публикации.');
            if ($news->delete() !== 1) throw new \RuntimeException('Не удалось удалить новость.');
        });
    }

    private function lock(int $id, int $revision): News
    {
        $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%site_news}} WHERE [[id]] = CAST(:id AS BIGINT) FOR UPDATE', [':id' => $id])->queryScalar();
        if ($found === false) throw new NotFoundHttpException('Новость не найдена.');
        $news = News::findOne($found);
        if ($news->revision !== $revision) throw new ConflictHttpException('Новость уже изменена. Загрузите актуальные данные перед сохранением.');
        return $news;
    }

    private function persist(News $news): void
    {
        if (!$news->validate()) throw new NewsValidationException($news->getErrors());
        if (!$news->save(false)) throw new \RuntimeException('Не удалось сохранить новость.');
    }

    private function advanceRevision(News $news): void
    {
        if ($news->revision >= 2147483647) throw new ConflictHttpException('Достигнут предел версии новости.');
        $news->revision++;
    }

    private function assertKeys(array $data, array $keys): void
    {
        if (count($data) !== count($keys) || array_diff($keys, array_keys($data))) throw new BadRequestHttpException('Передайте только поля ' . implode(', ', $keys) . '.');
    }

    private function assertRevisionType($value): void
    {
        if (!is_int($value) || $value < 1 || $value > 2147483647) throw new BadRequestHttpException('revision должна быть положительным целым числом.');
    }
}
