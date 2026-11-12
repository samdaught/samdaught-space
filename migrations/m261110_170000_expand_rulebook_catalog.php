<?php

use yii\db\Migration;

class m261110_170000_expand_rulebook_catalog extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%catalog_entry}}', 'revision', $this->integer()->notNull()->defaultValue(1));
        $this->addCheck('ck_catalog_entry_revision', '{{%catalog_entry}}', '[[revision]] >= 1');
        $this->batchInsert('{{%catalog_entry_type}}', ['code', 'title'], [['subclass', 'Подкласс'], ['species_variant', 'Вариант вида']]);
        foreach (['subclass_definition' => ['class_entry_id', 'class_definition'], 'species_variant_definition' => ['species_entry_id', 'species_definition']] as $table => [$parent, $target]) {
            $entryType = $table === 'subclass_definition' ? 'subclass' : 'species_variant';
            $this->createTable('{{%' . $table . '}}', ['entry_type' => $this->string(32)->notNull()->defaultValue($entryType), 'catalog_entry_id' => $this->integer()->notNull(), $parent => $this->integer()->notNull(), 'selection_level' => $this->smallInteger()->notNull()->defaultValue(1)]);
            $this->addPrimaryKey('pk_' . $table, '{{%' . $table . '}}', 'catalog_entry_id');
            $this->addCheck('ck_' . $table . '_type', '{{%' . $table . '}}', "[[entry_type]] = '{$entryType}'");
            $this->addForeignKey('fk_' . $table . '_entry', '{{%' . $table . '}}', ['catalog_entry_id', 'entry_type'], '{{%catalog_entry}}', ['id', 'type'], 'RESTRICT', 'RESTRICT');
            $this->addForeignKey('fk_' . $table . '_parent', '{{%' . $table . '}}', $parent, '{{%' . $target . '}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
            $this->createIndex('ix_' . $table . '_parent', '{{%' . $table . '}}', $parent);
            $this->addCheck('ck_' . $table . '_level', '{{%' . $table . '}}', '[[selection_level]] BETWEEN 1 AND 20');
        }
        $this->createTable('{{%entry_rules}}', [
            'entry_id' => $this->integer()->notNull(),
            'variant_required' => $this->boolean()->notNull()->defaultValue(false),
            'subclass_level' => $this->smallInteger()->notNull()->defaultValue(3),
            'feature_kind' => $this->string(32)->notNull()->defaultValue('general'),
            'repeatable' => $this->boolean()->notNull()->defaultValue(false),
            'casting_ability_code' => $this->string(3),
            'casting_choice_code' => $this->string(32),
            'prerequisites' => $this->json()->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_rules', '{{%entry_rules}}', 'entry_id');
        $this->addForeignKey('fk_entry_rules_entry', '{{%entry_rules}}', 'entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_rules_casting_ability', '{{%entry_rules}}', 'casting_ability_code', '{{%ability_definition}}', 'code', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_rules_levels', '{{%entry_rules}}', '[[subclass_level]] BETWEEN 1 AND 20');
        $this->addCheck('ck_entry_rules_feature_kind', '{{%entry_rules}}', "[[feature_kind]] IN ('general','class_feature','invocation','metamagic','maneuver','item_plan')");

        $this->createTable('{{%entry_rule_choice}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'code' => $this->string(32)->notNull(),
            'title' => $this->string(255)->notNull(),
            'kind' => $this->string(32)->notNull(),
            'choice_count' => $this->smallInteger()->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_rule_choice', '{{%entry_rule_choice}}', ['source_entry_id', 'code']);
        $this->addForeignKey('fk_entry_rule_choice_source', '{{%entry_rule_choice}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_entry_rule_choice_kind', '{{%entry_rule_choice}}', "[[kind]] IN ('ability','size','language','spell_list','mastery','weapon_mastery','proficiency') AND [[choice_count]] BETWEEN 1 AND 100");
        $this->createTable('{{%entry_rule_choice_value}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'choice_code' => $this->string(32)->notNull(),
            'value' => $this->string(64)->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_rule_choice_value', '{{%entry_rule_choice_value}}', ['source_entry_id', 'choice_code', 'value']);
        $this->addForeignKey('fk_entry_rule_choice_value_choice', '{{%entry_rule_choice_value}}', ['source_entry_id', 'choice_code'], '{{%entry_rule_choice}}', ['source_entry_id', 'code'], 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_rule_distinct_choice}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'choice_code' => $this->string(32)->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_rule_distinct_choice', '{{%entry_rule_distinct_choice}}', ['source_entry_id', 'choice_code']);
        $this->addForeignKey('fk_entry_rule_distinct_choice_choice', '{{%entry_rule_distinct_choice}}', ['source_entry_id', 'choice_code'], '{{%entry_rule_choice}}', ['source_entry_id', 'code'], 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_rule_effect}}', [
            'id' => $this->primaryKey(),
            'source_entry_id' => $this->integer()->notNull(),
            'target' => $this->string(32)->notNull(),
            'target_entry_id' => $this->integer(),
            'choice_code' => $this->string(32),
            'formula' => $this->json()->notNull(),
            'condition' => $this->json()->notNull(),
        ]);
        $this->createIndex('ix_entry_rule_effect_source', '{{%entry_rule_effect}}', 'source_entry_id');
        $this->addForeignKey('fk_entry_rule_effect_source', '{{%entry_rule_effect}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_rule_effect_target', '{{%entry_rule_effect}}', 'target_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_spell_list_class}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'class_entry_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_spell_list_class', '{{%entry_spell_list_class}}', ['source_entry_id', 'class_entry_id']);
        $this->addForeignKey('fk_entry_spell_list_class_source', '{{%entry_spell_list_class}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_spell_list_class_class', '{{%entry_spell_list_class}}', 'class_entry_id', '{{%class_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_spell_pick}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'code' => $this->string(32)->notNull(),
            'title' => $this->string(255)->notNull(),
            'list_choice_code' => $this->string(32),
            'spell_level' => $this->smallInteger()->notNull(),
            'choice_count' => $this->smallInteger()->notNull(),
            'ability_choice_code' => $this->string(32),
            'ability_code' => $this->string(3),
            'always_prepared' => $this->boolean()->notNull(),
            'count_against_limit' => $this->boolean()->notNull(),
            'cast_with_slots' => $this->boolean()->notNull(),
            'free_uses' => $this->json()->notNull(),
            'recovery' => $this->string(16)->notNull(),
            'cast_level' => $this->smallInteger(),
        ]);
        $this->addPrimaryKey('pk_entry_spell_pick', '{{%entry_spell_pick}}', ['source_entry_id', 'code']);
        $this->addForeignKey('fk_entry_spell_pick_source', '{{%entry_spell_pick}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_spell_pick_ability', '{{%entry_spell_pick}}', 'ability_code', '{{%ability_definition}}', 'code', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_spell_pick_class}}', [
            'source_entry_id' => $this->integer()->notNull(),
            'pick_code' => $this->string(32)->notNull(),
            'class_entry_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_entry_spell_pick_class', '{{%entry_spell_pick_class}}', ['source_entry_id', 'pick_code', 'class_entry_id']);
        $this->addForeignKey('fk_entry_spell_pick_class_pick', '{{%entry_spell_pick_class}}', ['source_entry_id', 'pick_code'], '{{%entry_spell_pick}}', ['source_entry_id', 'code'], 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_entry_spell_pick_class_class', '{{%entry_spell_pick_class}}', 'class_entry_id', '{{%class_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_item_rule}}', [
            'item_entry_id' => $this->integer()->notNull(),
            'cost_cp' => $this->integer()->notNull(),
            'weight_grams' => $this->integer()->notNull(),
            'magic_rarity' => $this->string(16),
            'magic_attunement' => $this->boolean(),
            'magic_charges' => $this->json(),
            'magic_recovery' => $this->string(16),
            'magic_plan_min_level' => $this->smallInteger(),
            'magic_prerequisites' => $this->json(),
        ]);
        $this->addPrimaryKey('pk_entry_item_rule', '{{%entry_item_rule}}', 'item_entry_id');
        $this->addForeignKey('fk_entry_item_rule_item', '{{%entry_item_rule}}', 'item_entry_id', '{{%item_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_rule_reference}}', ['source_entry_id' => $this->integer()->notNull(), 'target_entry_id' => $this->integer()->notNull()]);
        $this->addPrimaryKey('pk_entry_rule_reference', '{{%entry_rule_reference}}', ['source_entry_id', 'target_entry_id']);
        foreach (['source_entry_id', 'target_entry_id'] as $column) {
            $this->addForeignKey('fk_rule_reference_' . $column, '{{%entry_rule_reference}}', $column, '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->createIndex('ix_rule_reference_target', '{{%entry_rule_reference}}', 'target_entry_id');
        $this->createTable('{{%entry_level_grant}}', ['source_entry_id' => $this->integer()->notNull(), 'target_entry_id' => $this->integer()->notNull(), 'level' => $this->smallInteger()->notNull(), 'level_basis' => $this->string(16)->notNull()]);
        $this->addPrimaryKey('pk_entry_level_grant', '{{%entry_level_grant}}', ['source_entry_id', 'target_entry_id', 'level', 'level_basis']);
        foreach (['source_entry_id', 'target_entry_id'] as $column) {
            $this->addForeignKey('fk_level_grant_' . $column, '{{%entry_level_grant}}', $column, '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->addCheck('ck_level_grant_level', '{{%entry_level_grant}}', '[[level]] BETWEEN 1 AND 20');
        $this->addCheck('ck_level_grant_basis', '{{%entry_level_grant}}', "[[level_basis]] IN ('character','class')");
        $this->createTable('{{%class_level_rule}}', ['class_entry_id' => $this->integer()->notNull(), 'level' => $this->smallInteger()->notNull(), 'proficiency_bonus' => $this->smallInteger()->notNull(), 'kind' => $this->string(16)->notNull(), 'cantrip_count' => $this->smallInteger()->notNull(), 'known_count' => $this->smallInteger()->notNull(), 'prepared_count' => $this->smallInteger()->notNull(), 'pact_level' => $this->smallInteger()->notNull(), 'recovery' => $this->string(16)->notNull(), 'change_on' => $this->string(16)->notNull()]);
        $this->addPrimaryKey('pk_class_level_rule', '{{%class_level_rule}}', ['class_entry_id', 'level']);
        $this->addForeignKey('fk_class_level_class', '{{%class_level_rule}}', 'class_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_class_level_values', '{{%class_level_rule}}', '[[level]] BETWEEN 1 AND 20 AND [[proficiency_bonus]] BETWEEN 2 AND 6 AND [[pact_level]] BETWEEN 0 AND 9 AND [[cantrip_count]] BETWEEN 0 AND 100 AND [[known_count]] BETWEEN 0 AND 100 AND [[prepared_count]] BETWEEN 0 AND 100');
        $this->addCheck('ck_class_level_kind', '{{%class_level_rule}}', "[[kind]] IN ('none','spellcasting','pact')");
        $this->createTable('{{%class_level_slot}}', ['class_entry_id' => $this->integer()->notNull(), 'level' => $this->smallInteger()->notNull(), 'spell_level' => $this->smallInteger()->notNull(), 'count' => $this->smallInteger()->notNull()]);
        $this->addPrimaryKey('pk_class_level_slot', '{{%class_level_slot}}', ['class_entry_id', 'level', 'spell_level']);
        $this->addForeignKey('fk_class_level_slot_rule', '{{%class_level_slot}}', ['class_entry_id', 'level'], '{{%class_level_rule}}', ['class_entry_id', 'level'], 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_class_level_slot_values', '{{%class_level_slot}}', '[[spell_level]] BETWEEN 1 AND 9 AND [[count]] BETWEEN 0 AND 100');
        $this->createTable('{{%rule_vocabulary}}', ['kind' => $this->string(32)->notNull(), 'code' => $this->string(64)->notNull(), 'title' => $this->string(100)->notNull(), 'group_code' => $this->string(32)->notNull()->defaultValue('')]);
        $this->addPrimaryKey('pk_rule_vocabulary', '{{%rule_vocabulary}}', ['kind', 'code']);
        $values = ['size' => ['small' => 'Маленький', 'medium' => 'Средний', 'large' => 'Большой'], 'armor' => ['light' => 'Лёгкие доспехи', 'medium' => 'Средние доспехи', 'heavy' => 'Тяжёлые доспехи', 'shield' => 'Щиты'], 'damage' => ['acid' => 'Кислота', 'bludgeoning' => 'Дробящий', 'cold' => 'Холод', 'fire' => 'Огонь', 'force' => 'Силовой', 'lightning' => 'Электричество', 'necrotic' => 'Некротический', 'piercing' => 'Колющий', 'poison' => 'Яд', 'psychic' => 'Психический', 'radiant' => 'Излучение', 'slashing' => 'Рубящий', 'thunder' => 'Звук'], 'weapon_property' => ['ammunition' => 'Боеприпас', 'finesse' => 'Фехтовальное', 'heavy' => 'Тяжёлое', 'light' => 'Лёгкое', 'loading' => 'Перезарядка', 'range' => 'Дистанция', 'reach' => 'Досягаемость', 'thrown' => 'Метательное', 'two_handed' => 'Двуручное', 'versatile' => 'Универсальное'], 'mastery' => ['cleave' => 'Cleave', 'graze' => 'Graze', 'nick' => 'Nick', 'push' => 'Push', 'sap' => 'Sap', 'slow' => 'Slow', 'topple' => 'Topple', 'vex' => 'Vex'], 'language' => ['common' => 'Общий', 'common_sign' => 'Общий язык жестов', 'draconic' => 'Драконий', 'dwarvish' => 'Дварфийский', 'elvish' => 'Эльфийский', 'giant' => 'Великаний', 'gnomish' => 'Гномий', 'goblin' => 'Гоблинский', 'halfling' => 'Полуросликов', 'orc' => 'Орочий', 'abyssal' => 'Бездны', 'celestial' => 'Небесный', 'deep_speech' => 'Глубинная речь', 'druidic' => 'Друидический', 'infernal' => 'Инфернальный', 'primordial' => 'Первичный', 'sylvan' => 'Сильван', 'thieves_cant' => 'Воровской жаргон', 'undercommon' => 'Подземный']];
        foreach ($values as $kind => $rows) {
            foreach ($rows as $code => $title) {
                $this->insert('{{%rule_vocabulary}}', ['kind' => $kind, 'code' => $code, 'title' => $title, 'group_code' => $kind === 'language' ? in_array($code, ['common', 'common_sign', 'draconic', 'dwarvish', 'elvish', 'giant', 'gnomish', 'goblin', 'halfling', 'orc'], true) ? 'standard' : 'rare' : '']);
            }
        }
        $this->createTable('{{%entry_resource}}', ['source_entry_id' => $this->integer()->notNull(), 'code' => $this->string(32)->notNull(), 'title' => $this->string(120)->notNull(), 'maximum' => $this->json()->notNull(), 'recovery' => $this->string(16)->notNull(), 'level' => $this->smallInteger()->notNull()]);
        $this->addPrimaryKey('pk_entry_resource', '{{%entry_resource}}', ['source_entry_id', 'code']);
        $this->addForeignKey('fk_entry_resource_source', '{{%entry_resource}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%entry_spell_grant}}', ['source_entry_id' => $this->integer()->notNull(), 'code' => $this->string(32)->notNull(), 'spell_entry_id' => $this->integer()->notNull(), 'level' => $this->smallInteger()->notNull(), 'level_basis' => $this->string(16)->notNull(), 'always_prepared' => $this->boolean()->notNull(), 'count_against_limit' => $this->boolean()->notNull(), 'cast_with_slots' => $this->boolean()->notNull(), 'free_uses' => $this->json()->notNull(), 'recovery' => $this->string(16)->notNull(), 'cast_level' => $this->smallInteger()]);
        $this->addPrimaryKey('pk_entry_spell_grant', '{{%entry_spell_grant}}', ['source_entry_id', 'code']);
        $this->addForeignKey('fk_spell_grant_source', '{{%entry_spell_grant}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_spell_grant_spell', '{{%entry_spell_grant}}', 'spell_entry_id', '{{%spell_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%weapon_definition}}', ['catalog_entry_id' => $this->integer()->notNull(), 'category' => $this->string(16)->notNull(), 'damage_count' => $this->smallInteger()->notNull(), 'damage_die' => $this->smallInteger()->notNull(), 'damage_kind' => $this->string(32)->notNull()->defaultValue('damage'), 'damage_type' => $this->string(64)->notNull(), 'mastery_kind' => $this->string(32)->notNull()->defaultValue('mastery'), 'mastery' => $this->string(64), 'range_normal' => $this->integer()->notNull(), 'range_long' => $this->integer()->notNull(), 'versatile_die' => $this->smallInteger()]);
        $this->addPrimaryKey('pk_weapon_definition', '{{%weapon_definition}}', 'catalog_entry_id');
        $this->addForeignKey('fk_weapon_item', '{{%weapon_definition}}', 'catalog_entry_id', '{{%item_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_weapon_kinds', '{{%weapon_definition}}', "[[damage_kind]]='damage' AND [[mastery_kind]]='mastery'");
        $this->addForeignKey('fk_weapon_damage', '{{%weapon_definition}}', ['damage_kind', 'damage_type'], '{{%rule_vocabulary}}', ['kind', 'code'], 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_weapon_mastery', '{{%weapon_definition}}', ['mastery_kind', 'mastery'], '{{%rule_vocabulary}}', ['kind', 'code'], 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%weapon_property}}', ['item_entry_id' => $this->integer()->notNull(), 'kind' => $this->string(32)->notNull()->defaultValue('weapon_property'), 'code' => $this->string(64)->notNull()]);
        $this->addPrimaryKey('pk_weapon_property', '{{%weapon_property}}', ['item_entry_id', 'code']);
        $this->addForeignKey('fk_weapon_property_item', '{{%weapon_property}}', 'item_entry_id', '{{%weapon_definition}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        $this->addCheck('ck_weapon_property_kind', '{{%weapon_property}}', "[[kind]]='weapon_property'");
        $this->addForeignKey('fk_weapon_property_vocabulary', '{{%weapon_property}}', ['kind', 'code'], '{{%rule_vocabulary}}', ['kind', 'code'], 'RESTRICT', 'RESTRICT');
        foreach (['category' => $this->string(16)->notNull()->defaultValue('light'), 'strength_min' => $this->smallInteger()->notNull()->defaultValue(0), 'stealth_disadvantage' => $this->boolean()->notNull()->defaultValue(false)] as $column => $type) {
            $this->addColumn('{{%armor_definition}}', $column, $type);
        }
        $this->addCheck('ck_armor_strength', '{{%armor_definition}}', '[[strength_min]] BETWEEN 0 AND 30');
        foreach (['subclass_entry_id' => 'subclass_definition', 'species_variant_entry_id' => 'species_variant_definition'] as $column => $table) {
            $this->addColumn('{{%character}}', $column, $this->integer());
            $this->addForeignKey('fk_character_' . $column, '{{%character}}', $column, '{{%' . $table . '}}', 'catalog_entry_id', 'RESTRICT', 'RESTRICT');
        }
        $this->addColumn('{{%character}}', 'rule_choices', $this->json()->notNull()->defaultValue(new \yii\db\Expression("'{}'::jsonb")));
        $this->addColumn('{{%character_creation_choice}}', 'instance_key', $this->string(16)->notNull()->defaultValue('root'));
        $this->dropPrimaryKey('pk_character_creation_choice', '{{%character_creation_choice}}');
        $this->addPrimaryKey('pk_character_creation_choice', '{{%character_creation_choice}}', ['character_id', 'source_entry_id', 'instance_key', 'code', 'entry_id']);
        $this->createTable('{{%character_rule_instance}}', ['character_id' => $this->integer()->notNull(), 'source_entry_id' => $this->integer()->notNull(), 'instance_key' => $this->string(16)->notNull(), 'origin' => $this->string(255)->notNull()]);
        $this->addPrimaryKey('pk_character_rule_instance', '{{%character_rule_instance}}', ['character_id', 'source_entry_id', 'instance_key']);
        $this->addForeignKey('fk_rule_instance_character', '{{%character_rule_instance}}', 'character_id', '{{%character}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_rule_instance_source', '{{%character_rule_instance}}', 'source_entry_id', '{{%catalog_entry}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->createTable('{{%character_rule_choice}}', ['character_id' => $this->integer()->notNull(), 'source_entry_id' => $this->integer()->notNull(), 'instance_key' => $this->string(16)->notNull(), 'code' => $this->string(32)->notNull(), 'value' => $this->string(64)->notNull()]);
        $this->addPrimaryKey('pk_character_rule_choice', '{{%character_rule_choice}}', ['character_id', 'source_entry_id', 'instance_key', 'code', 'value']);
        $this->addForeignKey('fk_rule_choice_instance', '{{%character_rule_choice}}', ['character_id', 'source_entry_id', 'instance_key'], '{{%character_rule_instance}}', ['character_id', 'source_entry_id', 'instance_key'], 'RESTRICT', 'RESTRICT');
    }
    public function safeDown()
    {
        if ((new \yii\db\Query())->from('{{%character_rule_instance}}')->exists()) {
            throw new \RuntimeException('Нельзя откатить схему при наличии сохранённых экземпляров правил персонажей.');
        }
        $this->dropColumn('{{%character}}', 'rule_choices');
        $this->dropTable('{{%character_rule_choice}}');
        $this->dropTable('{{%character_rule_instance}}');
        // Do not silently collapse distinct repeated choices during rollback.
        if ((new \yii\db\Query())->from('{{%character_creation_choice}}')->where(['<>', 'instance_key', 'root'])->exists()) {
            throw new \RuntimeException('Нельзя откатить схему при наличии повторных экземпляров выбора.');
        }
        $this->dropPrimaryKey('pk_character_creation_choice', '{{%character_creation_choice}}');
        $this->addPrimaryKey('pk_character_creation_choice', '{{%character_creation_choice}}', ['character_id', 'source_entry_id', 'code', 'entry_id']);
        $this->dropColumn('{{%character_creation_choice}}', 'instance_key');
        foreach (['subclass_entry_id', 'species_variant_entry_id'] as $column) {
            $this->dropForeignKey('fk_character_' . $column, '{{%character}}');
            $this->dropColumn('{{%character}}', $column);
        }
        foreach (['weapon_property', 'weapon_definition', 'entry_item_rule', 'entry_spell_pick_class', 'entry_spell_pick', 'entry_spell_list_class', 'entry_rule_effect', 'entry_rule_distinct_choice', 'entry_rule_choice_value', 'entry_rule_choice', 'entry_spell_grant', 'entry_resource', 'rule_vocabulary', 'class_level_slot', 'class_level_rule', 'entry_level_grant', 'entry_rule_reference', 'entry_rules', 'species_variant_definition', 'subclass_definition'] as $table) {
            $this->dropTable('{{%' . $table . '}}');
        }
        foreach (['category', 'strength_min', 'stealth_disadvantage'] as $column) {
            $this->dropColumn('{{%armor_definition}}', $column);
        }
        $this->delete('{{%catalog_entry_type}}', ['code' => ['subclass', 'species_variant']]);
        $this->dropColumn('{{%catalog_entry}}', 'revision');
    }
}