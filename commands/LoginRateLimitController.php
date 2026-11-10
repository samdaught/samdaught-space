<?php

declare(strict_types=1);

namespace app\commands;

use Yii;

use yii\console\Controller;
use yii\console\ExitCode;

class LoginRateLimitController extends Controller
{
    public function actionCleanup(): int
    {
        $cutoff = time() - 24 * 60 * 60;

        $deleted = Yii::$app->db->transaction(
            function (\yii\db\Connection $db) use ($cutoff): int {
                $db->createCommand(
                    'LOCK TABLE {{%login_rate_limit}} IN SHARE ROW EXCLUSIVE MODE'
                )->execute();

                return $db->createCommand()
                    ->delete('{{%login_rate_limit}}', ['<', 'updated_at', $cutoff])
                    ->execute();
            }
        );

        $this->stdout("Удалено записей: {$deleted}\n");
        return ExitCode::OK;
    }
}