<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\{Character, CharacterInventoryState, CharacterItem};
use yii\web\{ConflictHttpException, ForbiddenHttpException};

class CharacterInventoryWriter
{

    public function save(Character $character, array $items, int $expectedRevision): void
    {
        Yii::$app->db->transaction(function () use ($character, $items, $expectedRevision): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%character}} WHERE [[id]] = :id FOR UPDATE', [':id' => $character->id])->queryScalar();
            $current = $id === false ? null : Character::findOne($id);
            if ($current === null || $current->rule_profile_id !== $character->rule_profile_id) {
                throw new ConflictHttpException('Профиль персонажа изменился. Загрузите актуальные данные.');
            }
            if ($current->status !== Character::STATUS_DRAFT) {
                throw new ForbiddenHttpException('Игрок может менять инвентарь только у черновика.');
            }
            $state = CharacterInventoryState::findOne($character->id);
            $revision = $state === null ? 0 : $state->revision;
            if ($expectedRevision !== $revision) {
                throw new ConflictHttpException('Инвентарь уже изменён. Загрузите актуальные данные.');
            }
            $owned = CharacterItem::find()->where(['character_id' => $character->id, 'source_kind' => CharacterItem::SOURCE_DRAFT])->indexBy('id')->all();
            $keptIds = [];
            foreach ($items as $line) {
                if ($line['id'] !== null && !isset($owned[$line['id']])) {
                    throw new ConflictHttpException('Редактируемая строка инвентаря изменилась. Загрузите актуальные данные.');
                }
                $row = $line['id'] === null ? new CharacterItem() : $owned[$line['id']];
                $row->character_id = $character->id;
                $row->source_kind = CharacterItem::SOURCE_DRAFT;
                foreach (['item_entry_id', 'quantity', 'is_equipped', 'note'] as $field) {
                    $row->$field = $line[$field];
                }
                if (!$row->save()) {
                    throw new \RuntimeException('Не удалось сохранить предмет инвентаря.');
                }
                $keptIds[] = $row->id;
            }
            CharacterItem::deleteAll(['and', ['character_id' => $character->id, 'source_kind' => CharacterItem::SOURCE_DRAFT], ['not in', 'id', $keptIds]]);
            if ($state === null) {
                $state = new CharacterInventoryState();
                $state->character_id = $character->id;
            }
            $state->revision = $revision + 1;
            if (!$state->save()) {
                throw new \RuntimeException('Не удалось сохранить версию инвентаря.');
            }
        });
    }
}
