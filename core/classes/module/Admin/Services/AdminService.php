<?php
declare(strict_types=1);

/**
 * VampqweEngineMedium — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */


namespace Core\Module\Admin\Services;

use Core\System\DataBase\DataBase;

class AdminService
{
    // Лимиты дашборда (управляются из config/admin-dashboard.php)
    private const DEFAULT_RECENT_LIMIT = 5;
    private const DEFAULT_ACTIVITY_LIMIT = 4;
    private const MAX_LIMIT = 50;

    private DataBase $db;

    /** Единая точка доступа к сессии (flash-сообщения и пр.) */
    private \Core\System\Session\SessionManager $session;

    public function __construct(DataBase $db, ?\Core\System\Session\SessionManager $session = null)
    {
        $this->db = $db;
        $this->session = $session ?? new \Core\System\Session\SessionManager();
    }

    /**
     * Установить flash-сообщение (через SessionManager — единая точка доступа к сессии)
     */
    public function setFlash(string $type, string $message): void
    {
        $flashes = $this->session->get('flash', []);
        $flashes[$type] = $message;
        $this->session->set('flash', $flashes);
    }

    /**
     * Получить и очистить flash-сообщения
     */
    public function getFlash(): array
    {
        $flash = $this->session->get('flash', []);
        $this->session->remove('flash');
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
     * Получить последние страницы.
     *
     * Лимит жёстко ограничивается диапазоном [1, MAX_LIMIT] — конкатенация
     * в SQL безопасна только при гарантированно целочисленном значении.
     */
    public function getRecentPages(int $limit = self::DEFAULT_RECENT_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        return $this->db->fetchAll(
            "SELECT id, title, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT {$limit}"
        );
    }

    /**
     * Получить последнюю активность (имитация — последние изменения страниц)
     */
    public function getRecentActivity(int $limit = self::DEFAULT_ACTIVITY_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        return $this->db->fetchAll(
            "SELECT id, title, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT {$limit}"
        );
    }
}