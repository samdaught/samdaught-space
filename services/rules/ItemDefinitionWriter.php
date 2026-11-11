<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ItemDefinition, ItemKind, ToolDefinition};

class ItemDefinitionWriter
{

    public function save(CatalogEntry $entry, string $kind, ?string $group, ?string $ability): void
    {
        Yii::$app->db->transaction(function () use ($entry, $kind, $group, $ability): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $entry->id])->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Предмет не найден при сохранении определения.');
            }
            $item = ItemDefinition::findOne($entry->id);
            if ($item === null) {
                $item = new ItemDefinition();
                $item->catalog_entry_id = $entry->id;
            }
            $item->kind_code = $kind;
            if (!$item->save()) {
                throw new \RuntimeException('Не удалось сохранить вид предмета.');
            }
            $db = Yii::$app->db;
            if ($kind !== 'weapon') {
                $db->createCommand()->delete('{{%weapon_property}}', ['item_entry_id' => $entry->id])->execute();
                $db->createCommand()->delete('{{%weapon_definition}}', ['catalog_entry_id' => $entry->id])->execute();
            }
            if (!in_array($kind, ['armor', 'shield'], true)) {
                $db->createCommand()->delete('{{%armor_definition}}', ['catalog_entry_id' => $entry->id])->execute();
            }
            if ($kind !== ItemKind::TOOL) {
                ToolDefinition::deleteAll(['catalog_entry_id' => $entry->id]);
                return;
            }
            $tool = ToolDefinition::findOne($entry->id);
            if ($tool === null) {
                $tool = new ToolDefinition();
                $tool->catalog_entry_id = $entry->id;
            }
            $tool->group_code = $group;
            $tool->ability_code = $ability;
            if (!$tool->save()) {
                throw new \RuntimeException('Не удалось сохранить свойства инструмента.');
            }
        });
    }
}
