<?php

declare(strict_types=1);

namespace app\helpers;

use Yii;

class FrontendAsset
{
    public static function url(string $name): string
    {
        $base = YII_ENV_DEV ? 'http://localhost:5173/' : Yii::getAlias('@web') . '/vue-dist/';
        return $base . $name;
    }
}
