<?php

use yii\db\Migration;

class m261110_190000_create_species_card extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%species_card}}', [
            'entry_id' => $this->integer()->notNull(),
            'data' => $this->json()->notNull(),
        ]);
        $this->addPrimaryKey('pk_species_card', '{{%species_card}}', 'entry_id');
        $this->addForeignKey('fk_species_card_entry', '{{%species_card}}', 'entry_id', '{{%catalog_entry}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%species_card}}');
    }
}
