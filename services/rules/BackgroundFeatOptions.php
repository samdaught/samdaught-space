<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\FeatCategory;
use app\models\rules\FeatDefinition;
use app\models\rules\RuleProfile;

class BackgroundFeatOptions
{
    public function get(?RuleProfile $profile): array
    {
        if ($profile === null) {
            return [];
        }
        $entries = CatalogEntry::find()->alias('entry')
            ->innerJoin(['definition' => FeatDefinition::tableName()], '[[definition.catalog_entry_id]] = [[entry.id]]')
            ->where(['entry.type' => 'feat', 'entry.is_published' => true, 'definition.category_code' => FeatCategory::ORIGIN])
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
        $foundId = Yii::$app->db->createCommand(
            'SELECT [[entry.id]] FROM {{%catalog_entry}} entry'
            . ' INNER JOIN {{%feat_definition}} definition ON [[definition.catalog_entry_id]] = [[entry.id]]'
            . ' WHERE [[entry.id]] = CAST(:id AS BIGINT) AND [[entry.type]] = :type'
            . ' AND [[entry.is_published]] = TRUE AND [[definition.category_code]] = :category'
            . ' FOR SHARE OF entry, definition',
            [':id' => $id, ':type' => 'feat', ':category' => FeatCategory::ORIGIN]
        )->queryScalar();
        return $foundId === false ? null : CatalogEntry::findOne($foundId);
    }
}
