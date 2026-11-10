<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class FeatCategory extends ActiveRecord
{
    public const ORIGIN = 'origin';

    public function rules(): array
    {
        return [
            [['code', 'title'], 'required'],
            ['code', 'string', 'max' => 32],
            ['title', 'string', 'max' => 100],
            ['code', 'unique'],
        ];
    }
}
