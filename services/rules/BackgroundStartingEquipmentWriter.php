<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{BackgroundStartingEquipment, BackgroundStartingEquipmentItem};

class BackgroundStartingEquipmentWriter
{
    public function save(CatalogEntry $background, array $value): void
    {
        Yii::$app->db->transaction(function () use ($background, $value): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $background->id])->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Предыстория не найдена при сохранении комплекта.');
            }
            $rule = BackgroundStartingEquipment::findOne($background->id);
            if ($rule === null) {
                $rule = new BackgroundStartingEquipment();
                $rule->background_entry_id = $background->id;
            }
            foreach (['package_money_cp', 'alternative_money_cp', 'include_background_tool'] as $field) {
                $rule->$field = $value[$field];
            }
            if (!$rule->save()) {
                throw new \RuntimeException('Не удалось сохранить варианты стартового снаряжения.');
            }
            BackgroundStartingEquipmentItem::deleteAll(['background_entry_id' => $background->id]);
            foreach ($value['items'] as $line) {
                $item = new BackgroundStartingEquipmentItem();
                $item->background_entry_id = $background->id;
                $item->item_entry_id = $line['item_entry_id'];
                $item->quantity = $line['quantity'];
                if (!$item->save()) {
                    throw new \RuntimeException('Не удалось сохранить предмет стартового комплекта.');
                }
            }
        });
    }
}
