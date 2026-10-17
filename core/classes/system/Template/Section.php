<?php

declare(strict_types=1);

namespace Core\System\Template;

/**
 * Класс для управления секциями (@section/@yield)
 */
class Section
{
    private string $name;
    private string $content = '';
    private bool $started = false;
    private bool $overwrite = true;
    private ?Section $parent = null;

    public function __construct(string $name, bool $overwrite = true)
    {
        $this->name = $name;
        $this->overwrite = $overwrite;
    }

    /**
     * Начать захват секции
     */
    public function start(): void
    {
        $this->started = true;
        ob_start();
    }

    /**
     * Завершить захват секции
     */
    public function end(): string
    {
        if (!$this->started) {
            return '';
        }

        $this->content = ob_get_clean();
        $this->started = false;

        return $this->content;
    }

    /**
     * Установить содержимое напрямую
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Установить родительскую секцию (для @parent)
     */
    public function setParent(?Section $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Получить содержимое с учетом @parent
     */
    public function getWithParent(): string
    {
        if ($this->parent !== null && str_contains($this->content, '@parent')) {
            return str_replace('@parent', $this->parent->getContent(), $this->content);
        }

        return $this->content;
    }

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }
}