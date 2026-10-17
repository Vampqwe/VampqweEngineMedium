<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

/**
 * Правило "минимальная длина"
 */
class MinLengthRule implements ValidationRule
{
    private int $min;

    public function __construct(int $min)
    {
        $this->min = $min;
    }

    public function validate(mixed $value, string $field, array $data = []): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return mb_strlen($value) >= $this->min;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' должно содержать минимум {$this->min} символов";
    }

    public function getName(): string
    {
        return 'min_length';
    }
}