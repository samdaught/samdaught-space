<?php

declare(strict_types=1);

namespace app\controllers\news;

use Yii;
use app\services\news\{NewsManager, NewsValidationException};
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{BadRequestHttpException, ForbiddenHttpException, UnauthorizedHttpException};

class NewsAdminApiController extends NewsApiController
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['manageNews']]],
                'denyCallback' => static function ($rule, $action): void {
                    if (Yii::$app->user->isGuest) throw new UnauthorizedHttpException('Войдите, чтобы управлять новостями.');
                    throw new ForbiddenHttpException('Недостаточно прав для управления новостями.');
                }],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET'], 'view' => ['GET'], 'create' => ['POST'], 'update' => ['PATCH'], 'publish' => ['POST'], 'unpublish' => ['POST'], 'delete' => ['DELETE']]],
        ];
    }

    public function actionIndex(): array
    {
        return (new NewsManager())->listing($this->integerQuery('page', 1, PHP_INT_MAX), $this->integerQuery('per_page', 20, 100), false);
    }
    public function actionView(int $id): array { return ['data' => (new NewsManager())->read($id, false)]; }
    public function actionCreate(): array { return $this->saveNews(null); }
    public function actionUpdate(int $id): array { return $this->saveNews($id); }
    public function actionPublish(int $id): array { return $this->response(fn() => (new NewsManager())->publish($id, $this->body(), true)); }
    public function actionUnpublish(int $id): array { return $this->response(fn() => (new NewsManager())->publish($id, $this->body(), false)); }
    public function actionDelete(int $id): array { (new NewsManager())->delete($id, $this->body()); return ['data' => ['deleted' => true]]; }

    private function saveNews(?int $id): array
    {
        return $this->response(function () use ($id): array {
            $news = (new NewsManager())->save($this->body(), Yii::$app->user->id, $id);
            Yii::$app->response->statusCode = $id === null ? 201 : 200;
            return $news;
        });
    }
    private function body(): array
    {
        $data = Yii::$app->request->getBodyParams();
        if (!is_array($data)) throw new BadRequestHttpException('Передайте объект новости.');
        return $data;
    }
    private function response(callable $operation): array
    {
        try { return ['data' => $operation()]; }
        catch (NewsValidationException $error) {
            Yii::$app->response->statusCode = 422;
            return ['error' => ['code' => 'VALIDATION_FAILED', 'message' => $error->getMessage(), 'fields' => $error->fields]];
        }
    }
}
