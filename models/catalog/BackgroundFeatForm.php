<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\RuleProfile;
use app\services\rules\BackgroundFeatOptions;
use app\services\rules\CatalogAvailability;
use yii\base\Model;

class BackgroundFeatForm extends Model
{
    public $feat_entry_id = null;

    public ?int $originProfileId = null;

    public function rules(): array
    {
        return [['feat_entry_id', 'validateFeat', 'skipOnEmpty' => false]];
    }

    public function validateFeat($attribute, $params): void
    {
        $id = $this->$attribute;
        if (!is_int($id) || $id < 1) {
            $this->addError($attribute, 'Выберите черту происхождения из списка.');
            return;
        }
        $profile = $this->originProfileId === null ? null : RuleProfile::findOne($this->originProfileId);
        if ($profile === null) {
            $this->addError($attribute, 'Сначала выберите корректный профиль происхождения предыстории.');
            return;
        }
        $entry = (new BackgroundFeatOptions())->lockAndFind($id);
        if ($entry === null) {
            $this->addError($attribute, 'Выберите опубликованную черту категории «Происхождение».');
            return;
        }
        if (!(new CatalogAvailability())->isAvailableInProfile($entry, $profile)) {
            $this->addError($attribute, 'Черта недоступна профилю происхождения предыстории.');
        }
    }
}
