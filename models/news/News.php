<?php

declare(strict_types=1);

namespace app\models\news;

use app\models\users\User;
use yii\db\ActiveRecord;

class News extends ActiveRecord
{
    public const MAX_BODY_LENGTH = 100000;

    public static function tableName(): string { return '{{%site_news}}'; }

    public function rules(): array
    {
        return [
            ['title', 'string', 'max' => 255, 'skipOnEmpty' => false, 'message' => 'Заголовок должен быть строкой.', 'tooLong' => 'Заголовок не должен превышать 255 символов.'],
            ['summary', 'string', 'max' => 500, 'skipOnEmpty' => false, 'message' => 'Анонс должен быть строкой.', 'tooLong' => 'Анонс не должен превышать 500 символов.'],
            ['body', 'string', 'max' => self::MAX_BODY_LENGTH, 'skipOnEmpty' => false, 'message' => 'Текст новости должен быть строкой.', 'tooLong' => 'Текст новости не должен превышать 100000 символов.'],
            [['title', 'summary', 'body'], 'trim', 'skipOnError' => true],
            ['title', 'required', 'message' => 'Введите заголовок новости.'],
            ['body', 'required', 'when' => static fn(News $news): bool => (bool) $news->is_published,
                'message' => 'Перед публикацией заполните текст новости.'],
            [['author_id', 'created_at', 'updated_at', 'revision'], 'required'],
            [['author_id', 'revision'], 'integer', 'min' => 1, 'max' => 2147483647],
            ['author_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => ['author_id' => 'id']],
            ['is_published', 'boolean'],
        ];
    }

    public function adminData(): array
    {
        return $this->getAttributes(['id', 'title', 'summary', 'body', 'is_published', 'revision']) + [
            'published_at' => $this->iso($this->published_at),
            'created_at' => $this->iso($this->created_at), 'updated_at' => $this->iso($this->updated_at),
        ];
    }

    public function publicData(bool $includeBody): array
    {
        $text = $this->summary !== '' ? $this->summary : preg_replace('/\s+/u', ' ', $this->body);
        $data = ['id' => $this->id, 'title' => $this->title, 'excerpt' => mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text,
            'published_at' => $this->iso($this->published_at)];
        if ($includeBody) $data['body'] = $this->body;
        return $data;
    }

    private function iso(?string $value): ?string
    {
        return $value === null ? null : (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
