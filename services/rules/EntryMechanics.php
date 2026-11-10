<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{RuleProfile, AbilityDefinition};
use yii\db\Query;

class EntryMechanics
{
    public const BONUSES = ['ability_str' => 'Сила', 'ability_dex' => 'Ловкость', 'ability_con' => 'Телосложение', 'ability_int' => 'Интеллект', 'ability_wis' => 'Мудрость', 'ability_cha' => 'Харизма', 'speed' => 'Скорость, футы', 'armor_class' => 'Класс доспеха', 'initiative' => 'Инициатива', 'spell_attack' => 'Атака заклинанием', 'spell_save' => 'СЛ заклинаний', 'hit_points' => 'Максимальные хиты'];
    public const TRAITS = ['language' => 'Язык', 'armor' => 'Владение доспехами', 'weapon' => 'Владение оружием', 'resistance' => 'Сопротивление', 'sense' => 'Чувство', 'size' => 'Размер'];
    public const MAGIC_CODES = ['magic_cantrips', 'magic_known', 'magic_prepared'];
    public static function empty(): array
    {
        return ['bonuses' => [], 'traits' => [], 'equipment' => [], 'spell' => null, 'spellcasting' => null, 'spell_ids' => [], 'class_ids' => [], 'armor' => null, 'innate_ability_code' => null, 'unarmored_defense' => null];
    }
    public function read(CatalogEntry $entry): array
    {
        $data = self::empty();
        $data['bonuses'] = (new Query())->select(['code', 'amount'])->from('{{%entry_bonus}}')->where(['source_entry_id' => $entry->id])->orderBy('code')->all();
        $data['traits'] = (new Query())->select(['kind', 'value'])->from('{{%entry_trait}}')->where(['source_entry_id' => $entry->id])->orderBy(['kind' => SORT_ASC, 'value' => SORT_ASC])->all();
        $data['equipment'] = $this->packages($entry->id);
        if ($entry->type === 'spell') {
            $data['spell'] = (new Query())->select(['level', 'school', 'casting_time', 'range_text', 'components', 'duration', 'concentration', 'ritual'])->from('{{%spell_definition}}')->where(['catalog_entry_id' => $entry->id])->one() ?: null;
            $data['class_ids'] = (new Query())->select('class_entry_id')->from('{{%class_spell}}')->where(['spell_entry_id' => $entry->id])->orderBy('class_entry_id')->column();
        }
        if ($entry->type === 'class') {
            $data['spellcasting'] = (new Query())->select(['enabled', 'ability_code', 'cantrip_count', 'known_count', 'prepared_count', 'slot_count'])->from('{{%class_spellcasting}}')->where(['class_entry_id' => $entry->id])->one() ?: null;
            $data['spell_ids'] = (new Query())->select('spell_entry_id')->from('{{%class_spell}}')->where(['class_entry_id' => $entry->id])->orderBy('spell_entry_id')->column();
        }
        if ($entry->type === 'item') {
            $data['armor'] = (new Query())->select(['base_ac', 'dexterity_cap', 'is_shield'])->from('{{%armor_definition}}')->where(['catalog_entry_id' => $entry->id])->one() ?: null;
        }
        $data['innate_ability_code'] = (new Query())->select('ability_code')->from('{{%entry_spellcasting}}')->where(['source_entry_id' => $entry->id])->scalar() ?: null;
        $data['unarmored_defense'] = (new Query())->select(['base_ac', 'ability_code', 'allows_shield'])->from('{{%entry_unarmored_defense}}')->where(['source_entry_id' => $entry->id])->one() ?: null;
        return $data;
    }
    public function packages(int $sourceId): array
    {
        $packages = (new Query())->select(['id', 'title', 'money_cp'])->from('{{%entry_equipment_package}}')->where(['source_entry_id' => $sourceId, 'enabled' => true])->orderBy('id')->all();
        foreach ($packages as &$package) {
            $package['items'] = (new Query())->select(['item_entry_id', 'quantity'])->from('{{%entry_equipment_package_item}}')->where(['package_id' => $package['id']])->orderBy('item_entry_id')->all();
        }
        return $packages;
    }
    public function options(?RuleProfile $profile): array
    {
        $entries = [];
        if ($profile !== null) {
            foreach (CatalogEntry::find()->where(['type' => ['class', 'spell', 'item']])->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC])->all() as $entry) {
                if (!(new CatalogAvailability())->hasCompatibleOrigin($entry, $profile)) {
                    continue;
                }
                $table = ['class' => 'class_definition', 'spell' => 'spell_definition', 'item' => 'item_definition'][$entry->type];
                if (!(new Query())->from('{{%' . $table . '}}')->where(['catalog_entry_id' => $entry->id])->exists()) {
                    continue;
                }
                $entries[] = $entry->getAttributes(['id', 'title', 'type', 'is_published']);
            }
        }
        return ['entries' => $entries, 'abilities' => AbilityDefinition::find()->select(['code', 'title'])->orderBy('code')->asArray()->all(), 'bonuses' => self::BONUSES, 'traits' => self::TRAITS,'vocabulary'=>(new Query())->from('{{%rule_vocabulary}}')->all()];
    }
    public function validate(CatalogEntry $entry, $data): array
    {
        $this->keys($data, array_keys(self::empty()));
        foreach (['bonuses', 'traits', 'equipment', 'class_ids', 'spell_ids'] as $key) {
            $this->list($data[$key],in_array($key,['class_ids','spell_ids'],true)?10000:100);
        }
        $source = in_array($entry->type, CreationRules::SOURCE_TYPES, true);
        $this->check($source || $entry->type === 'item' || !$data['bonuses'] && !$data['traits'], 'Эта категория не может давать бонусы или владения.');
        $this->check($source || !$data['equipment'], 'Стартовые комплекты задаются у вида, класса, предыстории, особенности или черты.');
        $this->check($source || !$data['traits'], 'Языки и владения задаются у происхождения, класса, черт и особенностей.');
        if ($data['innate_ability_code'] !== null) {
            $this->check($source && is_string($data['innate_ability_code']) && AbilityDefinition::find()->where(['code' => $data['innate_ability_code']])->exists(), 'Выберите магическую характеристику врождённых способностей.');
        }
        if ($data['unarmored_defense'] !== null) {
            $this->check($source, 'Защита без доспехов задаётся у вида, класса или особенности.');
            $this->keys($data['unarmored_defense'], ['base_ac', 'ability_code', 'allows_shield']);
            $this->integer($data['unarmored_defense']['base_ac'], 0, 30);
            $this->check(is_bool($data['unarmored_defense']['allows_shield']), 'Укажите совместимость защиты со щитом.');
            $code = $data['unarmored_defense']['ability_code'];
            $this->check($code === null || is_string($code) && AbilityDefinition::find()->where(['code' => $code])->exists(), 'Выберите характеристику защиты без доспехов.');
        }
        $codes = [];
        foreach ($data['bonuses'] as $bonus) {
            $this->keys($bonus, ['code', 'amount']);
            $this->check(is_string($bonus['code']) && isset(self::BONUSES[$bonus['code']]), 'Выберите вид бонуса.');
            $this->check($entry->type !== 'item' || in_array($bonus['code'], ['armor_class', 'speed', 'initiative', 'spell_attack', 'spell_save'], true), 'Для экипировки доступны бонусы КД, скорости, инициативы и заклинательства.');
            $this->integer($bonus['amount'], -100, 100);
            $codes[] = $bonus['code'];
        }
        $this->check(count($codes) === count(array_unique($codes)), 'Бонус одного вида задаётся один раз у записи.');
        $traits = [];
        foreach ($data['traits'] as $trait) {
            $this->keys($trait, ['kind', 'value']);
            $this->check(is_string($trait['kind']) && isset(self::TRAITS[$trait['kind']]), 'Выберите вид владения или свойства.');
            $this->text($trait['value'], 255);
            $traits[] = $trait['kind'] . ':' . trim($trait['value']);
        }
        $this->check(count($traits) === count(array_unique($traits)), 'Владения и свойства не должны повторяться.');
        $packageIds = [];
        foreach ($data['equipment'] as $package) {
            $this->keys($package, ['id', 'title', 'money_cp', 'items']);
            $this->text($package['title'], 120);
            $this->integer($package['money_cp'], 0, 100000000);
            $this->list($package['items']);
            if ($package['id'] !== null) {
                $this->integer($package['id'], 1, 2147483647);
                $this->check(!$entry->isNewRecord && (new Query())->from('{{%entry_equipment_package}}')->where(['id' => $package['id'], 'source_entry_id' => $entry->id])->exists(), 'Комплект принадлежит другой записи.');
                $packageIds[] = $package['id'];
            }
            $ids = [];
            foreach ($package['items'] as $item) {
                $this->keys($item, ['item_entry_id', 'quantity']);
                $this->integer($item['quantity'], 1, 10000);
                $this->reference($item['item_entry_id'], 'item', $entry);
                $ids[] = $item['item_entry_id'];
            }
            $this->check(count($ids) === count(array_unique($ids)), 'Объедините одинаковые предметы комплекта в одну строку.');
        }
        $this->check(count($packageIds) === count(array_unique($packageIds)), 'Один комплект указан повторно.');
        if ($entry->type === 'spell') {
            $this->keys($data['spell'], ['level', 'school', 'casting_time', 'range_text', 'components', 'duration', 'concentration', 'ritual']);
            $this->integer($data['spell']['level'], 0, 9);
            foreach (['school' => 100, 'casting_time' => 255, 'range_text' => 255, 'components' => 1000, 'duration' => 255] as $key => $max) {
                $this->text($data['spell'][$key], $max, true);
            }
            $this->check(is_bool($data['spell']['concentration']) && is_bool($data['spell']['ritual']), 'Проверьте признаки концентрации и ритуала.');
            foreach ($data['class_ids'] as $id) {
                $this->reference($id, 'class', $entry, true);
            }
            $this->check(count($data['class_ids']) === count(array_unique($data['class_ids'])), 'Классы не должны повторяться.');
        } else {
            $this->check($data['spell'] === null && !$data['class_ids'], 'Параметры заклинания задаются только у заклинания.');
        }
        if ($entry->type === 'class') {
            $casting = $data['spellcasting'];
            $this->keys($casting, ['enabled', 'ability_code', 'cantrip_count', 'known_count', 'prepared_count', 'slot_count']);
            $this->check(is_bool($casting['enabled']), 'Укажите, использует ли класс магию на первом уровне.');
            foreach (['cantrip_count', 'known_count', 'prepared_count', 'slot_count'] as $key) {
                $this->integer($casting[$key], 0, 100);
            }
            $this->check($casting['enabled'] ? is_string($casting['ability_code']) && AbilityDefinition::find()->where(['code' => $casting['ability_code']])->exists() : $casting['ability_code'] === null && array_sum(array_intersect_key($casting, array_flip(['cantrip_count', 'known_count', 'prepared_count', 'slot_count']))) === 0, 'Укажите магическую характеристику; для немагического класса количества должны быть нулевыми.');
            $this->check(!$casting['known_count'] || $casting['prepared_count'] <= $casting['known_count'], 'Нельзя подготовить больше заклинаний, чем изучено.');
            foreach ($data['spell_ids'] as $id) {
                $this->reference($id, 'spell', $entry);
            }
            $this->check(count($data['spell_ids']) === count(array_unique($data['spell_ids'])), 'Заклинания не должны повторяться.');
        } else {
            $this->check($data['spellcasting'] === null && !$data['spell_ids'], 'Список заклинаний и заклинательство задаются только у класса.');
        }
        if ($data['armor'] !== null) {
            $this->check($entry->type === 'item', 'Защита задаётся только у предмета.');
            $this->keys($data['armor'], ['base_ac', 'dexterity_cap', 'is_shield']);
            $this->integer($data['armor']['base_ac'], 0, 30);
            if ($data['armor']['dexterity_cap'] !== null) {
                $this->integer($data['armor']['dexterity_cap'], 0, 10);
            }
            $this->check(is_bool($data['armor']['is_shield']), 'Укажите, является ли предмет щитом.');
        }
        return $data;
    }
    public function save(CatalogEntry $entry, array $data): void
    {
        Yii::$app->db->transaction(function () use ($entry, $data): void {
            $db = Yii::$app->db;
            $db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]]=:id FOR UPDATE', [':id' => $entry->id])->queryScalar();
            foreach (['entry_bonus', 'entry_trait'] as $table) {
                $db->createCommand()->delete('{{%' . $table . '}}', ['source_entry_id' => $entry->id])->execute();
            }
            foreach ($data['bonuses'] as $row) {
                $db->createCommand()->insert('{{%entry_bonus}}', ['source_entry_id' => $entry->id] + $row)->execute();
            }
            foreach ($data['traits'] as $row) {
                $row['value'] = trim($row['value']);
                $db->createCommand()->insert('{{%entry_trait}}', ['source_entry_id' => $entry->id] + $row)->execute();
            }
            $db->createCommand()->update('{{%entry_equipment_package}}', ['enabled' => false], ['source_entry_id' => $entry->id])->execute();
            foreach ($data['equipment'] as $package) {
                $id = $package['id'];
                $values = ['source_entry_id' => $entry->id, 'title' => trim($package['title']), 'money_cp' => $package['money_cp'], 'enabled' => true];
                if ($id === null) {
                    $db->createCommand()->insert('{{%entry_equipment_package}}', $values)->execute();
                    $id = (int) $db->getLastInsertID();
                } else {
                    $db->createCommand()->update('{{%entry_equipment_package}}', $values, ['id' => $id, 'source_entry_id' => $entry->id])->execute();
                }
                $db->createCommand()->delete('{{%entry_equipment_package_item}}', ['package_id' => $id])->execute();
                foreach ($package['items'] as $item) {
                    $db->createCommand()->insert('{{%entry_equipment_package_item}}', ['package_id' => $id] + $item)->execute();
                }
            }
            if ($data['innate_ability_code'] === null) {
                $db->createCommand()->delete('{{%entry_spellcasting}}', ['source_entry_id' => $entry->id])->execute();
            } else {
                $db->createCommand()->upsert('{{%entry_spellcasting}}', ['source_entry_id' => $entry->id, 'ability_code' => $data['innate_ability_code']])->execute();
            }
            if ($data['unarmored_defense'] === null) {
                $db->createCommand()->delete('{{%entry_unarmored_defense}}', ['source_entry_id' => $entry->id])->execute();
            } else {
                $db->createCommand()->upsert('{{%entry_unarmored_defense}}', ['source_entry_id' => $entry->id] + $data['unarmored_defense'])->execute();
            }
            if ($entry->type === 'spell') {
                $oldLinks=(new Query())->select('class_entry_id')->from('{{%class_spell}}')->where(['spell_entry_id'=>$entry->id])->column();
                $changedPeers=array_unique(array_merge(array_diff($oldLinks,$data['class_ids']),array_diff($data['class_ids'],$oldLinks)));
                if($changedPeers)$db->createCommand()->update('{{%catalog_entry}}',['revision'=>new \yii\db\Expression('[[revision]] + 1')],['id'=>$changedPeers])->execute();
                $db->createCommand()->upsert('{{%spell_definition}}', ['catalog_entry_id' => $entry->id] + $data['spell'])->execute();
                $db->createCommand()->delete('{{%class_spell}}', ['spell_entry_id' => $entry->id])->execute();
                foreach ($data['class_ids'] as $id) {
                    $db->createCommand()->insert('{{%class_spell}}', ['class_entry_id' => $id, 'spell_entry_id' => $entry->id])->execute();
                }
            }
            if ($entry->type === 'class') {
                $oldLinks=(new Query())->select('spell_entry_id')->from('{{%class_spell}}')->where(['class_entry_id'=>$entry->id])->column();
                $changedPeers=array_unique(array_merge(array_diff($oldLinks,$data['spell_ids']),array_diff($data['spell_ids'],$oldLinks)));
                if($changedPeers)$db->createCommand()->update('{{%catalog_entry}}',['revision'=>new \yii\db\Expression('[[revision]] + 1')],['id'=>$changedPeers])->execute();
                $db->createCommand()->upsert('{{%class_spellcasting}}', ['class_entry_id' => $entry->id] + $data['spellcasting'])->execute();
                $db->createCommand()->delete('{{%class_spell}}', ['class_entry_id' => $entry->id])->execute();
                foreach ($data['spell_ids'] as $id) {
                    $db->createCommand()->insert('{{%class_spell}}', ['class_entry_id' => $entry->id, 'spell_entry_id' => $id])->execute();
                }
            }
            if ($entry->type === 'item') {
                if ($data['armor'] === null) {
                    $db->createCommand()->delete('{{%armor_definition}}', ['catalog_entry_id' => $entry->id])->execute();
                } else {
                    $db->createCommand()->upsert('{{%armor_definition}}', ['catalog_entry_id' => $entry->id] + $data['armor'],$data['armor'])->execute();
                }
            }
        });
    }
    private function reference($id, string $type, CatalogEntry $source, bool $reverse = false): void
    {
        $this->integer($id, 1, 2147483647);
        Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]]=:id FOR SHARE', [':id' => $id])->queryScalar();
        $entry = CatalogEntry::findOne($id);
        $this->check($entry !== null && $entry->type === $type, 'Выберите связанную запись подходящей категории.');
        $profile = RuleProfile::findOne($reverse ? $entry->origin_profile_id : $source->origin_profile_id);
        $this->check($profile !== null && (new CatalogAvailability())->hasCompatibleOrigin($reverse ? $source : $entry, $profile), 'Связанная запись недоступна в мире источника.');
        $table = ['class' => 'class_definition', 'spell' => 'spell_definition', 'item' => 'item_definition'][$type];
        $this->check((new Query())->from('{{%' . $table . '}}')->where(['catalog_entry_id' => $id])->exists(), 'Сначала настройте механику связанной записи.');
    }
    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException($message);
        }
    }
    private function keys($data, array $keys): void
    {
        $this->check(is_array($data) && count($data) === count($keys) && !array_diff($keys, array_keys($data)), 'Передайте все поля механики без дополнительных полей.');
    }
    private function list($data,int $maximum=100): void
    {
        $this->check(is_array($data) && array_is_list($data) && count($data) <= $maximum, 'Размер списка превышает допустимый предел.');
    }
    private function integer($value, int $min, int $max): void
    {
        $this->check(is_int($value) && $value >= $min && $value <= $max, 'Числовые параметры должны быть целыми и находиться в допустимом диапазоне.');
    }
    private function text($value, int $max, bool $empty = false): void
    {
        $this->check(is_string($value) && ($empty || trim($value) !== '') && mb_strlen($value) <= $max, 'Проверьте заполнение и длину текстовых параметров.');
    }
}
