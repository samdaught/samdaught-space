<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\SpeciesDefinition;

class SpeciesDefinitionWriter
{
    public function save(CatalogEntry $entry, int $speedFeet): void
    {
        Yii::$app->db->transaction(function () use ($entry, $speedFeet): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $entry->id])->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Вид не найден при сохранении базовой скорости.');
            }
            $definition = SpeciesDefinition::findOne($entry->id);
            if ($definition === null) {
                $definition = new SpeciesDefinition();
                $definition->catalog_entry_id = $entry->id;
            }
            $definition->speed_feet = $speedFeet;
            if (!$definition->save()) {
                throw new \RuntimeException('Не удалось сохранить базовую скорость вида.');
            }
        });
    }
}
