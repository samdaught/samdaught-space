<?php

declare(strict_types=1);

namespace app\models\users;

use Yii;
use yii\base\Model;
use yii\db\IntegrityException;

class SignupForm extends Model
{
    public $username = '';
    public $password = '';
    public $passwordRepeat = '';

    public function attributeLabels(): array
    {
        return ['username' => 'Логин', 'password' => 'Пароль', 'passwordRepeat' => 'Повторите пароль'];
    }
    public function rules(): array
    {
        return [
            [['username', 'password', 'passwordRepeat'], 'required'],
            ['username', 'string', 'max' => 100],
            ['username', 'unique', 'targetClass' => User::class, 'targetAttribute' => 'username'],
            ['password', 'string', 'min' => 8, 'max' => 64],
            ['passwordRepeat', 'string',],
            ['passwordRepeat', 'compare', 'compareAttribute' => 'password', 'operator' => '==='],
        ];
    }

    public function signup(): ?User
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User;
        $user->username = $this->username;
        $user->setPassword($this->password);
        $user->must_change_password = false;


        try {
            $created = Yii::$app->db->transaction(function () use ($user): bool {
                if (!$user->save()) {
                    return false;
                }

                $role = Yii::$app->authManager->getRole('user');
                if ($role === null) {
                    throw new \RuntimeException('Роль user не найдена');
                }

                $auth = Yii::$app->authManager;
                $auth->assign($role, $user->id);

                return true;
            });
        } catch (IntegrityException $e) {
            $code = $e->errorInfo[0] ?? null;

            if ($code !== '23505') {
                throw $e;
            }

            $existingUser = User::findByUsername($this->username);

            if ($existingUser === null) {
                throw $e;
            }

            $this->addError('username', 'Это имя уже занято.');
            return null;
        }

        if ($created === false) {
            if (
                $user->hasErrors('username')
                && User::findByUsername($user->username) !== null
            ) {
                $this->addError('username', 'Это имя уже занято.');
                return null;
            }

            throw new \RuntimeException('Не удалось сохранить пользователя');
        }
        return $user;
    }
}
