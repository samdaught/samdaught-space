<?php

declare(strict_types=1);

namespace app\tests\Support;

use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\mail\MailerInterface;

final class MailerBootstrap implements BootstrapInterface
{

    public function bootstrap($app): void
    {
        Yii::$container->setSingleton(MailerInterface::class, $app->mailer);
    }
}
