<?php

declare(strict_types=1);

namespace app\models\catalog;

use yii\db\ActiveRecord;
use app\helpers\AutomaticCodeBehavior;

class ContentSource extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'code' => [
                'class' => AutomaticCodeBehavior::class,
                'attribute' => 'title',
                'slugAttribute' => 'code',
                'immutable' => true,
                'ensureUnique' => true,
                'maxLength' => 64,
                'fallback' => 'source',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['code', 'title', 'rights_note'], 'trim'],
            [['code', 'title'], 'required'],
            ['rights_note', 'default', 'value' => ''],
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
