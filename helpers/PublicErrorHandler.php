<?php

declare(strict_types=1);

namespace app\helpers;

use yii\web\HttpException;

class PublicErrorHandler extends \yii\web\ErrorHandler
{
    protected function renderException($exception)
    {

        parent::renderException($exception instanceof HttpException ? $exception
            : new HttpException(500, 'Не удалось выполнить запрос. Попробуйте позже.'));
    }

    protected function convertExceptionToArray($exception)
    {
        $status = $exception instanceof HttpException ? $exception->statusCode : 500;
        return ['name' => 'Ошибка запроса', 'status' => $status, 'code' => 0,
            'message' => $status >= 500 ? 'Не удалось выполнить запрос. Попробуйте позже.' : $exception->getMessage(),
            'request_id' => Diagnostics::requestId()];
    }
}
