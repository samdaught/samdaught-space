<?php

use yii\db\Migration;

class m261112_120000_remove_obsolete_rulebook_metadata extends Migration
{
    public function safeUp(): void
    {
        $rules = $this->db->schema->getTableSchema('{{%entry_rules}}', true);
        if ($rules !== null && isset($rules->columns['ready'])) {
            $this->dropColumn('{{%entry_rules}}', 'ready');
        }

        if ($this->db->schema->getTableSchema('{{%entry_source_reference}}', true) !== null) {
            $this->dropTable('{{%entry_source_reference}}');
        }
    }

    public function safeDown(): void
    {
        throw new \RuntimeException(
            'Откат не поддерживается: удалённые технические поля не должны возвращаться в архив.',
        );
    }
}
