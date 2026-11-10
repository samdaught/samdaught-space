<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\Character;
use app\models\characters\CharacterAbilityIncreaseSource;

class AbilityIncreaseContext
{
    public function findMatchingSets(Character $character): array
    {
        $increaseSets = CharacterAbilityIncreaseSource::find()
            ->where(['character_id' => $character->id])
            ->orderBy(['obtained_level' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $matchingSets = [];
        foreach ($increaseSets as $increaseSet) {
            if ($this->matches($increaseSet, $character)) {
                $matchingSets[] = $increaseSet;
            }
        }

        return $matchingSets;
    }

    public function matches(CharacterAbilityIncreaseSource $increaseSet, Character $character): bool
    {
        if ($increaseSet->obtained_level > $character->target_level) {
            return false;
        }

        if ($increaseSet->source_kind === CharacterAbilityIncreaseSource::KIND_BACKGROUND) {
            return $character->background_entry_id !== null
                && $increaseSet->source_entry_id === $character->background_entry_id;
        }

        if ($increaseSet->source_kind === CharacterAbilityIncreaseSource::KIND_LEVEL) {
            return $character->class_entry_id !== null
                && $increaseSet->class_entry_id === $character->class_entry_id;
        }

        return false;
    }
}
