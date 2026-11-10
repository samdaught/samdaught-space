<?php

namespace app\controllers\catalog;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

use app\controllers\ApiController;
use app\models\catalog\ContentSource;

class ContentSourceApiController extends ApiController
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
                    'options' => ['GET'],
                    'create' => ['POST'],
                ]
            ]
        ];
    }

    public function actionIndex(): array
    {
        $sources = ContentSource::find()->select(['id', 'code', 'title', 'url', 'rights_note', 'attribution_text',])->orderBy(['id' => SORT_ASC])->limit(20)->asArray()->all();

        return ['data' => $sources];
    }

    public function actionCreate(): array
    {
        $data = Yii::$app->request->getBodyParams();

        if (!is_array($data)) {
            throw new \yii\web\BadRequestHttpException('Запрос не соответствует ожидаемой структуре');
        }

        $source = new ContentSource();
        $source->load($data, '');

        $saved = $source->save();

        if ($saved === false) {
            Yii::$app->response->setStatusCode(422);
            return [
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Проверьте поля формы.',
                    'fields' => $source->getErrors(),
                ],
            ];
        }

        Yii::$app->response->setStatusCode(201);

        return [
            'data' => $source->getAttributes([
                'id',
                'code',
                'title',
                'url',
                'rights_note',
                'attribution_text',
            ]),
        ];
    }

    public function actionOptions(): array
    {
        $options = ContentSource::find()->select(['id', 'title'])->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();

        return ['data' => $options];
    }
}
