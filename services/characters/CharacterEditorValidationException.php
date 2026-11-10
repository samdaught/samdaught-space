<?php

declare(strict_types=1);

namespace app\services\characters;

class CharacterEditorValidationException extends \RuntimeException
{
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Проверьте поля редактора персонажа.');
    }
}
