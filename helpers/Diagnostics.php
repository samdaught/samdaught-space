<?php

declare(strict_types=1);

namespace app\helpers;

use Yii;

class Diagnostics
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    public static function prefix(): string
    {
        $request = Yii::$app->request;
        $route = Yii::$app->requestedRoute ?: '-';
        $method = $request instanceof \yii\web\Request ? $request->method : 'CLI';

        return '[' . self::requestId() . '][' . $method . '][' . $route . ']';
    }
}
