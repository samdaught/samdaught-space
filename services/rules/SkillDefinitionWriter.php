<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\SkillDefinition;

class SkillDefinitionWriter
{

    public function save(CatalogEntry $entry, string $abilityCode): void
    {
        Yii::$app->db->transaction(function () use ($entry, $abilityCode): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $entry->id]
            )->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Запись навыка не найдена при сохранении.');
            }

            $definition = SkillDefinition::findOne(['catalog_entry_id' => $entry->id]);
            if ($definition === null) {
                $definition = new SkillDefinition();
                $definition->catalog_entry_id = $entry->id;
            }
            $definition->ability_code = $abilityCode;
            if (!$definition->save()) {
                throw new \RuntimeException('Не удалось сохранить характеристику навыка.');
            }
        });
    }
}
