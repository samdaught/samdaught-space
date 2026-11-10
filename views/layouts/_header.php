<?php
declare(strict_types=1);
use yii\helpers\Html;
?>
<header class="server-header">
    <?= Html::a('<span aria-hidden="true">☾</span> Samdaught Space', ['/site/app'], ['class' => 'server-brand']) ?>
    <nav aria-label="Главная навигация">
        <?= Html::a('Главная', ['/site/app']) ?>
        <?= Html::a('Архив', Yii::getAlias('@web') . '/archive') ?>
        <?php if (Yii::$app->user->isGuest): ?>
            <?= Html::a('Войти', ['/login']) ?>
        <?php else: ?>
            <?= Html::a('Персонажи', Yii::getAlias('@web') . '/cabinet') ?>
            <?= Html::beginForm(['/site/logout'], 'post') ?>
            <?= Html::submitButton('Выйти') ?>
            <?= Html::endForm() ?>
        <?php endif ?>
    </nav>
</header>
