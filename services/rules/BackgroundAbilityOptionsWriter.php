<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\BackgroundAbilityOption;

class BackgroundAbilityOptionsWriter
{
    public function save(CatalogEntry $background, array $codes): void
    {
        Yii::$app->db->transaction(function () use ($background, $codes): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $background->id]
            )->queryScalar();

            if ($id === false) {
                throw new \RuntimeException('Предыстория не найдена при сохранении настроек.');
            }

            BackgroundAbilityOption::deleteAll(['background_entry_id' => $background->id]);

            foreach ($codes as $code) {
                $option = new BackgroundAbilityOption();
                $option->background_entry_id = $background->id;
                $option->ability_code = $code;

                if (!$option->save()) {
                    throw new \RuntimeException('Не удалось сохранить доступные характеристики предыстории.');
                }
            }
        });
    }
}
