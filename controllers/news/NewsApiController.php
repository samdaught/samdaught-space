<?php

declare(strict_types=1);

namespace app\controllers\news;

use Yii;
use app\controllers\ApiController;
use app\services\news\NewsManager;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;

class NewsApiController extends ApiController
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET'], 'view' => ['GET']]]];
    }

    public function actionIndex(): array
    {
        return (new NewsManager())->listing($this->integerQuery('page', 1, PHP_INT_MAX), $this->integerQuery('per_page', 5, 100), true);
    }

    public function actionView(int $id): array { return ['data' => (new NewsManager())->read($id, true)]; }

    protected function integerQuery(string $name, int $default, int $max): int
    {
        $value = filter_var(Yii::$app->request->get($name, $default), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
        if ($value === false) throw new BadRequestHttpException('Некорректный параметр ' . $name . '.');
        return $value;
    }
}
