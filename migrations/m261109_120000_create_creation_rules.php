<?php

use yii\db\Migration;

class m261109_120000_create_creation_rules extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%background_ability_option}}', [
            'id' => $this->primaryKey(),
            'background_entry_id' => $this->integer()->notNull(),
            'ability_code' => $this->string(3)->notNull(),
        ]);
        $this->createIndex(
            'ux_background_ability_option_entry_ability',
            '{{%background_ability_option}}',
            ['background_entry_id', 'ability_code'],
            true
        );
        $this->createIndex('ix_background_ability_option_ability', '{{%background_ability_option}}', 'ability_code');
        $this->addForeignKey(
            'fk_background_ability_option_entry',
            '{{%background_ability_option}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_ability_option_ability',
            '{{%background_ability_option}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%skill_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'ability_code' => $this->string(3)->notNull(),
        ]);
        $this->addPrimaryKey('pk_skill_definition', '{{%skill_definition}}', 'catalog_entry_id');
        $this->createIndex('ix_skill_definition_ability', '{{%skill_definition}}', 'ability_code');
        $this->addForeignKey(
            'fk_skill_definition_entry',
            '{{%skill_definition}}',
            'catalog_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_skill_definition_ability',
            '{{%skill_definition}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%background_skill_proficiency}}', [
            'id' => $this->primaryKey(),
            'background_entry_id' => $this->integer()->notNull(),
            'skill_entry_id' => $this->integer()->notNull(),
        ]);
        $this->createIndex(
            'ux_background_skill_pair',
            '{{%background_skill_proficiency}}',
            ['background_entry_id', 'skill_entry_id'],
            true
        );
        $this->createIndex('ix_background_skill_definition', '{{%background_skill_proficiency}}', 'skill_entry_id');
        $this->addForeignKey(
            'fk_background_skill_background',
            '{{%background_skill_proficiency}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_skill_definition',
            '{{%background_skill_proficiency}}',
            'skill_entry_id',
            '{{%skill_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%feat_category}}', [
            'code' => $this->string(32)->notNull(),
            'title' => $this->string(100)->notNull(),
        ]);
        $this->addPrimaryKey('pk_feat_category', '{{%feat_category}}', 'code');
        $this->batchInsert('{{%feat_category}}', ['code', 'title'], [
            ['origin', 'Происхождение'],
            ['general', 'Общая'],
            ['fighting_style', 'Боевой стиль'],
            ['epic_boon', 'Эпический дар'],
        ]);
        $this->createTable('{{%feat_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'category_code' => $this->string(32)->notNull(),
        ]);
        $this->addPrimaryKey('pk_feat_definition', '{{%feat_definition}}', 'catalog_entry_id');
        $this->createIndex('ix_feat_definition_category', '{{%feat_definition}}', 'category_code');
        $this->addForeignKey(
            'fk_feat_definition_entry',
            '{{%feat_definition}}',
            'catalog_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_feat_definition_category',
            '{{%feat_definition}}',
            'category_code',
            '{{%feat_category}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%background_feat}}', [
            'background_entry_id' => $this->integer()->notNull(),
            'feat_entry_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_background_feat', '{{%background_feat}}', 'background_entry_id');
        $this->createIndex('ix_background_feat_definition', '{{%background_feat}}', 'feat_entry_id');
        $this->addForeignKey(
            'fk_background_feat_background',
            '{{%background_feat}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_feat_definition',
            '{{%background_feat}}',
            'feat_entry_id',
            '{{%feat_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%item_kind}}', ['code' => $this->string(32)->notNull(),
            'title' => $this->string(100)->notNull()]);
        $this->addPrimaryKey('pk_item_kind', '{{%item_kind}}', 'code');
        $this->batchInsert('{{%item_kind}}', ['code', 'title'], [
            ['tool', 'Инструмент'],
            ['gear', 'Снаряжение'],
            ['weapon', 'Оружие'],
            ['armor', 'Броня'],
            ['shield', 'Щит'],
            ['potion', 'Зелье'],
            ['ammunition', 'Боеприпасы'],
            ['component', 'Компонент'],
        ]);
        $this->createTable('{{%tool_group}}', ['code' => $this->string(32)->notNull(),
            'title' => $this->string(100)->notNull()]);
        $this->addPrimaryKey('pk_tool_group', '{{%tool_group}}', 'code');
        $this->batchInsert('{{%tool_group}}', ['code', 'title'], [
            ['artisan', 'Ремесленные инструменты'],
            ['musical', 'Музыкальные инструменты'],
            ['gaming', 'Игровые наборы'],
            ['other', 'Прочие инструменты'],
        ]);
        $this->createTable('{{%item_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'kind_code' => $this->string(32)->notNull(),
        ]);
        $this->addPrimaryKey('pk_item_definition', '{{%item_definition}}', 'catalog_entry_id');
        $this->createIndex('ix_item_definition_kind', '{{%item_definition}}', 'kind_code');
        $this->addForeignKey(
            'fk_item_definition_entry',
            '{{%item_definition}}',
            'catalog_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_item_definition_kind',
            '{{%item_definition}}',
            'kind_code',
            '{{%item_kind}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%tool_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'group_code' => $this->string(32)->notNull(),
            'ability_code' => $this->string(3)->notNull(),
        ]);
        $this->addPrimaryKey('pk_tool_definition', '{{%tool_definition}}', 'catalog_entry_id');
        $this->createIndex('ix_tool_definition_group', '{{%tool_definition}}', 'group_code');
        $this->createIndex('ix_tool_definition_ability', '{{%tool_definition}}', 'ability_code');
        $this->addForeignKey(
            'fk_tool_definition_item',
            '{{%tool_definition}}',
            'catalog_entry_id',
            '{{%item_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_tool_definition_group',
            '{{%tool_definition}}',
            'group_code',
            '{{%tool_group}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_tool_definition_ability',
            '{{%tool_definition}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%background_tool_proficiency}}', [
            'background_entry_id' => $this->integer()->notNull(),
            'tool_entry_id' => $this->integer(),
            'tool_group_code' => $this->string(32),
        ]);
        $this->addPrimaryKey(
            'pk_background_tool_proficiency',
            '{{%background_tool_proficiency}}',
            'background_entry_id'
        );
        $this->createIndex('ix_background_tool_entry', '{{%background_tool_proficiency}}', 'tool_entry_id');
        $this->createIndex('ix_background_tool_group', '{{%background_tool_proficiency}}', 'tool_group_code');
        $this->addForeignKey(
            'fk_background_tool_background',
            '{{%background_tool_proficiency}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_tool_entry',
            '{{%background_tool_proficiency}}',
            'tool_entry_id',
            '{{%tool_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_tool_group',
            '{{%background_tool_proficiency}}',
            'tool_group_code',
            '{{%tool_group}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_background_tool_one_target',
            '{{%background_tool_proficiency}}',
            '([[tool_entry_id]] IS NOT NULL AND [[tool_group_code]] IS NULL)'
            . ' OR ([[tool_entry_id]] IS NULL AND [[tool_group_code]] IS NOT NULL)'
        );

        $this->createTable('{{%background_starting_equipment}}', [
            'background_entry_id' => $this->integer()->notNull(),
            'package_money_cp' => $this->integer()->notNull(),
            'alternative_money_cp' => $this->integer()->notNull(),
            'include_background_tool' => $this->boolean()->notNull(),
        ]);
        $this->addPrimaryKey(
            'pk_background_starting_equipment',
            '{{%background_starting_equipment}}',
            'background_entry_id'
        );
        $this->addForeignKey(
            'fk_background_equipment_entry',
            '{{%background_starting_equipment}}',
            'background_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_background_equipment_money',
            '{{%background_starting_equipment}}',
            '[[package_money_cp]] >= 0 AND [[alternative_money_cp]] >= 0'
        );
        $this->createTable('{{%background_starting_equipment_item}}', [
            'id' => $this->primaryKey(),
            'background_entry_id' => $this->integer()->notNull(),
            'item_entry_id' => $this->integer()->notNull(),
            'quantity' => $this->integer()->notNull(),
        ]);
        $this->createIndex(
            'ux_background_equipment_item',
            '{{%background_starting_equipment_item}}',
            ['background_entry_id', 'item_entry_id'],
            true
        );
        $this->createIndex(
            'ix_background_equipment_item_entry',
            '{{%background_starting_equipment_item}}',
            'item_entry_id'
        );
        $this->addForeignKey(
            'fk_background_equipment_item_rule',
            '{{%background_starting_equipment_item}}',
            'background_entry_id',
            '{{%background_starting_equipment}}',
            'background_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_background_equipment_item_entry',
            '{{%background_starting_equipment_item}}',
            'item_entry_id',
            '{{%item_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_background_equipment_quantity',
            '{{%background_starting_equipment_item}}',
            '[[quantity]] > 0'
        );

        $this->createTable('{{%hit_die_definition}}', ['sides' => $this->smallInteger()->notNull(),
            'title' => $this->string(16)->notNull()]);
        $this->addPrimaryKey('pk_hit_die_definition', '{{%hit_die_definition}}', 'sides');
        $this->addCheck('ck_hit_die_sides', '{{%hit_die_definition}}', '[[sides]] > 0');
        $this->batchInsert(
            '{{%hit_die_definition}}',
            ['sides', 'title'],
            [[6, 'd6'], [8, 'd8'], [10, 'd10'], [12, 'd12']]
        );
        $this->createTable('{{%species_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'speed_feet' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_species_definition', '{{%species_definition}}', 'catalog_entry_id');
        $this->addForeignKey(
            'fk_species_definition_entry',
            '{{%species_definition}}',
            'catalog_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck('ck_species_speed', '{{%species_definition}}', '[[speed_feet]] >= 0');
        $this->createTable('{{%class_definition}}', [
            'catalog_entry_id' => $this->integer()->notNull(),
            'hit_die_sides' => $this->smallInteger()->notNull(),
            // NULL: правило ещё не настроено; 0: выбирать навыки не нужно.
            'skill_choice_count' => $this->integer(),
        ]);
        $this->addPrimaryKey('pk_class_definition', '{{%class_definition}}', 'catalog_entry_id');
        $this->createIndex('ix_class_definition_hit_die', '{{%class_definition}}', 'hit_die_sides');
        $this->addForeignKey(
            'fk_class_definition_entry',
            '{{%class_definition}}',
            'catalog_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_class_definition_hit_die',
            '{{%class_definition}}',
            'hit_die_sides',
            '{{%hit_die_definition}}',
            'sides',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%class_saving_throw_proficiency}}', [
            'class_entry_id' => $this->integer()->notNull(),
            'ability_code' => $this->string(3)->notNull(),
        ]);
        $this->addPrimaryKey(
            'pk_class_saving_throw',
            '{{%class_saving_throw_proficiency}}',
            ['class_entry_id', 'ability_code']
        );
        $this->createIndex('ix_class_saving_throw_ability', '{{%class_saving_throw_proficiency}}', 'ability_code');
        $this->addForeignKey(
            'fk_class_saving_throw_class',
            '{{%class_saving_throw_proficiency}}',
            'class_entry_id',
            '{{%class_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_class_saving_throw_ability',
            '{{%class_saving_throw_proficiency}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addCheck('ck_class_skill_count', '{{%class_definition}}', '[[skill_choice_count]] BETWEEN 0 AND 100');
        $this->createTable('{{%class_skill_option}}', [
            'class_entry_id' => $this->integer()->notNull(),
            'skill_entry_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_class_skill_option', '{{%class_skill_option}}', ['class_entry_id', 'skill_entry_id']);
        $this->createIndex('ix_class_skill_option_skill', '{{%class_skill_option}}', 'skill_entry_id');
        $this->addForeignKey(
            'fk_class_skill_option_class',
            '{{%class_skill_option}}',
            'class_entry_id',
            '{{%class_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_class_skill_option_skill',
            '{{%class_skill_option}}',
            'skill_entry_id',
            '{{%skill_definition}}',
            'catalog_entry_id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%entry_creation_rule}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'hit_point_bonus' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addPrimaryKey('pk_entry_creation_rule', '{{%entry_creation_rule}}', 'source_entry_id');
        $this->addForeignKey(
            'fk_entry_creation_rule_source',
            '{{%entry_creation_rule}}',
            'source_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%entry_creation_grant}}', ['source_entry_id' => $this->integer()->notNull(),
            'entry_id' => $this->integer()->notNull(),
            'mode' => $this->string(16)->notNull()]);
        $this->addPrimaryKey('pk_entry_creation_grant', '{{%entry_creation_grant}}', ['source_entry_id', 'entry_id']);
        $this->addForeignKey(
            'fk_entry_creation_grant_source',
            '{{%entry_creation_grant}}',
            'source_entry_id',
            '{{%entry_creation_rule}}',
            'source_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createTable('{{%entry_creation_choice}}', ['source_entry_id' => $this->integer()->notNull(),
            'code' => $this->string(32)->notNull(),
            'title' => $this->string(120)->notNull(),
            'choose_count' => $this->integer()->notNull(),
            'mode' => $this->string(16)->notNull()]);
        $this->addPrimaryKey('pk_entry_creation_choice', '{{%entry_creation_choice}}', ['source_entry_id', 'code']);
        $this->addForeignKey(
            'fk_entry_creation_choice_source',
            '{{%entry_creation_choice}}',
            'source_entry_id',
            '{{%entry_creation_rule}}',
            'source_entry_id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_entry_creation_choice_count',
            '{{%entry_creation_choice}}',
            '[[choose_count]] BETWEEN 1 AND 100'
        );
        $this->createTable('{{%entry_creation_choice_option}}', ['source_entry_id' => $this->integer()->notNull(),
            'code' => $this->string(32)->notNull(),
            'entry_id' => $this->integer()->notNull()]);
        $this->addPrimaryKey(
            'pk_entry_creation_choice_option',
            '{{%entry_creation_choice_option}}',
            ['source_entry_id', 'code', 'entry_id']
        );
        $this->addForeignKey(
            'fk_entry_creation_option_choice',
            '{{%entry_creation_choice_option}}',
            ['source_entry_id', 'code'],
            '{{%entry_creation_choice}}',
            ['source_entry_id', 'code'],
            'RESTRICT',
            'RESTRICT'
        );

        foreach (['entry_creation_grant', 'entry_creation_choice_option'] as $table) {
            $this->createIndex('ix_' . $table . '_entry', '{{%' . $table . '}}', 'entry_id');
            $this->addForeignKey(
                'fk_' . $table . '_entry',
                '{{%' . $table . '}}',
                'entry_id',
                '{{%catalog_entry}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
        }
        foreach (['entry_creation_grant', 'entry_creation_choice'] as $table) {
            $this->addCheck('ck_' . $table . '_mode', '{{%' . $table . '}}', "[[mode]] IN ('grant', 'expertise')");
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%entry_creation_choice_option}}');
        $this->dropTable('{{%entry_creation_choice}}');
        $this->dropTable('{{%entry_creation_grant}}');
        $this->dropTable('{{%entry_creation_rule}}');
        $this->dropTable('{{%class_skill_option}}');
        $this->dropTable('{{%class_saving_throw_proficiency}}');
        $this->dropTable('{{%class_definition}}');
        $this->dropTable('{{%species_definition}}');
        $this->dropTable('{{%hit_die_definition}}');
        $this->dropTable('{{%background_starting_equipment_item}}');
        $this->dropTable('{{%background_starting_equipment}}');
        $this->dropTable('{{%background_tool_proficiency}}');
        $this->dropTable('{{%tool_definition}}');
        $this->dropTable('{{%item_definition}}');
        $this->dropTable('{{%tool_group}}');
        $this->dropTable('{{%item_kind}}');
        $this->dropTable('{{%background_feat}}');
        $this->dropTable('{{%feat_definition}}');
        $this->dropTable('{{%feat_category}}');
        $this->dropTable('{{%background_skill_proficiency}}');
        $this->dropTable('{{%skill_definition}}');
        $this->dropTable('{{%background_ability_option}}');
    }
}
