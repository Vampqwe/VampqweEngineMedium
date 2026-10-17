<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

class RequiredRule implements ValidationRule
{
    public function validate(mixed $value, string $field, array $data = []): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' обязательно для заполнения";
    }

    public function getName(): string
    {
        return 'required';
    }
}