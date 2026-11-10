<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\{Character, CharacterBackgroundChoice};
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;

class CharacterBackgroundChoiceWriter
{
    public function save(Character $character, ?int $toolId, ?string $equipmentChoice, int $expectedRevision): void
    {
        Yii::$app->db->transaction(function () use ($character, $toolId, $equipmentChoice, $expectedRevision): void {
            $id = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%character}} WHERE [[id]] = :id FOR UPDATE', [':id' => $character->id])->queryScalar();
            $current = $id === false ? null : Character::findOne($id);
            if ($current === null || $current->background_entry_id !== $character->background_entry_id
                || $current->rule_profile_id !== $character->rule_profile_id) {
                throw new ConflictHttpException('Предыстория или профиль изменились. Загрузите актуальные данные.');
            }
            if ($current->status !== Character::STATUS_DRAFT) {
                throw new ForbiddenHttpException('Выборы предыстории можно менять только у черновика.');
            }
            $row = CharacterBackgroundChoice::findOne($character->id);
            $revision = $row === null ? 0 : $row->revision;
            if ($revision !== $expectedRevision) {
                throw new ConflictHttpException('Выборы уже изменены. Загрузите актуальные данные.');
            }
            if ($row === null) {
                $row = new CharacterBackgroundChoice();
                $row->character_id = $character->id;
            }
            $row->background_entry_id = $character->background_entry_id;
            $row->rule_profile_id = $character->rule_profile_id;
            $row->tool_entry_id = $toolId;
            $row->equipment_choice = $equipmentChoice;
            $row->revision = $revision + 1;
            if (!$row->save()) {
                throw new \RuntimeException('Не удалось сохранить личные выборы предыстории.');
            }
        });
    }
}
