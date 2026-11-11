<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\{BackgroundStartingEquipment, RuleProfile};
use app\services\rules\{CatalogAvailability, StartingEquipmentItemOptions};
use yii\base\Model;

class BackgroundStartingEquipmentForm extends Model
{
    public $starting_equipment = null;
    public ?int $ruleProfileId = null;
    public bool $backgroundToolConfigured = false;

    public function rules(): array
    {
        return [['starting_equipment', 'validateEquipment', 'skipOnEmpty' => false]];
    }

    public function validateEquipment($attribute, $params): void
    {
        $value = $this->$attribute;
        $keys = ['package_money_cp', 'alternative_money_cp', 'include_background_tool', 'items'];
        if (!is_array($value) || count($value) !== count($keys) || array_diff($keys, array_keys($value))) {
            $this->addError($attribute, 'Заполните комплект и денежную альтернативу стартового снаряжения.');
            return;
        }
        foreach (['package_money_cp', 'alternative_money_cp'] as $key) {
            if (!is_int($value[$key]) || $value[$key] < 0 || $value[$key] > BackgroundStartingEquipment::MAX_INTEGER) {
                $this->addError($attribute, 'Суммы должны быть целыми неотрицательными числами медных монет.');
                return;
            }
        }
        if (!is_bool($value['include_background_tool'])) {
            $this->addError($attribute, 'Признак включения выбранного инструмента должен быть true или false.');
            return;
        }
        if ($value['include_background_tool'] && !$this->backgroundToolConfigured) {
            $this->addError($attribute, 'Сначала настройте владение инструментом этой предыстории.');
            return;
        }
        if (!is_array($value['items']) || !array_is_list($value['items']) || count($value['items']) > BackgroundStartingEquipment::MAX_ITEMS) {
            $this->addError($attribute, 'Передайте список предметов в пределах допустимого количества строк.');
            return;
        }
        $ids = [];
        foreach ($value['items'] as $line) {
            if (!is_array($line) || count($line) !== 2 || !array_key_exists('item_entry_id', $line) || !array_key_exists('quantity', $line)
                || !is_int($line['item_entry_id']) || $line['item_entry_id'] < 1
                || !is_int($line['quantity']) || $line['quantity'] < 1 || $line['quantity'] > BackgroundStartingEquipment::MAX_INTEGER) {
                $this->addError($attribute, 'В каждой строке выберите предмет и задайте положительное целое количество.');
                return;
            }
            $ids[] = $line['item_entry_id'];
        }
        if (count(array_unique($ids)) !== count($ids)) {
            $this->addError($attribute, 'Предмет повторяется: объедините количество в одной строке.');
            return;
        }
        $profile = $this->ruleProfileId === null ? null : RuleProfile::findOne($this->ruleProfileId);
        if ($profile === null) {
            $this->addError($attribute, 'Выберите корректный профиль происхождения предыстории.');
            return;
        }
        sort($ids, SORT_NUMERIC);
        $options = new StartingEquipmentItemOptions();
        $availability = new CatalogAvailability();
        foreach ($ids as $id) {
            $entry = $options->lockAndFind($id);
            if ($entry === null || !$availability->isAvailableInProfile($entry, $profile)) {
                $this->addError($attribute, 'Выберите опубликованные предметы с определением, доступные профилю предыстории.');
                return;
            }
        }
    }
}
