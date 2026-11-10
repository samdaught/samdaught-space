<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\{RuleProfile, ToolGroup};
use app\services\rules\{BackgroundToolOptions, CatalogAvailability};
use yii\base\Model;

class BackgroundToolForm extends Model
{
    public $background_tool = null;
    public ?int $originProfileId = null;

    public function rules(): array
    {
        return [['background_tool', 'validateTool', 'skipOnEmpty' => false]];
    }

    public function validateTool($attribute, $params): void
    {
        $value = $this->$attribute;
        if (!is_array($value) || count($value) !== 2
            || !array_key_exists('tool_entry_id', $value) || !array_key_exists('tool_group_code', $value)) {
            $this->addError($attribute, 'Укажите инструмент или группу в полях tool_entry_id и tool_group_code.');
            return;
        }
        $id = $value['tool_entry_id'];
        $group = $value['tool_group_code'];
        if (($id === null) === ($group === null)) {
            $this->addError($attribute, 'Выберите либо конкретный инструмент, либо одну группу инструментов.');
            return;
        }
        if ($group !== null) {
            if (!is_string($group) || strlen($group) > 32 || !ToolGroup::find()->where(['code' => $group])->exists()) {
                $this->addError($attribute, 'Выберите группу инструментов из справочника.');
            }
            return;
        }
        if (!is_int($id) || $id < 1) {
            $this->addError($attribute, 'Выберите инструмент из списка.');
            return;
        }
        $profile = $this->originProfileId === null ? null : RuleProfile::findOne($this->originProfileId);
        if ($profile === null) {
            $this->addError($attribute, 'Сначала выберите корректный профиль происхождения предыстории.');
            return;
        }
        $entry = (new BackgroundToolOptions())->lockAndFind($id);
        if ($entry === null) {
            $this->addError($attribute, 'Выберите опубликованный предмет с определением инструмента.');
            return;
        }
        if (!(new CatalogAvailability())->isAvailableInProfile($entry, $profile)) {
            $this->addError($attribute, 'Инструмент недоступен профилю происхождения предыстории.');
        }
    }
}
