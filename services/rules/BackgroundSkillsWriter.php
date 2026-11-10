<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\BackgroundSkillProficiency;

class BackgroundSkillsWriter
{

    public function save(CatalogEntry $background, array $skillIds): void
    {
        Yii::$app->db->transaction(function () use ($background, $skillIds): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $background->id]
            )->queryScalar();
            if ($id === false) throw new \RuntimeException('Предыстория не найдена при сохранении навыков.');

            BackgroundSkillProficiency::deleteAll(['background_entry_id' => $background->id]);
            foreach ($skillIds as $skillId) {
                $link = new BackgroundSkillProficiency();
                $link->background_entry_id = $background->id;
                $link->skill_entry_id = $skillId;
                if (!$link->save()) throw new \RuntimeException('Не удалось сохранить навыки предыстории.');
            }
        });
    }
}
