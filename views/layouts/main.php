<?php
declare(strict_types=1);

use app\widgets\Alert;
use yii\helpers\Html;
$this->render('_head');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?> · Samdaught Space</title>
</head>
<body class="server-shell">
<?php $this->beginBody() ?>
<a class="skip-link" href="#main">К содержимому</a>
<?= $this->render('_header') ?>
<main id="main" class="server-content" tabindex="-1">
    <?= Alert::widget() ?>
    <?= $content ?>
</main>
<?= $this->render('_footer') ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
