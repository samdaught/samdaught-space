<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\Character;
use app\models\characters\CharacterAbilityScore;

class AbilityScoreWriter
{

    public function save(Character $character, array $scores): void
    {
        Yii::$app->db->transaction(function () use ($character, $scores): void {
            foreach ($scores as $abilityCode => $baseScore) {
                $score = CharacterAbilityScore::findOne([
                    'character_id' => $character->id,
                    'ability_code' => $abilityCode,
                ]);

                if ($score === null) {
                    $score = new CharacterAbilityScore();
                    $score->character_id = $character->id;
                    $score->ability_code = $abilityCode;
                }

                $score->base_score = $baseScore;

                if ($score->save() === false) {
                    throw new \RuntimeException('Не удалось сохранить характеристику.');
                }
            }
        });
    }
}
