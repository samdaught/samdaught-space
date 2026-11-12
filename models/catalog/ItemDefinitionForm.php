<?php

declare(strict_types=1);

namespace app\models\catalog;

use app\models\rules\{AbilityDefinition, BackgroundToolProficiency, ItemKind, ToolGroup};
use app\models\characters\CharacterBackgroundChoice;
use yii\base\Model;

class ItemDefinitionForm extends Model
{
    public $item_kind_code = null;
    public $tool_group_code = null;
    public $tool_ability_code = null;
    public ?int $catalogEntryId = null;

    public function rules(): array
    {
        return [
            [['tool_group_code', 'tool_ability_code'], 'filter', 'filter' => static fn($value) => $value === '' ? null : $value],
            ['item_kind_code', 'required', 'message' => 'Выберите вид предмета.'],
            ['tool_group_code', 'required', 'message' => 'Выберите группу инструмента.',
                'when' => fn(): bool => $this->item_kind_code === ItemKind::TOOL],
            [['item_kind_code', 'tool_group_code'], 'string', 'max' => 32], ['tool_ability_code', 'string', 'max' => 3],
            ['item_kind_code', 'exist', 'targetClass' => ItemKind::class, 'targetAttribute' => ['item_kind_code' => 'code']],
            ['tool_group_code', 'exist', 'targetClass' => ToolGroup::class, 'targetAttribute' => ['tool_group_code' => 'code'], 'message' => 'Выберите группу инструментов из списка.'],
            ['tool_ability_code', 'exist', 'targetClass' => AbilityDefinition::class, 'targetAttribute' => ['tool_ability_code' => 'code'], 'message' => 'Выберите характеристику инструмента из списка.'],
            ['item_kind_code', 'validateToolUsage'],
        ];
    }

    public function validateToolUsage($attribute, $params): void
    {
        if ($this->item_kind_code === ItemKind::TOOL) {
            return;
        }
        if ($this->tool_group_code !== null || $this->tool_ability_code !== null) {
            $this->addError($attribute, 'Свойства инструмента разрешены только для вида «Инструмент».');
        }
        if ($this->catalogEntryId !== null && (BackgroundToolProficiency::find()->where(['tool_entry_id' => $this->catalogEntryId])->exists()
            || CharacterBackgroundChoice::find()->where(['tool_entry_id' => $this->catalogEntryId])->exists())) {
            $this->addError($attribute, 'Этот инструмент используется в предыстории или личных выборах персонажей. Сначала замените соответствующие ссылки.');
        }
    }
}
