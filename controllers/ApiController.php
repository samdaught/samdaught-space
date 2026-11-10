<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class ApiController extends Controller
{
    public function init(): void
    {
        parent::init();

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->headers->set('Cache-Control', 'no-store');
    }
}
