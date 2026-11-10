<?php

declare(strict_types=1);

namespace app\models\rules;

use app\models\catalog\CatalogEntry;
use yii\db\ActiveRecord;

class BackgroundToolProficiency extends ActiveRecord
{
    public function rules(): array
    {
        return [
            ['background_entry_id', 'required'], ['background_entry_id', 'integer', 'min' => 1],
            ['background_entry_id', 'unique'], ['tool_entry_id', 'integer', 'min' => 1],
            ['tool_group_code', 'string', 'max' => 32],
            ['background_entry_id', 'exist', 'targetClass' => CatalogEntry::class,
                'targetAttribute' => ['background_entry_id' => 'id'], 'filter' => ['type' => 'background']],
            ['tool_entry_id', 'exist', 'targetClass' => ToolDefinition::class,
                'targetAttribute' => ['tool_entry_id' => 'catalog_entry_id']],
            ['tool_group_code', 'exist', 'targetClass' => ToolGroup::class,
                'targetAttribute' => ['tool_group_code' => 'code']],
            ['tool_entry_id', 'validateTarget', 'skipOnEmpty' => false],
        ];
    }

    public function validateTarget($attribute, $params): void
    {
        if (($this->tool_entry_id === null) === ($this->tool_group_code === null)) {
            $this->addError($attribute, 'Укажите либо конкретный инструмент, либо группу инструментов.');
        }
    }
}
