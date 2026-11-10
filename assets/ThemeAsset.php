<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;

class ThemeAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = ['css/portal.css'];
}
