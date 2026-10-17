<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

/**
 * Правило "максимальная длина"
 */
class MaxLengthRule implements ValidationRule
{
    private int $max;

    public function __construct(int $max)
    {
        $this->max = $max;
    }

    public function validate(mixed $value, string $field, array $data = []): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return mb_strlen($value) <= $this->max;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' должно содержать максимум {$this->max} символов";
    }

    public function getName(): string
    {
        return 'max_length';
    }
}