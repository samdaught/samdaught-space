<?php

use yii\db\Migration;

class m261109_130000_create_characters extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%character}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'rule_profile_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'target_level' => $this->smallInteger()->notNull()->defaultValue(1),
            'status' => $this->string(16)->notNull()->defaultValue('draft'),
            'species_entry_id' => $this->integer(),
            'class_entry_id' => $this->integer(),
            'background_entry_id' => $this->integer(),
            'ability_score_method' => $this->string(32)->notNull()->defaultValue('standard_array'),
            'revision' => $this->integer()->notNull()->defaultValue(1),
            'finished_at' => $this->timestamp(),
        ]);

        $this->addCheck(
            'ck_character_target_level',
            '{{%character}}',
            '[[target_level]] BETWEEN 1 AND 20'
        );

        $this->createIndex('ix_character_user_id', '{{%character}}', 'user_id');
        $this->createIndex('ix_character_rule_profile_id', '{{%character}}', 'rule_profile_id');

        $this->addForeignKey(
            'fk_character_user_id',
            '{{%character}}',
            'user_id',
            '{{%user}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_character_rule_profile_id',
            '{{%character}}',
            'rule_profile_id',
            '{{%rule_profile}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck('ck_character_revision', '{{%character}}', '[[revision]] >= 1');
        $this->addCheck('ck_character_status', '{{%character}}', "[[status]] IN ('draft', 'ready')");
        foreach (['species_entry_id', 'class_entry_id', 'background_entry_id'] as $column) {
            $this->createIndex('ix_character_' . $column, '{{%character}}', $column);
            $this->addForeignKey(
                'fk_character_' . $column,
                '{{%character}}',
                $column,
                '{{%catalog_entry}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
        }

        $this->createTable('{{%character_ability_score}}', [
            'id' => $this->primaryKey(),
            'character_id' => $this->integer()->notNull(),
            'ability_code' => $this->string(3)->notNull(),
            'base_score' => $this->smallInteger()->null(),
        ]);
        $this->addCheck(
            'ck_character_ability_score_positive',
            '{{%character_ability_score}}',
            '[[base_score]] IS NULL OR [[base_score]] >= 1'
        );
        $this->createIndex(
            'ux_character_ability_score_character_ability',
            '{{%character_ability_score}}',
            ['character_id', 'ability_code'],
            true
        );
        $this->createIndex('ix_character_ability_score_ability_code', '{{%character_ability_score}}', 'ability_code');
        $this->addForeignKey(
            'fk_character_ability_score_character_id',
            '{{%character_ability_score}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_ability_score_ability_code',
            '{{%character_ability_score}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%character_ability_increase_source}}', [
            'id' => $this->primaryKey(),
            'character_id' => $this->integer()->notNull(),
            'slot' => $this->string(64)->notNull(),
            'source_kind' => $this->string(16)->notNull(),
            'source_entry_id' => $this->integer()->notNull(),
            'obtained_level' => $this->smallInteger()->notNull(),
            'class_entry_id' => $this->integer()->null(),
            'revision' => $this->integer()->notNull()->defaultValue(1),
        ]);
        $this->addCheck(
            'ck_ability_increase_source_level',
            '{{%character_ability_increase_source}}',
            '[[obtained_level]] BETWEEN 1 AND 20'
        );
        $this->addCheck(
            'ck_ability_increase_source_kind',
            '{{%character_ability_increase_source}}',
            "[[source_kind]] IN ('background', 'level')"
        );
        $this->addCheck('ck_ability_increase_source_slot', '{{%character_ability_increase_source}}', "[[slot]] <> ''");
        $this->addCheck(
            'ck_ability_increase_source_context',
            '{{%character_ability_increase_source}}',
            "([[source_kind]] = 'background' AND [[slot]] = 'background'"
            . " AND [[obtained_level]] = 1 AND [[class_entry_id]] IS NULL)"
            . " OR ([[source_kind]] = 'level' AND [[class_entry_id]] IS NOT NULL)"
        );
        $this->createIndex(
            'ux_ability_increase_source_character_slot',
            '{{%character_ability_increase_source}}',
            ['character_id', 'slot'],
            true
        );
        $this->createIndex(
            'ix_ability_increase_source_entry',
            '{{%character_ability_increase_source}}',
            'source_entry_id'
        );
        $this->createIndex(
            'ix_ability_increase_source_class',
            '{{%character_ability_increase_source}}',
            'class_entry_id'
        );
        $this->addForeignKey(
            'fk_ability_increase_source_character',
            '{{%character_ability_increase_source}}',
            'character_id',
            '{{%character}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_ability_increase_source_entry',
            '{{%character_ability_increase_source}}',
            'source_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_ability_increase_source_class',
            '{{%character_ability_increase_source}}',
            'class_entry_id',
            '{{%catalog_entry}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%character_ability_increase}}', [
            'id' => $this->primaryKey(),
            'source_id' => $this->integer()->notNull(),
            'ability_code' => $this->string(3)->notNull(),
            'amount' => $this->smallInteger()->notNull(),
        ]);
        $this->addCheck('ck_character_ability_increase_positive', '{{%character_ability_increase}}', '[[amount]] > 0');
        $this->createIndex(
            'ux_character_ability_increase_source_ability',
            '{{%character_ability_increase}}',
            ['source_id', 'ability_code'],
            true
        );
        $this->createIndex('ix_character_ability_increase_ability', '{{%character_ability_increase}}', 'ability_code');
        $this->addForeignKey(
            'fk_character_ability_increase_source',
            '{{%character_ability_increase}}',
            'source_id',
            '{{%character_ability_increase_source}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk_character_ability_increase_ability',
            '{{%character_ability_increase}}',
            'ability_code',
            '{{%ability_definition}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );
        $this->addCheck(
            'ck_ability_increase_source_revision',
            '{{%character_ability_increase_source}}',
            '[[revision]] >= 1'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%character_ability_increase}}');
        $this->dropTable('{{%character_ability_increase_source}}');
        $this->dropTable('{{%character_ability_score}}');
        $this->dropTable('{{%character}}');
    }
}
