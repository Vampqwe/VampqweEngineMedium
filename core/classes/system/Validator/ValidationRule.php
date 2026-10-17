<?php

declare(strict_types=1);

namespace Core\System\Validator;

/**
 * Интерфейс для правил валидации
 */
interface ValidationRule
{
    /**
     * Проверить значение
     *
     * @param mixed $value Значение для проверки
     * @param string $field Имя поля (для сообщений об ошибках)
     * @param array<string, mixed> $data Все данные (для зависимых правил)
     * @return bool true если валидно, false если нет
     */
    public function validate(mixed $value, string $field, array $data = []): bool;

    /**
     * Получить сообщение об ошибке
     *
     * @param string $field Имя поля
     * @param array<string, mixed> $params Параметры правила
     * @return string Сообщение об ошибке
     */
    public function getMessage(string $field, array $params = []): string;

    /**
     * Получить имя правила
     */
    public function getName(): string;
}