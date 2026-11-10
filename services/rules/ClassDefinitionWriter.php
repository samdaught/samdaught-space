<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ClassDefinition, ClassSavingThrowProficiency};

class ClassDefinitionWriter
{
    public function save(CatalogEntry $entry, int $hitDieSides, array $savingThrowCodes): void
    {
        Yii::$app->db->transaction(function () use ($entry, $hitDieSides, $savingThrowCodes): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $entry->id])->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Класс не найден при сохранении базовых свойств.');
            }
            $definition = ClassDefinition::findOne($entry->id);
            if ($definition === null) {
                $definition = new ClassDefinition();
                $definition->catalog_entry_id = $entry->id;
            }
            $definition->hit_die_sides = $hitDieSides;
            if (!$definition->save()) {
                throw new \RuntimeException('Не удалось сохранить кость хитов класса.');
            }
            ClassSavingThrowProficiency::deleteAll(['class_entry_id' => $entry->id]);
            foreach ($savingThrowCodes as $code) {
                $row = new ClassSavingThrowProficiency();
                $row->class_entry_id = $entry->id;
                $row->ability_code = $code;
                if (!$row->save()) {
                    throw new \RuntimeException('Не удалось сохранить владения спасбросками класса.');
                }
            }
        });
    }
}
