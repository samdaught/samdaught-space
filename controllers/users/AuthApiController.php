<?php

declare(strict_types=1);

namespace app\controllers\users;

use Yii;
use app\controllers\ApiController;
use app\services\users\{Authentication, AuthenticationValidationException};
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{BadRequestHttpException, UnauthorizedHttpException, ForbiddenHttpException};

class AuthApiController extends ApiController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'actions' => ['login', 'signup'], 'roles' => ['?']],
                    ['allow' => true, 'actions' => ['logout', 'change-password', 'force-password-change'], 'roles' => ['@']],
                ],
                'denyCallback' => static function (): void {
                    if (Yii::$app->user->isGuest) throw new UnauthorizedHttpException('Войдите в аккаунт.');
                    throw new ForbiddenHttpException('Вы уже вошли в аккаунт.');
                },
            ],
            'verbs' => ['class' => VerbFilter::class, 'actions' => array_fill_keys(['login', 'signup', 'logout', 'change-password', 'force-password-change'], ['POST'])],
        ];
    }

    public function actionLogin(): array
    {
        return $this->respond(fn() => (new Authentication())->login($this->input(['username', 'password', 'rememberMe'])));
    }
    public function actionSignup(): array
    {
        return $this->respond(fn() => (new Authentication())->signup($this->input(['username', 'password', 'passwordRepeat'])), 201);
    }
    public function actionLogout(): array
    {
        return $this->respond(function (): array {
            $this->input([]);
            return (new Authentication())->logout();
        });
    }
    public function actionChangePassword(): array
    {
        return $this->respond(fn() => (new Authentication())->changePassword($this->input(['currentPassword', 'newPassword', 'newPasswordRepeat']), false));
    }
    public function actionForcePasswordChange(): array
    {
        return $this->respond(fn() => (new Authentication())->changePassword($this->input(['newPassword', 'newPasswordRepeat']), true));
    }

    private function input(array $fields): array
    {
        $data = Yii::$app->request->getBodyParams();
        if (!is_array($data) || count($data) !== count($fields) || array_diff($fields, array_keys($data))) {
            throw new BadRequestHttpException('Некорректные поля формы.');
        }
        foreach ($fields as $field) {
            if ($field === 'rememberMe' ? !is_bool($data[$field]) : !is_string($data[$field])) {
                throw new AuthenticationValidationException([$field => ['Передано некорректное значение поля.']]);
            }
        }
        return $data;
    }

    private function respond(callable $operation, int $status = 200): array
    {
        try {
            $data = $operation();
            Yii::$app->response->statusCode = $status;
            return ['data' => $data];
        } catch (AuthenticationValidationException $error) {
            Yii::$app->response->statusCode = 422;
            return ['error' => ['code' => 'VALIDATION_FAILED', 'message' => $error->getMessage(), 'fields' => $error->fields]];
        }
    }
}
