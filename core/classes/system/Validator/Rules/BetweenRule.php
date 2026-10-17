<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

/**
 * Правило "значение в диапазоне"
 */
class BetweenRule implements ValidationRule
{
    private int|float $min;
    private int|float $max;

    public function __construct(int|float $min, int|float $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function validate(mixed $value, string $field, array $data = []): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $num = (float) $value;

        return $num >= $this->min && $num <= $this->max;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "Поле '{$field}' должно быть в диапазоне от {$this->min} до {$this->max}";
    }

    public function getName(): string
    {
        return 'between';
    }
}