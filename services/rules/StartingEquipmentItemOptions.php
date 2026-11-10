<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ItemDefinition, ItemKind, RuleProfile, ToolDefinition};

class StartingEquipmentItemOptions
{
    public function get(?RuleProfile $profile): array
    {
        if ($profile === null) {
            return [];
        }
        $entries = CatalogEntry::find()->alias('entry')->select('entry.*')
            ->innerJoin(['item' => ItemDefinition::tableName()], '[[item.catalog_entry_id]] = [[entry.id]]')
            ->leftJoin(['tool' => ToolDefinition::tableName()], '[[tool.catalog_entry_id]] = [[entry.id]]')
            ->where(['entry.type' => 'item', 'entry.is_published' => true])
            ->andWhere(['or', ['<>', 'item.kind_code', ItemKind::TOOL], ['not', ['tool.catalog_entry_id' => null]]])
            ->orderBy(['entry.title' => SORT_ASC, 'entry.id' => SORT_ASC])->all();
        $availability = new CatalogAvailability();
        $options = [];
        foreach ($entries as $entry) {
            if ($availability->isAvailableInProfile($entry, $profile)) {
                $options[] = ['id' => $entry->id, 'title' => $entry->title];
            }
        }
        return $options;
    }

    public function lockAndFind(int $id): ?CatalogEntry
    {
        $found = Yii::$app->db->createCommand(
            'SELECT [[entry.id]] FROM {{%catalog_entry}} entry'
            . ' INNER JOIN {{%item_definition}} item ON [[item.catalog_entry_id]] = [[entry.id]]'
            . ' LEFT JOIN {{%tool_definition}} tool ON [[tool.catalog_entry_id]] = [[entry.id]]'
            . ' WHERE [[entry.id]] = CAST(:id AS BIGINT) AND [[entry.type]] = :type AND [[entry.is_published]] = TRUE'
            . ' AND ([[item.kind_code]] <> :tool_kind OR [[tool.catalog_entry_id]] IS NOT NULL)'
            . ' FOR SHARE OF entry, item',
            [':id' => $id, ':type' => 'item', ':tool_kind' => ItemKind::TOOL]
        )->queryScalar();
        return $found === false ? null : CatalogEntry::findOne($found);
    }
}
