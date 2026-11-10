<?php

declare(strict_types=1);

namespace app\models\rules;

use yii\db\ActiveRecord;

class ItemKind extends ActiveRecord
{
    public const TOOL = 'tool';

    public function rules(): array
    {
        return [[['code', 'title'], 'required'], ['code', 'string', 'max' => 32],
            ['title', 'string', 'max' => 100], ['code', 'unique']];
    }
}
