<?php

use yii\db\Migration;

class m261109_140000_create_character_choices_and_sheets extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%character_background_choice}}', [
            'character_id' => $this->integer()->notNull(),
            'background_entry_id' => $this->integer()->notNull(),
            'rule_profile_id' => $this->integer()->notNull(),
            'tool_entry_id' => $this->integer(),
            'equipment_choice' => $this->string(16),
            'revision' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_character_background_choice', '{{%character_background_choice}}', 'character_id');
        foreach (['background_entry_id', 'rule_profile_id', 'tool_entry_id'] as $column) {
            $this->createIndex(
                'ix_character_background_choice_' . $column,
                '{{%character_background_choice}}',
                $column
            );
        }
        $this->addForeignKey(
            'fk_character_background_choice_character',
            '{{%character_background_choice}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_background_choice_background',
            '{{%character_background_choice}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_background_choice_profile',
            '{{%character_background_choice}}',
            'rule_profile_id',
            '{{%rule_profile}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_background_choice_tool',
            '{{%character_background_choice}}',
            'tool_entry_id',
            '{{%tool_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_character_background_choice_revision',
            '{{%character_background_choice}}',
            '[[revision]] >= 1'
        );
        $this->addCheck(
            'ck_character_background_choice_equipment',
            '{{%character_background_choice}}',
            "[[equipment_choice]] IS NULL OR [[equipment_choice]] IN ('package', 'money')"
        );

        $this->createTable('{{%character_inventory_state}}', [
            'character_id' => $this->integer()->notNull(),
            'revision' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_character_inventory_state', '{{%character_inventory_state}}', 'character_id');
        $this->addForeignKey(
            'fk_character_inventory_state_character',
            '{{%character_inventory_state}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck('ck_character_inventory_revision', '{{%character_inventory_state}}', '[[revision]] >= 1');
        $this->createTable('{{%character_item}}', [
            'id' => $this->primaryKey(),
            'character_id' => $this->integer()->notNull(),
            'item_entry_id' => $this->integer()->notNull(),
            'quantity' => $this->integer()->notNull(),
            'is_equipped' => $this->boolean()->notNull()->defaultValue(false),
            'note' => $this->string(1000)->notNull()->defaultValue(''),
            'source_kind' => $this->string(16)->notNull(),
        ]);
        $this->createIndex('ix_character_item_owner_source', '{{%character_item}}', ['character_id', 'source_kind']);
        $this->createIndex('ix_character_item_entry', '{{%character_item}}', 'item_entry_id');
        $this->addForeignKey(
            'fk_character_item_character',
            '{{%character_item}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_item_entry',
            '{{%character_item}}',
            'item_entry_id',
            '{{%item_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck('ck_character_item_quantity', '{{%character_item}}', '[[quantity]] > 0');
        $this->addCheck(
            'ck_character_item_source',
            '{{%character_item}}',
            "[[source_kind]] IN ('draft', 'creation', 'gm')"
        );

        $this->createTable('{{%character_class_skill_choice}}', [
            'character_id' => $this->integer()->notNull(),
            'class_entry_id' => $this->integer()->notNull(),
            'rule_profile_id' => $this->integer()->notNull(),
            'revision' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_character_class_skill_choice', '{{%character_class_skill_choice}}', 'character_id');
        foreach (['class_entry_id', 'rule_profile_id'] as $column) {
            $this->createIndex(
                'ix_character_class_skill_choice_' . $column,
                '{{%character_class_skill_choice}}',
                $column
            );
        }
        $this->addForeignKey(
            'fk_character_class_skill_character',
            '{{%character_class_skill_choice}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_class_skill_class',
            '{{%character_class_skill_choice}}',
            'class_entry_id',
            '{{%class_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_class_skill_profile',
            '{{%character_class_skill_choice}}',
            'rule_profile_id',
            '{{%rule_profile}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck('ck_character_class_skill_revision', '{{%character_class_skill_choice}}', '[[revision]] >= 1');
        $this->createTable('{{%character_class_skill}}', [
            'character_id' => $this->integer()->notNull(),
            'skill_entry_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey(
            'pk_character_class_skill',
            '{{%character_class_skill}}',
            ['character_id', 'skill_entry_id']
        );
        $this->createIndex('ix_character_class_skill_skill', '{{%character_class_skill}}', 'skill_entry_id');
        $this->addForeignKey(
            'fk_character_class_skill_choice',
            '{{%character_class_skill}}',
            'character_id',
            '{{%character_class_skill_choice}}',
            'character_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_class_skill_skill',
            '{{%character_class_skill}}',
            'skill_entry_id',
            '{{%skill_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%character_creation_choice}}', ['character_id' => $this->integer()->notNull(),
            'source_entry_id' => $this->integer()->notNull(),
            'code' => $this->string(32)->notNull(),
            'entry_id' => $this->integer()->notNull()]);
        $this->addPrimaryKey(
            'pk_character_creation_choice',
            '{{%character_creation_choice}}',
            ['character_id', 'source_entry_id', 'code', 'entry_id']
        );
        $this->addForeignKey(
            'fk_character_creation_choice_character',
            '{{%character_creation_choice}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        // Сохраняем выбор при изменении правила, чтобы пользователь мог его исправить.
        $this->addForeignKey(
            'fk_character_creation_choice_source',
            '{{%character_creation_choice}}',
            'source_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createIndex('ix_character_creation_choice_entry', '{{%character_creation_choice}}', 'entry_id');
        $this->addForeignKey(
            'fk_character_creation_choice_entry',
            '{{%character_creation_choice}}',
            'entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        foreach (['character_sheet_details', 'character_sheet_snapshot'] as $table) {
            $this->createTable('{{%' . $table . '}}', ['character_id' => $this->integer()->notNull(),
            'data' => $this->json()->notNull()]);
            $this->addPrimaryKey('pk_' . $table, '{{%' . $table . '}}', 'character_id');
            $this->addForeignKey(
                'fk_' . $table . '_character',
                '{{%' . $table . '}}',
                'character_id',
                '{{%character}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%character_sheet_snapshot}}');
        $this->dropTable('{{%character_sheet_details}}');
        $this->dropTable('{{%character_creation_choice}}');
        $this->dropTable('{{%character_class_skill}}');
        $this->dropTable('{{%character_class_skill_choice}}');
        $this->dropTable('{{%character_item}}');
        $this->dropTable('{{%character_inventory_state}}');
        $this->dropTable('{{%character_background_choice}}');
    }
}
