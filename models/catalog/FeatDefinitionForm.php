<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\BackgroundFeat;
use app\models\rules\FeatCategory;
use yii\base\Model;

class FeatDefinitionForm extends Model
{
    public $feat_category_code = null;

    public ?int $catalogEntryId = null;

    public function rules(): array
    {
        return [
            ['feat_category_code', 'required', 'message' => 'Выберите категорию черты.'],
            ['feat_category_code', 'string', 'max' => 32],
            ['feat_category_code', 'exist', 'targetClass' => FeatCategory::class,
                'targetAttribute' => ['feat_category_code' => 'code'], 'message' => 'Выберите категорию черты из справочника.'],
            ['feat_category_code', 'validateBackgroundUsage'],
        ];
    }

    public function validateBackgroundUsage($attribute, $params): void
    {
        if ($this->$attribute !== FeatCategory::ORIGIN && $this->catalogEntryId !== null
            && BackgroundFeat::find()->where(['feat_entry_id' => $this->catalogEntryId])->exists()) {
            $this->addError($attribute, 'Эту черту используют предыстории. Сначала замените её в предысториях, затем измените категорию.');
        }
    }
}
