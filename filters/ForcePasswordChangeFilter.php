<?php

declare(strict_types=1);

namespace app\filters;

use Yii;
use yii\base\ActionFilter;

class ForcePasswordChangeFilter extends ActionFilter
{

    public function beforeAction($action): bool
    {
        if (Yii::$app->user->isGuest) {
            return true;
        }

        $user = Yii::$app->user->identity;
        if (!$user->must_change_password) {
            return true;
        }

        $allowedActions = [
            'site/force-password-change',
            'site/logout',
            'site/error',
            'users/session-api/index',
            'users/auth-api/logout',
            'users/auth-api/force-password-change',
        ];

        if (in_array($action->uniqueId, $allowedActions, true)) {
            return true;
        }

        if ($action->uniqueId === 'site/app' && Yii::$app->request->pathInfo === 'force-password-change') {
            return true;
        }

        if (str_starts_with(Yii::$app->request->pathInfo, 'api/')) {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            $response->statusCode = 403;
            $response->headers->set('Cache-Control', 'no-store');
            $response->data = ['error' => ['code' => 'PASSWORD_CHANGE_REQUIRED', 'message' => 'Сначала смените временный пароль.']];
            return false;
        }

        Yii::$app->response->redirect(
            \yii\helpers\Url::to(['/force-password-change'])
        );

        return false;
    }
}
