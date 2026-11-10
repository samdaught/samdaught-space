<?php

declare(strict_types=1);

namespace app\models\catalog;

use yii\db\ActiveRecord;

class ContentSource extends ActiveRecord
{
    public function rules(): array
    {
        return [
            [['code', 'title', 'rights_note'], 'trim'],
            [['code', 'title', 'rights_note'], 'required'],
            ['rights_note', 'string'],
            ['code', 'string', 'max' => 64],
            ['title', 'string', 'max' => 255],
            ['code', 'unique'],
            [['url', 'attribution_text'], 'default'],
            ['url', 'url'],
            ['attribution_text', 'string'],
        ];
    }
}
