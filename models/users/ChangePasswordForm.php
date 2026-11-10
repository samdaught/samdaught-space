<?php

declare(strict_types=1);

namespace app\models\users;

use yii\base\Model;

class ChangePasswordForm extends Model
{
    public $currentPassword = '';
    public $newPassword = '';
    public $newPasswordRepeat = '';

    public function attributeLabels(): array
    {
        return ['currentPassword' => 'Текущий пароль', 'newPassword' => 'Новый пароль', 'newPasswordRepeat' => 'Повторите новый пароль'];
    }

    public function __construct(private readonly User $user, array $config = [])
    {
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['currentPassword', 'newPassword', 'newPasswordRepeat'], 'required'],
            ['currentPassword', 'string'],
            ['currentPassword', 'validateCurrentPassword'],
            ['newPassword', 'string', 'min' => 8, 'max' => 64],
            ['newPassword', 'validateNewPassword'],
            ['newPasswordRepeat', 'string'],
            ['newPasswordRepeat', 'compare', 'compareAttribute' => 'newPassword', 'operator' => '==='],
        ];
    }

    public function validateCurrentPassword(string $attribute, array|null $params): void
    {
        if (!password_verify($this->currentPassword, $this->user->password_hash)) {
            $this->addError($attribute, 'Текущий пароль неверен.');
        }
    }


    public function validateNewPassword(string $attribute, array|null $params): void
    {
        if ($this->hasErrors('currentPassword')) {
            return;
        }

        if ($this->currentPassword === $this->newPassword) {
            $this->addError($attribute, 'Новый пароль не должен совпадать с предыдущим');
        }
    }
}
