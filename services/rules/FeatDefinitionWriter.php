<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\FeatDefinition;

class FeatDefinitionWriter
{

    public function save(CatalogEntry $entry, string $categoryCode): void
    {
        Yii::$app->db->transaction(function () use ($entry, $categoryCode): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $entry->id]
            )->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Черта не найдена при сохранении определения.');
            }
            $definition = FeatDefinition::findOne(['catalog_entry_id' => $entry->id]);
            if ($definition === null) {
                $definition = new FeatDefinition();
                $definition->catalog_entry_id = $entry->id;
            }
            $definition->category_code = $categoryCode;
            if (!$definition->save()) {
                throw new \RuntimeException('Не удалось сохранить категорию черты.');
            }
        });
    }
}
