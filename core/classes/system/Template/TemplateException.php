<?php

declare(strict_types=1);

namespace Core\System\Template;

use RuntimeException;

class TemplateException extends RuntimeException
{
    public static function notFound(string $template): self
    {
        return new self("Шаблон не найден: {$template}");
    }

    public static function compilationFailed(string $template, string $error): self
    {
        return new self("Ошибка компиляции шаблона '{$template}': {$error}");
    }

    public static function invalidSyntax(string $template, string $line): self
    {
        return new self("Неверный синтаксис в шаблоне '{$template}' на строке: {$line}");
    }

    public static function pathTraversal(string $path): self
    {
        return new self("Обнаружена попытка Path Traversal: {$path}");
    }

    public static function nestedTooDeep(int $depth, int $max): self
    {
        return new self("Превышена максимальная глубина вложенности: {$depth} (максимум {$max})");
    }
}