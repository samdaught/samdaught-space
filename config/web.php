<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'language' => 'ru-RU',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'on beforeRequest' => static function (): void {
        Yii::$app->response->headers->set('X-Request-ID', \app\helpers\Diagnostics::requestId());
    },
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,

                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'assetManager' => [
            'appendTimestamp' => true,
        ],
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_KEY'),
            'csrfCookie' => [
                'httpOnly' => true,
                'secure' => YII_ENV_PROD,
                'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
            ],
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\users\User::class,
            'enableAutoLogin' => true,
            'loginUrl' => ['/login'],
            'identityCookie' => [
                'name' => '_identity',
                'httpOnly' => true,
                'secure' => YII_ENV_PROD,
                'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'session' => [
            'cookieParams' => [
                'httponly' => true,
                'secure' => YII_ENV_PROD,
                'samesite' => \yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'errorHandler' => [
            'class' => \app\helpers\PublicErrorHandler::class,
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \app\helpers\DiagnosticLogTarget::class,
                    'logVars' => [],
                    'prefix' => [\app\helpers\Diagnostics::class, 'prefix'],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/app',
                'app' => 'site/legacy-app',
                'app/<path:.*>' => 'site/legacy-app',
                'GET api/v1/session' => 'users/session-api/index',
                'POST api/v1/auth/login' => 'users/auth-api/login',
                'POST api/v1/auth/signup' => 'users/auth-api/signup',
                'POST api/v1/auth/logout' => 'users/auth-api/logout',
                'POST api/v1/auth/change-password' => 'users/auth-api/change-password',
                'POST api/v1/auth/force-password-change' => 'users/auth-api/force-password-change',
                'GET api/v1/news' => 'news/news-api/index',
                'GET api/v1/news/<id:\d+>' => 'news/news-api/view',
                'GET api/v1/admin/news' => 'news/news-admin-api/index',
                'GET api/v1/admin/news/<id:\d+>' => 'news/news-admin-api/view',
                'POST api/v1/admin/news' => 'news/news-admin-api/create',
                'PATCH api/v1/admin/news/<id:\d+>' => 'news/news-admin-api/update',
                'POST api/v1/admin/news/<id:\d+>/publish' => 'news/news-admin-api/publish',
                'POST api/v1/admin/news/<id:\d+>/unpublish' => 'news/news-admin-api/unpublish',
                'DELETE api/v1/admin/news/<id:\d+>' => 'news/news-admin-api/delete',

                'GET api/v1/content-sources' => 'catalog/content-source-api/index',
                'GET api/v1/content-sources/options' => 'catalog/content-source-api/options',
                'POST api/v1/content-sources' => 'catalog/content-source-api/create',

                'GET api/v1/catalog-entries' => 'catalog/catalog-entry-api/index',
                'GET api/v1/catalog-entries/<id:\d+>' => 'catalog/catalog-entry-api/view',
                'POST api/v1/catalog-entries' => 'catalog/catalog-entry-api/create',
                'PATCH api/v1/catalog-entries/<id:\d+>' => 'catalog/catalog-entry-api/update',
                'POST api/v1/catalog-entries/<id:\d+>/copy-to-world' => 'catalog/catalog-entry-api/copy-to-world',
                'GET api/v1/catalog-entries/<id:\d+>/background-abilities' => 'catalog/background-ability-api/view',
                'PATCH api/v1/catalog-entries/<id:\d+>/background-abilities' => 'catalog/background-ability-api/update',
                'POST api/v1/catalog-entries/<id:\d+>/publish' => 'catalog/catalog-entry-api/publish',
                'POST api/v1/catalog-entries/<id:\d+>/unpublish' => 'catalog/catalog-entry-api/unpublish',
                'GET api/v1/catalog-entry-types' => 'catalog/catalog-entry-api/types',
                'GET api/v1/ability-definitions' => 'catalog/catalog-entry-api/ability-options',
                'GET api/v1/feat-categories' => 'catalog/catalog-entry-api/feat-categories',
                'GET api/v1/item-definition-options' => 'catalog/catalog-entry-api/item-options',
                'GET api/v1/basic-rule-options' => 'catalog/catalog-entry-api/basic-rule-options',
                'GET api/v1/creation-rule-options' => 'catalog/catalog-entry-api/creation-rule-options',
                'GET api/v1/background-ability-options' => 'catalog/background-ability-api/options',

                'GET api/v1/archive/types' => 'catalog/archive-api/types',
                'GET api/v1/archive/entries/<id:\d+>' => 'catalog/archive-api/view',
                'GET api/v1/archive/entries' => 'catalog/archive-api/index',

                'GET api/v1/catalog-audit' => 'catalog/catalog-entry-api/audit',
                'GET api/v1/rulebook/options' => 'catalog/catalog-entry-api/rulebook-options',
                'GET api/v1/entry-mechanics/options' => 'catalog/catalog-entry-api/mechanics-options',

                'GET api/v1/rule-profiles' => 'rules/rule-profile-api/index',

                'POST api/v1/character-editor/options' => 'characters/character-api/editor-options',
                'GET api/v1/characters' => 'characters/character-api/index',
                'POST api/v1/characters' => 'characters/character-api/create',
                'GET api/v1/characters/<id:\d+>' => 'characters/character-api/view',
                'PATCH api/v1/characters/<id:\d+>' => 'characters/character-api/update',
                'DELETE api/v1/characters/<id:\d+>' => 'characters/character-api/delete',

                '<route:(?:api|site|debug|gii|assets|css|js|images|vue-dist)(?:/.*)?>' => '<route>',
                '<route:admin/user(?:/.*)?>' => '<route>',

                '<path:.*>' => 'site/app',
            ],
        ],
    ],
    'as forcePasswordChange' => [
        'class' => \app\filters\ForcePasswordChangeFilter::class,
    ],
    'modules' => [
        'admin' => [
            'class' => \app\modules\admin\Module::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {

    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,

    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,

    ];
}

return $config;
