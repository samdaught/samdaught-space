<?php

declare(strict_types=1);

namespace app\services\characters;

class AbilityModifier
{
    public function calculate(int|null $score): int|null
    {
        if ($score === null) {
            return null;
        }

        return (int) floor(($score - 10) / 2);
    }
}
