<?php

namespace app\modules\admin;

use yii\filters\AccessControl;

final class Module extends \yii\base\Module
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['accessAdmin'],
                    ],
                ],
            ]
        ];
    }
}
