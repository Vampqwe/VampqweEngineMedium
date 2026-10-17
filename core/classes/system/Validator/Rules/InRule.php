<?php

declare(strict_types=1);

namespace Core\System\Validator\Rules;

use Core\System\Validator\ValidationRule;

class InRule implements ValidationRule
{
    /** @var array<mixed> */
    private array $allowed;

    /**
     * @param array<mixed> $allowed
     */
    public function __construct(array $allowed)
    {
        $this->allowed = $allowed;
    }

    public function validate(mixed $value, string $field, array $data = []): bool
    {
        return in_array($value, $this->allowed, true);
    }

    public function getMessage(string $field, array $params = []): string
    {
        $allowed = implode(', ', array_map('strval', $this->allowed));
        return "Поле '{$field}' должно быть одним из: {$allowed}";
    }

    public function getName(): string
    {
        return 'in';
    }
}