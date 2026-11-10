<?php

declare(strict_types=1);

namespace app\models\characters;

use app\models\catalog\CatalogEntry;
use app\models\rules\RuleProfile;
use app\models\users\User;
use app\services\rules\CatalogAvailability;
use yii\db\ActiveRecord;

class Character extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const ABILITY_METHOD_STANDARD_ARRAY = 'standard_array';

    public static function tableName(): string
    {
        return '{{%character}}';
    }

    public function rules(): array
    {
        $isMissingValue = static fn($value): bool => $value === null || $value === '';

        return [
            ['name', 'string', 'max' => 255],
            ['name', 'trim', 'skipOnError' => true],
            ['target_level', 'default', 'value' => 1, 'isEmpty' => $isMissingValue],
            ['status', 'default', 'value' => self::STATUS_DRAFT, 'isEmpty' => $isMissingValue],
            ['ability_score_method', 'default', 'value' => self::ABILITY_METHOD_STANDARD_ARRAY, 'isEmpty' => $isMissingValue],
            [['user_id', 'rule_profile_id', 'name', 'target_level', 'status', 'ability_score_method'], 'required'],
            [['user_id', 'rule_profile_id'], 'integer', 'min' => 1],
            ['revision', 'integer', 'min' => 1, 'max' => 2147483647],
            [['species_entry_id', 'class_entry_id', 'background_entry_id'], 'default', 'value' => null, 'isEmpty' => $isMissingValue],
            [['species_entry_id', 'class_entry_id', 'background_entry_id'], 'integer', 'min' => 1, 'isEmpty' => $isMissingValue],
            ['target_level', 'integer', 'min' => 1, 'max' => 20],
            ['status', 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_READY], 'strict' => true],
            ['ability_score_method', 'in', 'range' => [self::ABILITY_METHOD_STANDARD_ARRAY], 'strict' => true],
            [
                'user_id',
                'exist',
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id'],
            ],
            [
                'rule_profile_id',
                'exist',
                'targetClass' => RuleProfile::class,
                'targetAttribute' => ['rule_profile_id' => 'id'],
            ],
            [
                'species_entry_id',
                'validateCatalogEntry',
                'skipOnEmpty' => false,
                'params' => ['expectedType' => 'species'],
            ],
            [
                'class_entry_id',
                'validateCatalogEntry',
                'skipOnEmpty' => false,
                'params' => ['expectedType' => 'class'],
            ],
            [
                'background_entry_id',
                'validateCatalogEntry',
                'skipOnEmpty' => false,
                'params' => ['expectedType' => 'background'],
            ],
        ];
    }

    public function validateCatalogEntry($attribute, $params): void
    {
        if ($this->hasErrors($attribute) || $this->hasErrors('rule_profile_id')) {
            return;
        }

        $entryId = $this->$attribute;

        if ($entryId === null) {
            return;
        }

        $entry = CatalogEntry::findOne($entryId);

        if ($entry === null) {
            $this->addError($attribute, 'Выбранная запись не найдена.');
            return;
        }

        if ($entry->type !== $params['expectedType']) {
            $this->addError($attribute, 'Выбранная запись не подходит для этого поля.');
            return;
        }

        if (!(bool) $entry->is_published) {
            $this->addError($attribute, 'Выбранная запись не опубликована.');
            return;
        }

        $profile = RuleProfile::findOne($this->rule_profile_id);

        if ($profile === null) {
            $this->addError('rule_profile_id', 'Профиль правил не найден.');
            return;
        }

        $availability = new CatalogAvailability();

        if (!$availability->isAvailableInProfile($entry, $profile)) {
            $this->addError($attribute, 'Выбранная запись недоступна в этом профиле правил.');
        }
    }
}
