<?php

if (YII_ENV_DEV) {
    $this->registerJsFile('http://localhost:5173/@vite/client', [
        'type' => 'module',
        'position' => \yii\web\View::POS_HEAD,
    ]);
    $this->registerJsFile('http://localhost:5173/src/main.ts', [
        'type' => 'module',
        'position' => \yii\web\View::POS_HEAD,
    ]);
} else {
    $manifestPath = Yii::getAlias('@webroot/vue-dist/.vite/manifest.json');

    if (!is_file($manifestPath)) {
        throw new \RuntimeException('Отсутствует манифест Vite');
    }

    $json = file_get_contents($manifestPath);

    if ($json === false) {
        throw new \RuntimeException('Не удалось прочитать манифест Vite.');
    }

    $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($manifest)) {
        throw new \RuntimeException('Ошибка при чтении манифеста');
    }

    $entry = $manifest['src/main.ts'] ?? null;

    if (!is_array($entry)) {
        throw new \RuntimeException('Ошибка при чтении точки входа');
    }

    $file = $entry['file'] ?? null;

    if (!is_string($file) || $file === '') {
        throw new \RuntimeException('В манифесте отсутствует файл точки входа.');
    }

    $url = Yii::getAlias('@web') . '/vue-dist/' . $file;

    $this->registerJsFile($url, [
        'type' => 'module',
        'position' => \yii\web\View::POS_HEAD,
    ]);

    foreach ($entry['css'] ?? [] as $css) {
        if (!is_string($css) || $css === '') {
            throw new \RuntimeException('Некорректный файл стилей в манифесте Vite.');
        }
        $this->registerCssFile(Yii::getAlias('@web') . '/vue-dist/' . $css);
    }
}

$this->title = 'Samdaught Space';
?>

<div id="app"></div>
