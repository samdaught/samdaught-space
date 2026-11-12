<?php

use yii\db\Migration;

class m261112_130000_allow_contextual_tools_and_source_equipment extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn('{{%tool_definition}}', 'ability_code', $this->string(3));

        $this->addColumn('{{%background_starting_equipment}}', 'source_text', $this->text());
        $this->alterColumn('{{%background_starting_equipment}}', 'package_money_cp', $this->integer());
        $this->alterColumn('{{%background_starting_equipment}}', 'alternative_money_cp', $this->integer());
        $this->alterColumn('{{%background_starting_equipment}}', 'include_background_tool', $this->boolean());
        $this->addCheck(
            'ck_background_equipment_representation',
            '{{%background_starting_equipment}}',
            '(
                [[source_text]] IS NOT NULL
                AND [[package_money_cp]] IS NULL
                AND [[alternative_money_cp]] IS NULL
                AND [[include_background_tool]] IS NULL
            ) OR (
                [[source_text]] IS NULL
                AND [[package_money_cp]] IS NOT NULL
                AND [[alternative_money_cp]] IS NOT NULL
                AND [[include_background_tool]] IS NOT NULL
            )'
        );
    }

    public function safeDown(): void
    {
        throw new \RuntimeException(
            'Откат не поддерживается: архив хранит источник комплекта и контекстную характеристику инструмента.'
        );
    }
}
