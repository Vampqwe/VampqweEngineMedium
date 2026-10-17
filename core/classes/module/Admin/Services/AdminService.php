<?php
declare(strict_types=1);

namespace Core\Module\Admin\Services;

use Core\System\DataBase\DataBase;

class AdminService
{
    private DataBase $db;

    public function __construct(DataBase $db)
    {
        $this->db = $db;
    }

    /**
     * Установить flash-сообщение
     */
    public function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Получить и очистить flash-сообщения
     */
    public function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Статистика для дашборда
     */
    public function getDashboardStats(): array
    {
        return [
            'total_pages' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM pages"),
            'published_pages' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM pages WHERE status = 'published'"),
            'draft_pages' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM pages WHERE status = 'draft'"),
            'total_views' => (int) $this->db->fetchColumn("SELECT COALESCE(SUM(views_count), 0) FROM pages"),
        ];
    }
	
		/**
	 * Получить последние страницы
	 */
	public function getRecentPages(int $limit = 5): array
	{
		return $this->db->fetchAll(
			"SELECT id, title, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT {$limit}"
		);
	}

	/**
	 * Получить последнюю активность (имитация — последние изменения страниц)
	 */
	public function getRecentActivity(int $limit = 4): array
	{
		return $this->db->fetchAll(
			"SELECT id, title, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT {$limit}"
		);
	}
}