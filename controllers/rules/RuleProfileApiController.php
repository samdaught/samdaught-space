<?php

namespace app\controllers\rules;

use app\controllers\ApiController;
use app\models\rules\RuleProfile;
use yii\filters\VerbFilter;

class RuleProfileApiController extends ApiController
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                ],
            ],
        ];
    }

    public function actionIndex(): array
    {
        $profiles = RuleProfile::find()
            ->select(['id', 'code', 'title'])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        return ['data' => $profiles];
    }
}
