<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\rules\RuleProfile;
use app\services\rules\{CatalogAvailability, StartingEquipmentItemOptions};
use yii\base\Model;

class CharacterInventoryForm extends Model
{
    public const MAX_ITEMS = 100;
    public $items = null;
    public ?RuleProfile $profile = null;

    public array $ownedRows = [];

    public function rules(): array
    {
        return [['items', 'validateItems', 'skipOnEmpty' => false]];
    }

    public function validateItems($attribute, $params): void
    {
        $items = $this->$attribute;
        if (!is_array($items) || !array_is_list($items) || count($items) > self::MAX_ITEMS) {
            $this->addError($attribute, 'Передайте список предметов в пределах допустимого числа строк.');
            return;
        }
        if ($this->profile === null) {
            $this->addError($attribute, 'Мир правил персонажа недоступен.');
            return;
        }
        $rowIds = [];
        $entryIds = [];
        $keys = ['id', 'item_entry_id', 'quantity', 'is_equipped', 'note'];
        foreach ($items as $line) {
            if (!is_array($line) || count($line) !== count($keys) || array_diff($keys, array_keys($line))) {
                $this->addError($attribute, 'В каждой строке нужны id, item_entry_id, quantity, is_equipped и note. Для новой строки id должен быть null.');
                return;
            }
            if ($line['id'] !== null) {
                if (!is_int($line['id']) || $line['id'] < 1 || !isset($this->ownedRows[$line['id']])) {
                    $this->addError($attribute, 'Строка инвентаря не принадлежит этому персонажу или недоступна для изменения.');
                    return;
                }
                $rowIds[] = $line['id'];
            }
            if (!is_int($line['item_entry_id']) || $line['item_entry_id'] < 1
                || !is_int($line['quantity']) || $line['quantity'] < 1 || $line['quantity'] > CharacterItem::MAX_QUANTITY
                || !is_bool($line['is_equipped']) || !is_string($line['note']) || mb_strlen($line['note'], 'UTF-8') > CharacterItem::MAX_NOTE_LENGTH) {
                $this->addError($attribute, 'Выберите предмет, положительное целое количество, отметку экипировки и заметку допустимой длины.');
                return;
            }
            $entryIds[] = $line['item_entry_id'];
        }
        if (count(array_unique($rowIds)) !== count($rowIds)) {
            $this->addError($attribute, 'Одна сохранённая строка передана несколько раз.');
            return;
        }
        $entryIds = array_unique($entryIds);
        sort($entryIds, SORT_NUMERIC);
        $options = new StartingEquipmentItemOptions();
        $availability = new CatalogAvailability();
        foreach ($entryIds as $id) {
            $entry = $options->lockAndFind($id);
            if ($entry === null || !$availability->isAvailableInProfile($entry, $this->profile)) {
                $this->addError($attribute, 'В инвентаре есть недоступный предмет. Выберите опубликованные предметы с определением, доступные в мире персонажа.');
                return;
            }
        }
    }

    public function normalizedItems(): array
    {
        $items = [];
        foreach ($this->items as $line) {
            $items[] = ['id' => $line['id'], 'item_entry_id' => $line['item_entry_id'], 'quantity' => $line['quantity'],
                'is_equipped' => $line['is_equipped'], 'note' => trim($line['note'])];
        }
        return $items;
    }
}
