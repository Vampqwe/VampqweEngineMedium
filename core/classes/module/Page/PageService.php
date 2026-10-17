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


namespace Core\Module\Page;

use Core\System\DataBase\DataBase;

/**
 * Сервис для работы со страницами из БД
 */
class PageService
{
    private DataBase $db;

    public function __construct(DataBase $db)
    {
        $this->db = $db;
    }
	
		/**
	 * Проверить существование страницы по slug (независимо от статуса)
	 * Используется при импорте для предотвращения дубликатов
	 */
	public function pageExistsBySlug(string $slug): bool
	{
		$slug = $slug === '' ? '' : ltrim($slug, '/');
		$slug = rtrim($slug, '/');
		
		$count = $this->db->fetchColumn(
			"SELECT COUNT(*) FROM pages WHERE slug = :slug",
			['slug' => $slug]
		);
		
		return (int) $count > 0;
	}
	
    /**
     * Получить страницу по слагу
     */
    public function getPageBySlug(string $slug): ?array
    {
        // Нормализуем слаг
        $slug = $slug === '' ? '' : ltrim($slug, '/');
        $slug = rtrim($slug, '/');

        $sql = "
            SELECT 
                p.*, 
                a.name AS author_name, 
                a.bio AS author_bio,
                a.photo AS author_photo,
                a.schema_person AS author_schema
            FROM pages p
            LEFT JOIN authors a ON p.author_id = a.id AND a.is_active = 1
            WHERE p.slug = :slug 
              AND p.status = 'published'
            LIMIT 1
        ";

        $page = $this->db->fetchOne($sql, ['slug' => $slug]);

        if (!$page) {
            return null;
        }

        // Получаем Schema.org разметки:
        //  - page_schema — индивидуальные схемы страницы (page_id, без привязки к маршруту)
        //  - global_schema — общие схемы для всех страниц (route = '*' или точное совпадение с маршрутом)
        $schemas = $this->db->fetchAll(
            "SELECT schema_type, data, page_id, route
             FROM schema_org
             WHERE ((page_id = :page_id AND route IS NULL) OR route = :route OR route = '*')
               AND is_active = 1
             ORDER BY priority DESC",
            [
                'page_id' => $page['id'],
                'route'   => '/' . $slug,
            ]
        );

        $page['global_schema'] = [];
        $page['page_schema'] = [];

        foreach ($schemas as $schema) {
            $data = json_decode($schema['data'], true) ?: [];
            $decoded = array_merge([
                '@context' => 'https://schema.org',
                '@type'    => $schema['schema_type'],
            ], $data);

            if ($schema['page_id'] !== null && $schema['route'] === null) {
                $page['page_schema'][] = $decoded;
            } else {
                $page['global_schema'][] = $decoded;
            }
        }

        // Обратная совместимость: единый список схем
        $page['schema_org'] = array_merge($page['global_schema'], $page['page_schema']);

        // Декодируем скрипты; путь по умолчанию — /assets/js/main.js
        $scripts = !empty($page['scripts'])
            ? json_decode($page['scripts'], true) ?: []
            : [];
        if (!in_array('/assets/js/main.js', $scripts, true)) {
            $scripts[] = '/assets/js/main.js';
        }
        $page['scripts'] = $scripts;

        return $page;
    }

    /**
     * Увеличить счетчик просмотров
     */
    public function incrementViews(int $pageId): void
    {
        try {
            $this->db->execute(
                "UPDATE pages SET views_count = views_count + 1 WHERE id = :id",
                ['id' => $pageId]
            );
        } catch (\Throwable $e) {
            error_log("Failed to increment views: {$e->getMessage()}");
        }
    }
}