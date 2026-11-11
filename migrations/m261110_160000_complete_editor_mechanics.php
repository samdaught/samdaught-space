<?php

use yii\db\Migration;

class m261110_160000_complete_editor_mechanics extends Migration
{
    public function safeUp()
    {
        $this->createIndex('ux_catalog_entry_id_type', '{{%catalog_entry}}', ['id', 'type'], true);
        $this->createTable('{{%spell_definition}}', ['catalog_entry_id' => $this->integer()->notNull(), 'entry_type' => $this->string(32)->notNull()->defaultValue('spell'), 'level' => $this->smallInteger()->notNull(), 'school' => $this->string(100)->notNull()->defaultValue(''), 'casting_time' => $this->string(255)->notNull()->defaultValue(''), 'range_text' => $this->string(255)->notNull()->defaultValue(''), 'components' => $this->string(1000)->notNull()->defaultValue(''), 'duration' => $this->string(255)->notNull()->defaultValue(''), 'concentration' => $this->boolean()->notNull()->defaultValue(false), 'ritual' => $this->boolean()->notNull()->defaultValue(false)]);
        $this->addPrimaryKey('pk_spell_definition', '{{%spell_definition}}', 'catalog_entry_id');
        $this->addCheck('ck_spell_definition_type', '{{%spell_definition}}', "[[entry_type]] = 'spell'");
        $this->addCheck('ck_spell_definition_level', '{{%spell_definition}}', '[[level]] BETWEEN 0 AND 9');
        $this->addForeignKey('fk_spell_definition_entry', '{{%spell_definition}}', ['catalog_entry_id', 'entry_type'], '{{%catalog_entry}}', ['id', 'type'], 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%class_spell}}', ['class_entry_id' => $this->integer()->notNull(), 'spell_entry_id' => $this->integer()->notNull()]);
        $this->addPrimaryKey('pk_class_spell', '{{%class_spell}}', ['class_entry_id', 'spell_entry_id']);
        $this->createIndex('ix_class_spell_spell', '{{%class_spell}}', 'spell_entry_id');
        $this->addForeignKey('fk_class_spell_class', '{{%class_spell}}', 'class_entry_id', '{{%class_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_class_spell_spell', '{{%class_spell}}', 'spell_entry_id', '{{%spell_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_spellcasting}}', ['source_entry_id' => $this->integer()->notNull(), 'ability_code' => $this->string(3)->notNull()]);
        $this->addPrimaryKey('pk_entry_spellcasting', '{{%entry_spellcasting}}', 'source_entry_id');
        $this->addForeignKey('fk_entry_spellcasting_source', '{{%entry_spellcasting}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_spellcasting_ability', '{{%entry_spellcasting}}', 'ability_code', '{{%ability_definition}}', 'code', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_unarmored_defense}}', ['source_entry_id' => $this->integer()->notNull(), 'base_ac' => $this->smallInteger()->notNull(), 'ability_code' => $this->string(3), 'allows_shield' => $this->boolean()->notNull()]);
        $this->addPrimaryKey('pk_entry_unarmored_defense', '{{%entry_unarmored_defense}}', 'source_entry_id');
        $this->addForeignKey('fk_entry_unarmored_source', '{{%entry_unarmored_defense}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_unarmored_ability', '{{%entry_unarmored_defense}}', 'ability_code', '{{%ability_definition}}', 'code', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_unarmored_base', '{{%entry_unarmored_defense}}', '[[base_ac]] BETWEEN 0 AND 30');
        $this->createTable('{{%entry_bonus}}', ['source_entry_id' => $this->integer()->notNull(), 'code' => $this->string(32)->notNull(), 'amount' => $this->integer()->notNull()]);
        $this->addPrimaryKey('pk_entry_bonus', '{{%entry_bonus}}', ['source_entry_id', 'code']);
        $this->addForeignKey('fk_entry_bonus_source', '{{%entry_bonus}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_bonus_code', '{{%entry_bonus}}', "[[code]] IN ('ability_str','ability_dex','ability_con','ability_int','ability_wis','ability_cha','speed','armor_class','initiative','spell_attack','spell_save','hit_points')");
        $this->addCheck('ck_entry_bonus_amount', '{{%entry_bonus}}', '[[amount]] BETWEEN -100 AND 100');
        $this->createTable('{{%entry_trait}}', ['source_entry_id' => $this->integer()->notNull(), 'kind' => $this->string(32)->notNull(), 'value' => $this->string(255)->notNull()]);
        $this->addPrimaryKey('pk_entry_trait', '{{%entry_trait}}', ['source_entry_id', 'kind', 'value']);
        $this->addForeignKey('fk_entry_trait_source', '{{%entry_trait}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_trait_kind', '{{%entry_trait}}', "[[kind]] IN ('language','armor','weapon','resistance','sense','size')");
        $this->createTable('{{%entry_equipment_package}}', ['id' => $this->primaryKey(), 'source_entry_id' => $this->integer()->notNull(), 'title' => $this->string(120)->notNull(), 'enabled' => $this->boolean()->notNull()->defaultValue(true), 'money_cp' => $this->integer()->notNull()->defaultValue(0)]);
        $this->createIndex('ix_entry_equipment_source', '{{%entry_equipment_package}}', 'source_entry_id');
        $this->addForeignKey('fk_entry_equipment_source', '{{%entry_equipment_package}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_equipment_money', '{{%entry_equipment_package}}', '[[money_cp]] BETWEEN 0 AND 100000000');
        $this->createTable('{{%entry_equipment_package_item}}', ['package_id' => $this->integer()->notNull(), 'item_entry_id' => $this->integer()->notNull(), 'quantity' => $this->integer()->notNull()]);
        $this->addPrimaryKey('pk_entry_equipment_item', '{{%entry_equipment_package_item}}', ['package_id', 'item_entry_id']);
        $this->addForeignKey('fk_entry_equipment_item_package', '{{%entry_equipment_package_item}}', 'package_id', '{{%entry_equipment_package}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_equipment_item_item', '{{%entry_equipment_package_item}}', 'item_entry_id', '{{%item_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createIndex('ix_entry_equipment_item_item', '{{%entry_equipment_package_item}}', 'item_entry_id');
        $this->addCheck('ck_entry_equipment_item_quantity', '{{%entry_equipment_package_item}}', '[[quantity]] BETWEEN 1 AND 10000');
        $this->createTable('{{%character_equipment_package}}', ['character_id' => $this->integer()->notNull(), 'source_entry_id' => $this->integer()->notNull(), 'package_id' => $this->integer()->notNull()]);
        $this->addPrimaryKey('pk_character_equipment_package', '{{%character_equipment_package}}', ['character_id', 'source_entry_id']);
        $this->createIndex('ux_entry_equipment_id_source', '{{%entry_equipment_package}}', ['id', 'source_entry_id'], true);
        $this->addForeignKey('fk_character_equipment_character', '{{%character_equipment_package}}', 'character_id', '{{%character}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_character_equipment_package', '{{%character_equipment_package}}', ['package_id', 'source_entry_id'], '{{%entry_equipment_package}}', ['id', 'source_entry_id'], 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%armor_definition}}', ['catalog_entry_id' => $this->integer()->notNull(), 'base_ac' => $this->smallInteger()->notNull(), 'dexterity_cap' => $this->smallInteger(), 'is_shield' => $this->boolean()->notNull()->defaultValue(false)]);
        $this->addPrimaryKey('pk_armor_definition', '{{%armor_definition}}', 'catalog_entry_id');
        $this->addForeignKey('fk_armor_definition_item', '{{%armor_definition}}', 'catalog_entry_id', '{{%item_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_armor_definition_ac', '{{%armor_definition}}', '[[base_ac]] BETWEEN 0 AND 30 AND ([[dexterity_cap]] IS NULL OR [[dexterity_cap]] BETWEEN 0 AND 10)');
    }
    public function safeDown()
    {
        foreach (['entry_unarmored_defense', 'entry_spellcasting', 'armor_definition', 'character_equipment_package', 'entry_equipment_package_item', 'entry_equipment_package', 'entry_trait', 'entry_bonus', 'class_spell', 'spell_definition'] as $table) {
            $this->dropTable('{{%' . $table . '}}');
        }
        $this->dropIndex('ux_catalog_entry_id_type', '{{%catalog_entry}}');
    }
}
