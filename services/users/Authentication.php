<?php

declare(strict_types=1);

namespace app\services\users;

use Yii;
use app\models\users\{User, LoginForm, SignupForm, ChangePasswordForm, ForcedPasswordChangeForm};
use yii\web\{UnauthorizedHttpException, TooManyRequestsHttpException, ConflictHttpException};

class Authentication
{
    public function login(array $data): array
    {
        $model = new LoginForm();
        $model->setAttributes($data, false);
        if (!$model->validate(['username'])) throw new AuthenticationValidationException($model->errors);

        $limiter = new LoginAttemptLimiter(Yii::$app->db);
        if (!$limiter->consume('ip', $this->ip()) || !$limiter->consume('username', $model->username)) {
            throw new TooManyRequestsHttpException('Слишком много попыток входа. Попробуйте позже.');
        }
        if (!$model->login()) throw new AuthenticationValidationException($model->errors);
        $user = $model->getUser();
        if ($user === null) throw new \LogicException('Не найден вошедший пользователь.');
        Yii::$app->session->remove('forced_password_change');
        if ($user->must_change_password) {
            Yii::$app->session->set('forced_password_change', ['userId' => $user->id, 'authenticatedAt' => time()]);
        }
        return SessionState::current(true);
    }

    public function signup(array $data): array
    {
        $limiter = new LoginAttemptLimiter(Yii::$app->db);
        if (!$limiter->consume('signup_ip', $this->ip())) {
            throw new TooManyRequestsHttpException('Слишком много попыток регистрации. Попробуйте позже.');
        }
        $model = new SignupForm();
        $model->setAttributes($data, false);
        if ($model->signup() === null) throw new AuthenticationValidationException($model->errors);
        return ['registered' => true];
    }

    public function logout(): array
    {
        Yii::$app->session->remove('forced_password_change');
        Yii::$app->user->logout();
        return SessionState::current(true);
    }

    public function changePassword(array $data, bool $forced): array
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) throw new UnauthorizedHttpException('Войдите в аккаунт.');
        if ($forced) $this->ensureForcedLoginFresh($identity);
        else {
            $limiter = new LoginAttemptLimiter(Yii::$app->db);
            if (!$limiter->consume('change_user', (string) $identity->id)) {
                throw new TooManyRequestsHttpException('Слишком много попыток смены пароля. Попробуйте позже.');
            }
        }

        Yii::$app->db->transaction(function () use ($identity, $data, $forced): void {
            $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%user}} WHERE [[id]] = :id FOR UPDATE', [':id' => $identity->id])->queryScalar();
            $user = $found === false ? null : User::findOne($found);
            if ($user === null || !hash_equals($user->auth_key, $identity->auth_key)) {
                $this->logout();
                throw new UnauthorizedHttpException('Сессия устарела. Войдите снова.');
            }
            if ((bool) $user->must_change_password !== $forced) {
                throw new ConflictHttpException('Состояние аккаунта изменилось. Обновите страницу.');
            }
            $model = $forced ? new ForcedPasswordChangeForm() : new ChangePasswordForm($user);
            $model->setAttributes($data, false);
            if (!$model->validate()) throw new AuthenticationValidationException($model->errors);
            if ($forced && password_verify($model->newPassword, $user->password_hash)) {
                throw new AuthenticationValidationException(['newPassword' => ['Новый пароль должен отличаться от временного.']]);
            }
            $user->setPassword($model->newPassword);
            $user->must_change_password = false;
            if (!$user->save()) throw new \RuntimeException('Не удалось сохранить новый пароль.');
        });
        return $this->logout();
    }

    public function ensureForcedLoginFresh(User $user): void
    {
        $marker = Yii::$app->session->get('forced_password_change');
        $time = is_array($marker) ? ($marker['authenticatedAt'] ?? null) : null;
        if (!$user->must_change_password || !is_int($time) || $time > time() || time() - $time > 600
            || ($marker['userId'] ?? null) !== $user->id) {
            $this->logout();
            throw new UnauthorizedHttpException('Время смены временного пароля истекло. Войдите снова.');
        }
    }

    private function ip(): string
    {
        $ip = Yii::$app->request->getUserIP();
        if ($ip === null) throw new \RuntimeException('Не удалось определить IP клиента.');
        return $ip;
    }
}
