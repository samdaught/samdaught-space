<?php

declare(strict_types=1);

(static function (): void {
    $environment = defined('YII_ENV') ? YII_ENV : getenv('YII_ENV');
    if ($environment === false || $environment === '') {
        $environment = 'prod';
    }
    if (!in_array($environment, ['dev', 'test', 'prod'], true)) {
        throw new \RuntimeException('YII_ENV должен быть dev, test или prod.');
    }

    if (defined('YII_DEBUG')) {
        $debug = YII_DEBUG;
    } else {
        $value = getenv('YII_DEBUG');
        $debug = $value === false || $value === ''
            ? $environment === 'dev'
            : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
    if (!is_bool($debug)) {
        throw new \RuntimeException('YII_DEBUG должен быть логическим значением: true/false или 1/0.');
    }
    if ($environment === 'prod' && $debug) {
        throw new \RuntimeException('В production YII_DEBUG должен быть выключен.');
    }

    defined('YII_ENV') or define('YII_ENV', $environment);
    defined('YII_DEBUG') or define('YII_DEBUG', $debug);
})();
