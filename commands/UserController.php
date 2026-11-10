<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

use app\models\users\User;

class UserController extends Controller
{
    public function actionCreateAdmin(): int
    {
        $username = getenv('INITIAL_ADMIN_USERNAME');

        if ($username === false || trim($username) === '') {
            $this->stderr("INITIAL_ADMIN_USERNAME не задан\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $username = trim($username);

        if (User::findByUsername($username)) {
            $this->stderr("User already exist\n");
            return ExitCode::UNAVAILABLE;
        }

        $temporaryPassword = Yii::$app->security->generateRandomString(20);

        $user = new User();
        $user->username = $username;
        $user->setPassword($temporaryPassword);
        $user->must_change_password = true;

        $adminRole = Yii::$app->authManager->getRole('admin');

        if ($adminRole === null) {
            $this->stderr("Cant find role\n");
            return ExitCode::CONFIG;
        }

        Yii::$app->db->transaction(function () use ($user, $adminRole): void {
            if (!$user->save()) {
                $reason = implode('; ', $user->getFirstErrors());
                throw new \RuntimeException($reason ?: 'Не удалось сохранить пользователя');
            }

            Yii::$app->authManager->assign($adminRole, $user->id);
        });

        $this->stdout("Temporary password: " . $temporaryPassword . "\n");

        return ExitCode::OK;
    }
}
