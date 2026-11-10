<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class HitDieDefinition extends ActiveRecord
{
    public function rules(): array
    {
        return [[['sides', 'title'], 'required'], ['sides', 'integer', 'min' => 1, 'max' => 32767],
            ['sides', 'unique'], ['title', 'string', 'max' => 16]];
    }
}
