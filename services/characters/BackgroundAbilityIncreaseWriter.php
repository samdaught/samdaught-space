<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\Character;
use app\models\characters\CharacterAbilityIncrease;
use app\models\characters\CharacterAbilityIncreaseSource;

class BackgroundAbilityIncreaseWriter
{

    public function save(Character $character, array $increases): void
    {
        Yii::$app->db->transaction(function () use ($character, $increases): void {
            $id = Yii::$app->db->createCommand(
                'SELECT [[id]] FROM {{%character}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $character->id]
            )->queryScalar();

            if ($id === false) {
                throw new \DomainException('Персонаж больше не существует.');
            }

            $current = Character::findOne($id);

            if ($current->status !== Character::STATUS_DRAFT) {
                throw new \DomainException('Увеличения можно менять только у черновика персонажа.');
            }

            if ($current->user_id !== $character->user_id
                || $current->rule_profile_id !== $character->rule_profile_id
                || $current->background_entry_id !== $character->background_entry_id) {
                throw new \DomainException('Данные персонажа изменились. Загрузите актуальное состояние перед сохранением увеличений.');
            }

            if ($current->background_entry_id === null) {
                throw new \DomainException('Сначала выберите предысторию персонажа.');
            }

            $increaseSet = CharacterAbilityIncreaseSource::findOne([
                'character_id' => $current->id,
                'slot' => CharacterAbilityIncreaseSource::BACKGROUND_SLOT,
            ]);

            if ($increaseSet === null) {
                $increaseSet = new CharacterAbilityIncreaseSource();
                $increaseSet->character_id = $current->id;
                $increaseSet->slot = CharacterAbilityIncreaseSource::BACKGROUND_SLOT;
            }

            $increaseSet->source_kind = CharacterAbilityIncreaseSource::KIND_BACKGROUND;
            $increaseSet->source_entry_id = $current->background_entry_id;
            $increaseSet->obtained_level = 1;
            $increaseSet->class_entry_id = null;
            $increaseSet->revision = $increaseSet->isNewRecord ? 1 : $increaseSet->revision + 1;

            if ($increaseSet->save() === false) {
                throw new \RuntimeException('Не удалось сохранить набор увеличений от предыстории.');
            }

            CharacterAbilityIncrease::deleteAll(['source_id' => $increaseSet->id]);

            foreach ($increases as $abilityCode => $amount) {
                $increase = new CharacterAbilityIncrease();
                $increase->source_id = $increaseSet->id;
                $increase->ability_code = $abilityCode;
                $increase->amount = $amount;

                if ($increase->save() === false) {
                    throw new \RuntimeException('Не удалось сохранить увеличение характеристики от предыстории.');
                }
            }
        });
    }
}
