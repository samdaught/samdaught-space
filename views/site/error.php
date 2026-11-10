<?php
declare(strict_types=1);

use app\helpers\FrontendAsset;
use yii\helpers\Html;
use yii\web\HttpException;
$statusCode = $exception instanceof HttpException ? $exception->statusCode : 500;
$this->title = $statusCode === 404 ? 'Страница не найдена' : 'Не удалось открыть страницу';
?>
<section class="server-error">
    <div>
        <span class="error-code"><?= Html::encode($statusCode) ?></span>
        <h1><?= $statusCode === 404 ? 'Извини, магия не сработала.' : 'Портал пока не открывается.' ?></h1>
        <p><?= $statusCode === 404 ? 'Этой страницы нет. Возможно, адрес изменился или в него закралась опечатка.' : ($statusCode >= 500 ? 'Попробуйте ещё раз чуть позже. Если ошибка повторяется, сообщите администратору код запроса.' : Html::encode($message)) ?></p>
        <p>Код запроса: <?= Html::encode(\app\helpers\Diagnostics::requestId()) ?></p>
        <?= Html::a('На главную', ['/site/app'], ['class' => 'button button-primary']) ?>
    </div>
    <?= Html::img(FrontendAsset::url('404_1.webp'), ['alt' => 'Ведьмочка разбирается с неудачным заклинанием', 'width' => 1536, 'height' => 1024]) ?>
</section>
