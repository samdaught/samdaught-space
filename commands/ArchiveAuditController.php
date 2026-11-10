<?php

declare(strict_types=1);

namespace app\commands;

use app\models\catalog\CatalogEntry;
use app\services\rules\ArchiveReadiness;
use yii\console\Controller;
use yii\helpers\Json;

final class ArchiveAuditController extends Controller
{
    public function actionIndex(): int
    {
        $report = [];
        $failed = 0;
        foreach (CatalogEntry::find()->orderBy('id')->each() as $entry) {
            $row = (new ArchiveReadiness())->inspect($entry);
            if (!$row['complete']) {
                $failed++;
            }
            $report[] = $row;
        }
        $this->stdout(Json::encode(['total' => count($report), 'incomplete' => $failed, 'entries' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        return $failed ? 1 : 0;
    }
}
