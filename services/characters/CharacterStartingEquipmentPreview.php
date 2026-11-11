<?php

declare(strict_types=1);

namespace app\services\characters;

use app\models\catalog\{BackgroundStartingEquipmentForm, CatalogEntry};
use app\models\characters\CharacterBackgroundChoice;
use app\models\rules\{BackgroundStartingEquipment, RuleProfile};

class CharacterStartingEquipmentPreview
{

    public function get(?array $definition, ?array $tool, ?string $choice, bool $toolConfigured, RuleProfile $profile): array
    {
        $issues = [];
        $packageAvailable = false;
        $items = [];
        if ($definition !== null) {
            $form = new BackgroundStartingEquipmentForm();
            $form->ruleProfileId = $profile->id;
            $form->backgroundToolConfigured = $toolConfigured;
            $form->starting_equipment = $definition;
            $packageAvailable = $form->validate();
            if ($packageAvailable) {
                foreach ($definition['items'] as $line) {
                    $entry = CatalogEntry::findOne($line['item_entry_id']);
                    $items[$entry->id] = ['id' => $entry->id, 'title' => $entry->title, 'quantity' => $line['quantity']];
                }
                if ($definition['include_background_tool'] && $tool !== null) {
                    $quantity = ($items[$tool['id']]['quantity'] ?? 0) + 1;
                    if ($quantity > BackgroundStartingEquipment::MAX_INTEGER) {
                        $packageAvailable = false;
                        $issues[] = 'Количество выбранного инструмента превышает допустимый предел.';
                    } else {
                        $items[$tool['id']] = ['id' => $tool['id'], 'title' => $tool['title'], 'quantity' => $quantity];
                    }
                }
            } elseif ($choice !== CharacterBackgroundChoice::MONEY) {
                $issues[] = 'Комплект А сейчас недоступен: проверьте его предметы и правило инструмента.';
            }
        } else {
            $issues[] = 'Для предыстории не настроены варианты стартового снаряжения.';
        }

        $preview = null;
        if ($definition !== null && $choice === CharacterBackgroundChoice::MONEY) {
            $preview = ['items' => [], 'money_cp' => $definition['alternative_money_cp']];
        } elseif ($definition !== null && $choice === CharacterBackgroundChoice::PACKAGE && $packageAvailable
            && (!$definition['include_background_tool'] || $tool !== null)) {
            $preview = ['items' => array_values($items), 'money_cp' => $definition['package_money_cp']];
        }
        if ($definition !== null && $choice === null) {
            $issues[] = 'Выберите комплект А или денежную альтернативу Б.';
        }
        if ($definition !== null && $choice === CharacterBackgroundChoice::PACKAGE
            && $definition['include_background_tool'] && $tool === null) {
            $issues[] = 'Для сборки комплекта А нужен допустимый выбранный инструмент.';
        }

        return [
            'configured' => $definition !== null,
            'choice' => $choice,
            'package_available' => $packageAvailable,
            'money_available' => $definition !== null,
            'package' => $definition === null ? null : [
                'items' => array_values($items),
                'money_cp' => $definition['package_money_cp'],
                'include_background_tool' => $definition['include_background_tool'],
            ],
            'alternative_money_cp' => $definition === null ? null : $definition['alternative_money_cp'],
            'preview' => $preview,
            'complete' => $preview !== null,
            'issues' => $issues,
        ];
    }
}
