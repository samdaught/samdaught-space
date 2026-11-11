<?php

use yii\db\Migration;

class m261109_110000_create_archive extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%rule_profile}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(32)->notNull()->unique(),
            'title' => $this->string(100)->notNull(),
        ]);

        $this->insert('{{%rule_profile}}', [
            'code' => 'standard_2024',
            'title' => 'Стандарт 2024',
        ]);

        $this->insert('{{%rule_profile}}', [
            'code' => 'my_world_2024',
            'title' => 'Забытый край',
        ]);

        $this->createTable('{{%content_source}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull()->unique(),
            'title' => $this->string(255)->notNull(),
            'url' => $this->text(),
            'rights_note' => $this->text()->notNull(),
            'attribution_text' => $this->text(),
        ]);

        $this->createTable('{{%catalog_entry_type}}', [
            'code' => $this->string(32)->notNull(),
            'title' => $this->string(100)->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_catalog_entry_type',
            '{{%catalog_entry_type}}',
            'code'
        );

        $this->batchInsert('{{%catalog_entry_type}}', ['code', 'title'], [
            ['species', 'Вид (раса)'],
            ['class', 'Класс'],
            ['background', 'Предыстория'],
            ['skill', 'Навык'],
            ['feat', 'Черта'],
            ['item', 'Предмет'],
            ['feature', 'Особенность'],
            ['spell', 'Заклинание'],
        ]);

        $this->createTable('{{%catalog_entry}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(32)->notNull(),
            'slug' => $this->string(100)->notNull(),
            'title' => $this->string(255)->notNull(),
            'body' => $this->text(),
            'rule_profile_id' => $this->integer()->notNull(),
            'source_id' => $this->integer(),
            'is_published' => $this->boolean()->notNull()->defaultValue(false),
        ]);

        $this->createIndex(
            'ux_catalog_entry_profile_type_slug',
            '{{%catalog_entry}}',
            ['rule_profile_id', 'type', 'slug'],
            true
        );

        $this->addForeignKey(
            'fk_catalog_entry_type',
            '{{%catalog_entry}}',
            'type',
            '{{%catalog_entry_type}}',
            'code',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_catalog_entry_rule_profile_id',
            '{{%catalog_entry}}',
            'rule_profile_id',
            '{{%rule_profile}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_catalog_entry_source_id',
            '{{%catalog_entry}}',
            'source_id',
            '{{%content_source}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->createTable('{{%ability_definition}}', [
            'code' => $this->string(3)->notNull(),
            'title' => $this->string(100)->notNull(),
        ]);
        $this->addPrimaryKey('pk_ability_definition', '{{%ability_definition}}', 'code');

        $this->batchInsert('{{%ability_definition}}', ['code', 'title'], [
            ['str', 'Сила'],
            ['dex', 'Ловкость'],
            ['con', 'Телосложение'],
            ['int', 'Интеллект'],
            ['wis', 'Мудрость'],
            ['cha', 'Харизма'],
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%ability_definition}}');
        $this->dropTable('{{%catalog_entry}}');
        $this->dropTable('{{%catalog_entry_type}}');
        $this->dropTable('{{%content_source}}');
        $this->dropTable('{{%rule_profile}}');
    }
}
