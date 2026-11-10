<?php

declare(strict_types=1);

namespace app\controllers\users;

use Yii;

use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\services\users\SessionState;

class SessionApiController extends Controller
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET']]]];
    }

    public function actionIndex(): Response
    {
        $identity = Yii::$app->user->identity;
        if ($identity instanceof \app\models\users\User && $identity->must_change_password) {
            try { (new \app\services\users\Authentication())->ensureForcedLoginFresh($identity); }
            catch (\yii\web\UnauthorizedHttpException) {   }
        }
        $response = $this->asJson(SessionState::current());
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
