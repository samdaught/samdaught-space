<?php

declare(strict_types=1);

namespace app\services\users;

use Yii;
use app\models\users\User;

class TemporaryPasswordIssuer
{
    public function issue(User $user): string
    {
        if ($user->getIsNewRecord()) {
            throw new \InvalidArgumentException('Нужен существующий пользователь');
        }

        $tempPassword = Yii::$app->security->generateRandomString(8);

        $user->setPassword($tempPassword);
        $user->must_change_password = true;

        if (!$user->save()) {
            $reason = implode('; ', $user->getFirstErrors());
            throw new \RuntimeException($reason ?: 'Не удалось сохранить временный пароль для пользователя');
        }

        return $tempPassword;
    }
}
