<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ItemDefinition, ItemKind, RuleProfile, ToolDefinition};

class BackgroundToolOptions
{
    public function get(?RuleProfile $profile): array
    {
        if ($profile === null) {
            return [];
        }
        $entries = CatalogEntry::find()->alias('entry')->select('entry.*')
            ->innerJoin(['item' => ItemDefinition::tableName()], '[[item.catalog_entry_id]] = [[entry.id]]')
            ->innerJoin(['tool' => ToolDefinition::tableName()], '[[tool.catalog_entry_id]] = [[entry.id]]')
            ->where(['entry.type' => 'item', 'entry.is_published' => true, 'item.kind_code' => ItemKind::TOOL])
            ->orderBy(['entry.title' => SORT_ASC, 'entry.id' => SORT_ASC])->all();
        $availability = new CatalogAvailability();
        $result = [];
        foreach ($entries as $entry) {
            if ($availability->isAvailableInProfile($entry, $profile)) {
                $result[] = ['id' => $entry->id, 'title' => $entry->title];
            }
        }
        return $result;
    }

    public function lockAndFind(int $id): ?CatalogEntry
    {
        $found = Yii::$app->db->createCommand(
            'SELECT [[entry.id]] FROM {{%catalog_entry}} entry'
            . ' INNER JOIN {{%item_definition}} item ON [[item.catalog_entry_id]] = [[entry.id]]'
            . ' INNER JOIN {{%tool_definition}} tool ON [[tool.catalog_entry_id]] = [[entry.id]]'
            . ' WHERE [[entry.id]] = CAST(:id AS BIGINT) AND [[entry.type]] = :type'
            . ' AND [[entry.is_published]] = TRUE AND [[item.kind_code]] = :kind'
            . ' FOR SHARE OF entry, item, tool',
            [':id' => $id, ':type' => 'item', ':kind' => ItemKind::TOOL]
        )->queryScalar();
        return $found === false ? null : CatalogEntry::findOne($found);
    }
}
