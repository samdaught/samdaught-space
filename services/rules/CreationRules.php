<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{RuleProfile, SkillDefinition, FeatDefinition, ToolDefinition};
use yii\db\Query;

class CreationRules
{
    public const SOURCE_TYPES = ['species', 'class', 'background', 'feature', 'feat'];
    public const TARGET_TYPES = ['feature', 'spell', 'skill', 'feat', 'item'];
    public const MAX_ROWS = 100;

    public function read(int $sourceId): ?array
    {
        $definition = (new Query())->from('{{%entry_creation_rule}}')->where(['source_entry_id' => $sourceId])->one();
        if ($definition === false) return null;
        $grants = (new Query())->select(['entry_id', 'mode'])->from('{{%entry_creation_grant}}')->where(['source_entry_id' => $sourceId])->orderBy('entry_id')->all();
        $choices = [];
        foreach ((new Query())->from('{{%entry_creation_choice}}')->where(['source_entry_id' => $sourceId])->orderBy('code')->all() as $row) {
            $choices[] = ['code' => $row['code'], 'title' => $row['title'], 'count' => (int) $row['choose_count'], 'mode' => $row['mode'],
                'entry_ids' => (new Query())->select('entry_id')->from('{{%entry_creation_choice_option}}')->where(['source_entry_id' => $sourceId, 'code' => $row['code']])->orderBy('entry_id')->column()];
        }
        return ['grants' => $grants, 'choices' => $choices, 'hit_point_bonus' => (int) $definition['hit_point_bonus']];
    }

    public function findAvailable(int $id, ?RuleProfile $profile): ?CatalogEntry
    {
        if ($profile === null) return null;
        $found = Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = CAST(:id AS BIGINT) FOR SHARE', [':id' => $id])->queryScalar();
        $entry = $found === false ? null : CatalogEntry::findOne($found);
        return $entry !== null && $entry->is_published && (new CatalogAvailability())->isAvailableInProfile($entry, $profile) ? $entry : null;
    }

    public function targetAvailable(CatalogEntry $entry): bool
    {
        return in_array($entry->type, self::TARGET_TYPES, true)
            && ($entry->type !== 'skill' || SkillDefinition::findOne($entry->id) !== null)
            && ($entry->type !== 'feat' || FeatDefinition::findOne($entry->id) !== null)
            && ($entry->type !== 'item' || ToolDefinition::findOne($entry->id) !== null);
    }

    public function options(?RuleProfile $profile): array
    {
        $options = [];
        if ($profile !== null) foreach (CatalogEntry::find()->where(['type' => self::TARGET_TYPES, 'is_published' => true])->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC])->all() as $entry) {
            if ((new CatalogAvailability())->isAvailableInProfile($entry, $profile) && $this->targetAvailable($entry)) $options[] = $entry->getAttributes(['id', 'type', 'title']);
        }
        return ['entries' => $options, 'source_types' => self::SOURCE_TYPES, 'max_rows' => self::MAX_ROWS,
            'modes' => [['code' => 'grant', 'title' => 'Получить запись / владение навыком или инструментом'], ['code' => 'expertise', 'title' => 'Экспертиза навыка, которым уже владеет персонаж']]];
    }

    public function save(CatalogEntry $source, array $rule): void
    {
        Yii::$app->db->transaction(function () use ($source, $rule): void {
            Yii::$app->db->createCommand('SELECT [[id]] FROM {{%catalog_entry}} WHERE [[id]] = :id FOR UPDATE', [':id' => $source->id])->queryScalar();
            $db = Yii::$app->db;
            $db->createCommand()->upsert('{{%entry_creation_rule}}', ['source_entry_id' => $source->id, 'hit_point_bonus' => $rule['hit_point_bonus'] ?? 0])->execute();
            foreach (['entry_creation_choice_option', 'entry_creation_choice', 'entry_creation_grant'] as $table) $db->createCommand()->delete('{{%' . $table . '}}', ['source_entry_id' => $source->id])->execute();
            foreach ($rule['grants'] as $grant) $db->createCommand()->insert('{{%entry_creation_grant}}', ['source_entry_id' => $source->id] + $grant)->execute();
            foreach ($rule['choices'] as $choice) {
                $db->createCommand()->insert('{{%entry_creation_choice}}', ['source_entry_id' => $source->id, 'code' => $choice['code'], 'title' => trim($choice['title']), 'choose_count' => $choice['count'], 'mode' => $choice['mode']])->execute();
                foreach ($choice['entry_ids'] as $id) $db->createCommand()->insert('{{%entry_creation_choice_option}}', ['source_entry_id' => $source->id, 'code' => $choice['code'], 'entry_id' => $id])->execute();
            }
        });
    }
}
