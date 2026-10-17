<?php

declare(strict_types=1);

namespace Core\System\Validator;

use RuntimeException;

/**
 * Исключение для операций валидации
 */
class ValidationException extends RuntimeException
{
    /** @var array<string, string[]> */
    private array $errors;

    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(array $errors, string $message = 'Ошибка валидации')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public static function fromResult(ValidationResult $result): self
    {
        return new self($result->getErrors());
    }
}