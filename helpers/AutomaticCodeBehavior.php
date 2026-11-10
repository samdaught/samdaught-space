<?php

declare(strict_types=1);

namespace app\helpers;

use yii\behaviors\SluggableBehavior;
use yii\helpers\Inflector;

final class AutomaticCodeBehavior extends SluggableBehavior
{
    public int $maxLength = 100;
    public string $fallback = 'entry';

    protected function generateSlug($slugParts)
    {
        // Keep Cyrillic names readable even on hosts without the intl extension.
        $title = strtr(mb_strtolower(implode('-', $slugParts), 'UTF-8'), [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y',
            'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ]);
        $slug = Inflector::slug($title);

        return rtrim(substr($slug !== '' ? $slug : $this->fallback, 0, $this->maxLength), '-');
    }

    protected function generateUniqueSlug($baseSlug, $iteration)
    {
        $suffix = '-' . ($iteration + 1);

        return rtrim(substr($baseSlug, 0, $this->maxLength - strlen($suffix)), '-') . $suffix;
    }
}
