<?php

use yii\db\Migration;

class m261110_200000_create_class_card extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%class_card}}', [
            'entry_id' => $this->integer()->notNull(),
            'data' => $this->json()->notNull(),
        ]);
        $this->addPrimaryKey('pk_class_card', '{{%class_card}}', 'entry_id');
        $this->addForeignKey(
            'fk_class_card_entry',
            '{{%class_card}}',
            'entry_id',
            '{{%catalog_entry}}',
            'id',
            'CASCADE',
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%class_card}}');
    }
}
