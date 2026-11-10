<?php

declare(strict_types=1);

namespace app\services\characters;

use Yii;
use app\models\characters\Character;
use app\models\rules\RuleProfile;
use app\services\rules\{CreationRules, EntryMechanics, StartingEquipmentItemOptions};
use yii\db\Query;

class StartingPackages
{
    public function get(Character $character, array $creation, $selection = null): array
    {
        if ($selection === null) {
            $selection = (new Query())->select('package_id')->from('{{%character_equipment_package}}')->where(['character_id' => $character->id])->indexBy('source_entry_id')->column();
        }
        $errors = [];
        $issues = [];
        $groups = [];
        $items = [];
        $money = 0;
        if (!is_array($selection) || count($selection) > 100) {
            $errors[] = 'Передайте допустимый набор стартовых комплектов.';
            $selection = [];
        }
        $ids = array_filter([$character->species_entry_id, $character->class_entry_id, $character->background_entry_id]);
        foreach ($creation['entries'] as $entry) {
            if (in_array($entry['type'], CreationRules::SOURCE_TYPES, true)) {
                $ids[] = $entry['id'];
            }
        }
        $profile = RuleProfile::findOne($character->rule_profile_id);
        $allowed = [];
        if ($profile !== null) {
            foreach (array_unique($ids) as $id) {
                $entry = (new CreationRules())->findAvailable($id, $profile);
                if ($entry === null) {
                    continue;
                }
                $packages = (new EntryMechanics())->packages($id);
                if (!$packages) {
                    continue;
                }
                foreach ($packages as &$package) {
                    $package['available'] = true;
                    foreach ($package['items'] as &$line) {
                        $item = (new StartingEquipmentItemOptions())->lockAndFind($line['item_entry_id']);
                        $valid = $item !== null && (new \app\services\rules\CatalogAvailability())->isAvailableInProfile($item, $profile);
                        $package['available'] = $package['available'] && $valid;
                        $line['title'] = $valid ? $item->title : 'Недоступный предмет';
                    }
                    unset($line);
                }
                unset($package);
                $allowed[(string) $id] = true;
                $selected = $selection[$id] ?? null;
                if ($selected !== null && (!is_int($selected) || !in_array($selected, array_column($packages, 'id'), true))) {
                    $errors[] = 'Выберите существующий комплект «' . $entry->title . '».';
                }
                $package = null;
                foreach ($packages as $candidate) {
                    if ($candidate['id'] === $selected && $candidate['available']) {
                        $package = $candidate;
                    }
                }
                if ($package === null) {
                    $issues[] = 'Выберите доступное стартовое снаряжение: ' . $entry->title . '.';
                } else {
                    $money += $package['money_cp'];
                    foreach ($package['items'] as $line) {
                        $items[] = $line + ['source_title' => $entry->title];
                    }
                }
                $groups[] = ['source_id' => $id, 'source_title' => $entry->title, 'options' => $packages, 'selected_id' => $selected];
            }
        }
        foreach ($selection as $source => $package) {
            if (!isset($allowed[(string) $source])) {
                $errors[] = 'Уберите устаревший выбор стартового комплекта.';
            }
        }
        return ['groups' => $groups, 'selections' => $selection, 'items' => $items, 'money_cp' => $money, 'complete' => !$errors && !$issues, 'issues' => array_merge($issues, $errors), 'validation_errors' => $errors];
    }
    public function save(Character $character, array $selection): void
    {
        Yii::$app->db->createCommand()->delete('{{%character_equipment_package}}', ['character_id' => $character->id])->execute();
        foreach ($selection as $source => $id) {
            Yii::$app->db->createCommand()->insert('{{%character_equipment_package}}', ['character_id' => $character->id, 'source_entry_id' => (int) $source, 'package_id' => $id])->execute();
        }
    }
}
