<?php

declare(strict_types=1);

namespace app\services\news;

class NewsValidationException extends \RuntimeException
{
    public function __construct(public readonly array $fields) { parent::__construct('Проверьте поля новости.'); }
}
