<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

class NumericRule implements ValidationRule
{
    public function validate(mixed $value, string $field, array $data = []): bool
    {
        return is_numeric($value);
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' должно быть числом";
    }

    public function getName(): string
    {
        return 'numeric';
    }
}