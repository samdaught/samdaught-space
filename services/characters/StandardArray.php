<?php

declare(strict_types=1);

namespace app\services\characters;

class StandardArray
{
    public const VALUES = [15, 14, 13, 12, 10, 8];

    public function isValidAllocation(array $scores): bool
    {
        $remaining = self::VALUES;

        if (count($scores) > 6) return false;

        foreach ($scores as $score) {

            if ($score === null) {
                continue;
            };

            if (!is_int($score)) {
                return false;
            };

            $index = array_search($score, $remaining, true);

            if ($index === false) {
                return false;
            }

            unset($remaining[$index]);
        }

        return true;
    }
}
