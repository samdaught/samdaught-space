<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;

use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public function __construct(
        $id,
        $module,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout', 'app'],
                'rules' => [
                    [
                        'actions' => ['app'],
                        'allow' => true,
                        'matchCallback' => static fn(): bool => !preg_match('~^admin(?:/|$)~', Yii::$app->request->pathInfo),
                    ],
                    [
                        'actions' => ['app'],
                        'allow' => true,
                        'roles' => ['manageNews'],
                        'matchCallback' => static fn(): bool => (bool) preg_match('~^admin/news(?:/|$)~', Yii::$app->request->pathInfo),
                    ],
                    [
                        'actions' => ['app'],
                        'allow' => true,
                        'roles' => ['manageCatalog'],
                        'matchCallback' => static fn(): bool => !preg_match('~^admin/news(?:/|$)~', Yii::$app->request->pathInfo),
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'app' => ['GET', 'HEAD'],
                    'legacy-app' => ['GET', 'HEAD'],
                    'logout' => ['POST'],
                    'login' => ['GET', 'HEAD'],
                    'signup' => ['GET', 'HEAD'],
                    'force-password-change' => ['GET', 'HEAD'],
                    'change-password' => ['GET', 'HEAD'],
                ],
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'transparent' => true,
            ],
        ];
    }

    public function actionIndex(): Response
    {
        return $this->redirect(['/site/app']);
    }

    public function actionLogin(): Response { return $this->redirect(['/login']); }
    public function actionSignup(): Response { return $this->redirect(['/signup']); }
    public function actionChangePassword(): Response { return $this->redirect(['/change-password']); }
    public function actionForcePasswordChange(): Response { return $this->redirect(['/force-password-change']); }

    public function actionLogout(): Response
    {
        (new \app\services\users\Authentication())->logout();
        return $this->redirect(['/site/app']);
    }

    public function actionApp(): string
    {
        $this->layout = 'vue';
        return $this->render('app');
    }

    public function actionLegacyApp(string $path = ''): Response
    {

        if (str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \yii\web\BadRequestHttpException('Некорректный адрес страницы.');
        }
        $target = Yii::getAlias('@web') . '/' . ltrim($path, '/');

        $query = Yii::$app->request->queryString;
        if ($query !== '') $target .= '?' . $query;
        return $this->redirect($target);
    }
}
