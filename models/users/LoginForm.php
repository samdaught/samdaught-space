<?php

declare(strict_types=1);

namespace app\models\users;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public $username = '';
    public $password = '';
    public $rememberMe = true;
    private User|null $_user = null;
    private bool $_userLoaded = false;

    public function attributeLabels(): array
    {
        return ['username' => 'Логин', 'password' => 'Пароль', 'rememberMe' => 'Запомнить меня'];
    }

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['username', 'string', 'max' => 100],
            ['password', 'string'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }
    public function validatePassword(string $attribute, array|null $params): void
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !password_verify($this->password, $user->password_hash)) {
                $this->addError($attribute, 'Неверный логин или пароль.');
            }
        }
    }
    public function login(): bool
    {
        if ($this->validate()) {

            $user = $this->getUser();

            if ($user === null) {
                return false;
            }

            $duration = $user->must_change_password
                ? 0
                : ($this->rememberMe ? 60 * 60 * 24 * 30 : 0);
            return Yii::$app->user->login($user, $duration);
        }

        return false;
    }

    public function getUser(): User|null
    {
        if (!$this->_userLoaded) {
            $this->_user = User::findByUsername($this->username);
            $this->_userLoaded = true;
        }

        return $this->_user;
    }
}
