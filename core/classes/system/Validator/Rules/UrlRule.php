<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

class UrlRule implements ValidationRule
{
    public function validate(mixed $value, string $field, array $data = []): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' должно быть валидным URL";
    }

    public function getName(): string
    {
        return 'url';
    }
}