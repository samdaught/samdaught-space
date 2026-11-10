<?php

namespace app\modules\admin\controllers;

use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

use app\models\users\User;
use app\services\users\TemporaryPasswordIssuer;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class UserController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['reset-password'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['resetUserPassword'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'reset-password' => ['POST'],
                ],
            ],
        ];
    }

    public function actionResetPassword(int $id): Response
    {
        $user = User::findOne($id);

        if ($user === null) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $issuer = new TemporaryPasswordIssuer();
        $tempPassword = $issuer->issue($user);

        $response = $this->asJson(['temporaryPassword' => $tempPassword]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
