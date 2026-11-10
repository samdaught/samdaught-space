<?php

declare(strict_types=1);

use yii\helpers\Html;
use app\assets\ThemeAsset;

ThemeAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(
    ['charset' => Yii::$app->charset],
    'charset',
);
$this->registerMetaTag(
    [
        'name' => 'viewport',
        'content' => 'width=device-width, initial-scale=1',
    ],
);
if (!empty($this->params['meta_description'])) {
    $this->registerMetaTag(
        [
            'name' => 'description',
            'content' => $this->params['meta_description'],
        ],
    );
}
if (!empty($this->params['meta_keywords'])) {
    $this->registerMetaTag(
        [
            'name' => 'keywords',
            'content' => $this->params['meta_keywords'],
        ],
    );
}
$this->registerLinkTag(
    [
        'rel' => 'icon',
        'type' => 'image/svg+xml',
        'href' => Yii::getAlias('@web/favicon.svg'),
    ],
);

?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">

<head>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?></title>
</head>

<body>
    <?php $this->beginBody() ?>
    <?= $content ?>
    <?php $this->endBody() ?>
</body>

</html>
<?php $this->endPage() ?>
