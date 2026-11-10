<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\catalog\CatalogEntry;
use app\models\characters\{Character, CharacterInventoryForm, CharacterInventoryState, CharacterItem};
use app\models\rules\RuleProfile;
use app\services\rules\{CatalogAvailability, StartingEquipmentItemOptions};

class CharacterInventory
{

    public function get(Character $character): array
    {
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $benefits = (new CharacterBackgroundBenefits())->get($character);
        $state = CharacterInventoryState::findOne($character->id);
        $manual = [];
        $other = [];
        $entries = [];
        $availability = new CatalogAvailability();
        $options = new StartingEquipmentItemOptions();
        $manualValid = true;
        foreach (CharacterItem::find()->where(['character_id' => $character->id])->orderBy('id')->all() as $row) {
            if (!array_key_exists($row->item_entry_id, $entries)) {
                $entry = $options->lockAndFind($row->item_entry_id);
                $available = $profile !== null && $entry !== null && $availability->isAvailableInProfile($entry, $profile);
                $display = $entry ?? CatalogEntry::findOne($row->item_entry_id);
                $entries[$row->item_entry_id] = [
                    'available' => $available,
                    'title' => $display !== null && $display->is_published ? $display->title : 'Недоступный предмет (ID ' . $row->item_entry_id . ')',
                    'body' => $display !== null && $display->is_published ? $display->body : null,
                ];
            }
            $data = $row->getAttributes(['id', 'item_entry_id', 'quantity', 'is_equipped', 'note', 'source_kind']) + $entries[$row->item_entry_id];
            $data['source_title'] = match ($row->source_kind) {
                CharacterItem::SOURCE_DRAFT => 'Добавлено в черновике',
                CharacterItem::SOURCE_CREATION => 'Получено при создании',
                default => 'Выдано ведущим',
            };
            if ($row->source_kind === CharacterItem::SOURCE_DRAFT) {
                $manual[] = $data;
                $manualValid = $manualValid && $data['available'];
            } else {
                $other[] = $data;
            }
        }
        $preview = $benefits['equipment']['preview'];
        return [
            'rule_profile_id' => $character->rule_profile_id,
            'background_entry_id' => $character->background_entry_id,
            'background_choice_revision' => $benefits['revision'],
            'revision' => $state === null ? 0 : $state->revision,
            'item_options' => $options->get($profile),
            'manual_items' => $manual,
            'manual_items_valid' => $manualValid,
            'other_items' => $other,
            'background' => [
                'complete' => $benefits['equipment']['complete'],
                'items' => $preview === null ? [] : $preview['items'],
                'money_cp' => $preview === null ? null : $preview['money_cp'],
                'issues' => $benefits['equipment']['issues'],
            ],
            'max_items' => CharacterInventoryForm::MAX_ITEMS,
            'max_quantity' => CharacterItem::MAX_QUANTITY,
            'max_note_length' => CharacterItem::MAX_NOTE_LENGTH,
        ];
    }
}
