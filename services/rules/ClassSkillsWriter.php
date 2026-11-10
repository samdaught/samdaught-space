<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ClassDefinition, ClassSkillOption};

class ClassSkillsWriter
{
    public function save(CatalogEntry $entry, int $count, array $ids): void
    {
        Yii::$app->db->transaction(function () use ($entry, $count, $ids): void {
            Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $entry->id])->queryScalar();
            $definition = ClassDefinition::findOne($entry->id);
            if ($definition === null) throw new \RuntimeException('Сначала настройте базовые свойства класса.');
            $definition->skill_choice_count = $count;
            if (!$definition->save()) throw new \RuntimeException('Не удалось сохранить количество навыков класса.');
            ClassSkillOption::deleteAll(['class_entry_id' => $entry->id]);
            foreach ($ids as $id) {
                $row = new ClassSkillOption();
                $row->class_entry_id = $entry->id;
                $row->skill_entry_id = $id;
                if (!$row->save()) throw new \RuntimeException('Не удалось сохранить разрешённые навыки класса.');
            }
        });
    }
}
