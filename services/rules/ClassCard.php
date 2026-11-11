<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use app\models\rules\{ClassDefinition, ClassSavingThrowProficiency, ClassSkillOption};
use yii\db\{JsonExpression, Query};

final class ClassCard
{
    private const ABILITIES = ['str', 'dex', 'con', 'int', 'wis', 'cha'];

    public function read(int $entryId): ?array
    {
        $data = (new Query())->select('data')->from('{{%class_card}}')->where(['entry_id' => $entryId])->scalar();
        return is_string($data) ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : ($data ?: null);
    }

    public function validate(CatalogEntry $entry, $data): array
    {
        if ($entry->type !== 'class') throw new \InvalidArgumentException('Карточка класса доступна только для класса.');
        $this->keys($data, ['primary_abilities', 'roleplay', 'portraits', 'features', 'subclass_label']);
        if (!is_array($data['primary_abilities']) || !array_is_list($data['primary_abilities']) || count($data['primary_abilities']) > 6) {
            throw new \InvalidArgumentException('Проверьте основные характеристики класса.');
        }
        foreach ($data['primary_abilities'] as $ability) {
            if (!is_string($ability) || !in_array($ability, self::ABILITIES, true)) {
                throw new \InvalidArgumentException('Укажите допустимые основные характеристики класса.');
            }
        }
        if (count(array_unique($data['primary_abilities'])) !== count($data['primary_abilities'])) {
            throw new \InvalidArgumentException('Основные характеристики класса не должны повторяться.');
        }
        foreach (['roleplay' => 10000, 'subclass_label' => 255] as $field => $max) $data[$field] = $this->text($data[$field], $max);
        $this->rows($data['portraits'], 2, 'В карточке класса можно добавить до двух иллюстраций.');
        foreach ($data['portraits'] as &$portrait) {
            $this->keys($portrait, ['url', 'alt']);
            $portrait['url'] = $this->text($portrait['url'], 2048);
            $portrait['alt'] = $this->text($portrait['alt'], 255);
            if (!$this->imageUrl($portrait['url'])) throw new \InvalidArgumentException('Укажите ссылку на иллюстрацию по HTTP(S) или путь от корня сайта.');
        }
        unset($portrait);
        $this->rows($data['features'], 100, 'В карточке класса можно добавить до 100 особенностей.');
        foreach ($data['features'] as &$feature) {
            $this->keys($feature, ['level', 'title', 'description']);
            if (!is_int($feature['level']) || $feature['level'] < 1 || $feature['level'] > 20) {
                throw new \InvalidArgumentException('Укажите уровень особенности от 1 до 20.');
            }
            $feature['title'] = $this->text($feature['title'], 255);
            $feature['description'] = $this->text($feature['description'], 10000);
            if ($feature['title'] === '' || $feature['description'] === '') {
                throw new \InvalidArgumentException('Заполните название и описание каждой особенности класса.');
            }
        }
        unset($feature);
        return $data;
    }

    public function save(int $entryId, array $data): void
    {
        Yii::$app->db->createCommand()->upsert('{{%class_card}}', [
            'entry_id' => $entryId,
            'data' => new JsonExpression($data),
        ])->execute();
    }

    public function details(CatalogEntry $entry): array
    {
        $definition = ClassDefinition::findOne($entry->id);
        $skills = (new Query())->from(['option' => '{{%class_skill_option}}'])
            ->innerJoin(['skill' => '{{%catalog_entry}}'], '[[skill.id]] = [[option.skill_entry_id]]')
            ->where(['option.class_entry_id' => $entry->id])->orderBy(['skill.title' => SORT_ASC])
            ->select(['skill.title'])->column();
        $mechanics = (new EntryMechanics())->read($entry);
        $training = array_values(array_filter($mechanics['traits'] ?? [], static fn($trait) => in_array($trait['kind'] ?? '', ['armor', 'weapon'], true)));
        return [
            'hit_die_sides' => $definition?->hit_die_sides,
            'saving_throw_codes' => ClassSavingThrowProficiency::find()->select('ability_code')
                ->where(['class_entry_id' => $entry->id])->orderBy('ability_code')->column(),
            'skill_choice_count' => $definition?->skill_choice_count,
            'skills' => $skills,
            'training' => $training,
        ];
    }

    private function keys($data, array $keys): void
    {
        if (!is_array($data) || count($data) !== count($keys) || array_diff($keys, array_keys($data))) {
            throw new \InvalidArgumentException('Проверьте поля карточки класса.');
        }
    }

    private function rows($data, int $max, string $message): void
    {
        if (!is_array($data) || !array_is_list($data) || count($data) > $max) throw new \InvalidArgumentException($message);
    }

    private function text($value, int $max): string
    {
        if (!is_string($value) || mb_strlen($value) > $max) throw new \InvalidArgumentException('Поле карточки класса слишком длинное или содержит неверное значение.');
        return trim($value);
    }

    private function imageUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url)) return false;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
