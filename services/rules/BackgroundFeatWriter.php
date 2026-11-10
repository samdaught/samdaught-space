<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\BackgroundFeat;

class BackgroundFeatWriter
{

    public function save(CatalogEntry $background, int $featId): void
    {
        Yii::$app->db->transaction(function () use ($background, $featId): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $background->id]
            )->queryScalar();
            if ($id === false) {
                throw new \RuntimeException('Предыстория не найдена при сохранении черты.');
            }
            $link = BackgroundFeat::findOne(['background_entry_id' => $background->id]);
            if ($link === null) {
                $link = new BackgroundFeat();
                $link->background_entry_id = $background->id;
            }
            $link->feat_entry_id = $featId;
            if (!$link->save()) {
                throw new \RuntimeException('Не удалось сохранить черту предыстории.');
            }
        });
    }
}
