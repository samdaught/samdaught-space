<?php

declare(strict_types=1);

namespace app\models\users;

use yii\base\Model;

class ForcedPasswordChangeForm extends Model
{
    public $newPassword = '';
    public $newPasswordRepeat = '';

    public function attributeLabels(): array
    {
        return ['newPassword' => 'Новый пароль', 'newPasswordRepeat' => 'Повторите новый пароль'];
    }

    public function rules(): array
    {
        return [
            [['newPassword', 'newPasswordRepeat'], 'required'],
            ['newPassword', 'string', 'min' => 8, 'max' => 64],
            ['newPasswordRepeat', 'string'],
            ['newPasswordRepeat', 'compare', 'compareAttribute' => 'newPassword', 'operator' => '==='],
        ];
    }
}
