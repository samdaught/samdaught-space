<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\BackgroundToolProficiency;

class BackgroundToolWriter
{

    public function save(CatalogEntry $background, array $target): void
    {
        Yii::$app->db->transaction(function () use ($background, $target): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $background->id])->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Предыстория не найдена при сохранении владения инструментом.');
            }
            $rule = BackgroundToolProficiency::findOne($background->id);
            if ($rule === null) {
                $rule = new BackgroundToolProficiency();
                $rule->background_entry_id = $background->id;
            }
            $rule->tool_entry_id = $target['tool_entry_id'];
            $rule->tool_group_code = $target['tool_group_code'];
            if (!$rule->save()) {
                throw new \RuntimeException('Не удалось сохранить владение инструментом предыстории.');
            }
        });
    }
}
