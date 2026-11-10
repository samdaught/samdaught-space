<?php

declare(strict_types=1);

namespace app\models\users;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{

    public function rules()
    {
        return [
            ['username', 'required'],
            ['username', 'unique'],
            ['username', 'string', 'max' => 100],
        ];
    }

    public static function findIdentity($id): static|null
    {
        return static::findOne($id);
    }
    public static function findIdentityByAccessToken($access_token, $type = null): static|null
    {
        return null;
    }

    public static function findByUsername(string $username): static|null
    {
        return static::findOne(['username' => $username]);
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getAuthKey(): string|null
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    public function setPassword(string $plainPassword): void
    {
        $this->password_hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }
}
