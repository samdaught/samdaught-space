<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\characters\{Character, CharacterItem};

class StartingEquipmentWriter
{
    public function save(Character $character, $equipped): void
    {
        if (!is_array($equipped) || !array_is_list($equipped) || count($equipped) > 100 || count(array_unique($equipped, SORT_REGULAR)) !== count($equipped)) {
            throw new CharacterEditorValidationException(['starting_equipped_ids' => ['Передайте список разных экипированных предметов стартовых комплектов.']]);
        }
        foreach ($equipped as $id) {
            if (!is_int($id) || $id < 1 || $id > 2147483647) {
                throw new CharacterEditorValidationException(['starting_equipped_ids' => ['Передайте целые ID экипированных предметов.']]);
            }
        }
        $benefits = (new CharacterBackgroundBenefits())->get($character);
        $creation = (new CreationBenefits())->get($character);
        $packages = (new StartingPackages())->get($character, $creation);
        $items = [];
        foreach (array_merge($benefits['equipment']['preview']['items'] ?? [], $packages['items']) as $item) {
            $id = $item['item_entry_id'] ?? $item['id'];
            $items[$id] = ($items[$id] ?? 0) + $item['quantity'];
        }
        $previous = CharacterItem::find()->where(['character_id' => $character->id, 'source_kind' => CharacterItem::SOURCE_CREATION])->indexBy('item_entry_id')->all();
        foreach ($equipped as $id) {
            if (!isset($items[$id]) && !isset($previous[$id])) {
                throw new CharacterEditorValidationException(['starting_equipped_ids' => ['Можно экипировать только предметы полученных стартовых комплектов.']]);
            }
        }
        foreach ($previous as $id => $row) {
            if (!isset($items[$id])) {
                $row->delete();
            }
        }
        foreach ($items as $id => $quantity) {
            if ($quantity > CharacterItem::MAX_QUANTITY) {
                throw new CharacterEditorValidationException(['equipment_choices' => ['Суммарное количество предмета превышает допустимый предел.']]);
            }
            $row = $previous[$id] ?? new CharacterItem();
            $row->character_id = $character->id;
            $row->item_entry_id = $id;
            $row->quantity = $quantity;
            $row->is_equipped = in_array($id, $equipped, true);
            $row->note = '';
            $row->source_kind = CharacterItem::SOURCE_CREATION;
            if (!$row->save()) {
                throw new \RuntimeException('Не удалось выдать стартовое снаряжение.');
            }
        }
    }
}
