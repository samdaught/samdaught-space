<?php

declare(strict_types=1);

namespace app\services\rules;

use Yii;
use app\models\catalog\CatalogEntry;
use yii\db\{JsonExpression, Query};

final class SpeciesCard
{
    public function read(int $entryId): ?array
    {
        $data = (new Query())->select('data')->from('{{%species_card}}')->where(['entry_id' => $entryId])->scalar();
        return is_string($data) ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : ($data ?: null);
    }

    public function validate(CatalogEntry $entry, $data): array
    {
        if ($entry->type !== 'species') {
            throw new \InvalidArgumentException('Карточка расы доступна только для расы.');
        }
        $this->keys($data, ['age', 'height', 'roleplay', 'portraits', 'features']);
        foreach (['age' => 255, 'height' => 255, 'roleplay' => 10000] as $field => $max) {
            $data[$field] = $this->text($data[$field], $max);
        }
        $this->rows($data['portraits'], 2);
        foreach ($data['portraits'] as &$portrait) {
            $this->keys($portrait, ['url', 'alt']);
            $portrait['url'] = $this->text($portrait['url'], 2048);
            $portrait['alt'] = $this->text($portrait['alt'], 255);
            if (!$this->imageUrl($portrait['url'])) {
                throw new \InvalidArgumentException('Укажите ссылку на иллюстрацию по HTTP(S) или путь от корня сайта.');
            }
        }
        unset($portrait);
        $this->rows($data['features'], 50);
        foreach ($data['features'] as &$feature) {
            $this->keys($feature, ['title', 'description']);
            $feature['title'] = $this->text($feature['title'], 255);
            $feature['description'] = $this->text($feature['description'], 10000);
            if ($feature['title'] === '' || $feature['description'] === '') {
                throw new \InvalidArgumentException('Заполните название и описание каждой особенности расы.');
            }
        }
        unset($feature);
        return $data;
    }

    public function save(int $entryId, array $data): void
    {
        Yii::$app->db->createCommand()->upsert('{{%species_card}}', [
            'entry_id' => $entryId,
            'data' => new JsonExpression($data),
        ])->execute();
    }

    private function keys($data, array $keys): void
    {
        if (!is_array($data) || count($data) !== count($keys) || array_diff($keys, array_keys($data))) {
            throw new \InvalidArgumentException('Проверьте поля карточки расы.');
        }
    }

    private function rows($data, int $max): void
    {
        if (!is_array($data) || !array_is_list($data) || count($data) > $max) {
            throw new \InvalidArgumentException('В карточке можно добавить до двух иллюстраций и до 50 особенностей.');
        }
    }

    private function text($value, int $max): string
    {
        if (!is_string($value) || mb_strlen($value) > $max) {
            throw new \InvalidArgumentException('Поле карточки расы слишком длинное или содержит неверное значение.');
        }
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
