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


namespace Core\Module\Admin\Controllers;

use Core\Module\Admin\Services\HtmlImportParser;
use Core\Module\Admin\Services\PageService;
use Core\Module\Admin\Services\AdminService;
use Core\System\Config\Config;
use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Template\Template;

class ImportController
{
    private HtmlImportParser $parser;
    private PageService $pageService;
    private AdminService $adminService;
    private Template $template;
    private Config $config;

    public function __construct(
        HtmlImportParser $parser,
        PageService $pageService,
        AdminService $adminService,
        Template $template,
        Config $config
    ) {
        $this->parser = $parser;
        $this->pageService = $pageService;
        $this->adminService = $adminService;
        $this->template = $template;
        $this->config = $config;
    }

    public function index(Request $request): Response
    {
        $flash = $this->adminService->getFlash();

        $html = $this->template->render('admin/import/index', [
            'title' => 'Импорт страниц',
            'flash' => $flash,
            'csrf_token' => '',
        ]);

        return Response::html($html);
    }

    public function upload(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/admin/import');
        }

        $file = $_FILES['html_file'] ?? null;

        if (!$file) {
            $this->adminService->setFlash('error', 'Файл не выбран');
            return Response::redirect('/admin/import');
        }

        // Валидация файла (размер, расширение, ошибки загрузки)
        $errors = $this->parser->validateUpload($file);
        if ($errors !== []) {
            $this->adminService->setFlash('error', implode('. ', $errors));
            return Response::redirect('/admin/import');
        }

        $html = file_get_contents($file['tmp_name']);

        if (empty($html)) {
            $this->adminService->setFlash('error', 'Файл пуст или не удалось прочитать');
            return Response::redirect('/admin/import');
        }

        try {
            $parsed = $this->parser->parse($html);
        } catch (\Exception $e) {
            $this->adminService->setFlash('error', 'Ошибка парсинга: ' . $e->getMessage());
            return Response::redirect('/admin/import');
        }

        $exists = $this->pageService->pageExistsBySlug($parsed['slug']);

        $_SESSION['import_preview'] = $parsed;

        $html = $this->template->render('admin/import/preview', [
            'title' => 'Предпросмотр импорта',
            'parsed' => $parsed,
            'existing' => $exists,
            'flash' => $this->adminService->getFlash(),
            'csrf_token' => '',
        ]);

        return Response::html($html);
    }

    public function confirm(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/admin/import');
        }

        $parsed = $_SESSION['import_preview'] ?? null;

        if (!$parsed) {
            $this->adminService->setFlash('error', 'Нет данных для импорта. Загрузите файл заново.');
            return Response::redirect('/admin/import');
        }

        $exists = $this->pageService->pageExistsBySlug($parsed['slug']);

        if ($exists) {
            $this->adminService->setFlash('error', 'Страница с slug "' . $parsed['slug'] . '" уже существует.');
            unset($_SESSION['import_preview']);
            return Response::redirect('/admin/import');
        }

        // canonical = APP_BASE_URL + slug из БД
        $baseUrl = rtrim((string)$this->config->get('APP_BASE_URL', ''), '/');
        $canonical = $baseUrl . '/' . ltrim($parsed['slug'], '/');

        // JS-скрипты: путь по умолчанию ["/assets/js/main.js"] + извлечённые со страницы
        $scripts = $parsed['scripts'] ?? ['/assets/js/main.js'];
        if (!in_array('/assets/js/main.js', $scripts, true)) {
            array_unshift($scripts, '/assets/js/main.js');
        }

        $data = [
            'title' => $parsed['title'],
            'description' => $parsed['description'],
            'slug' => $parsed['slug'],
            // Контент переносится в БД как есть: классы и inline-стили сохранены,
            // теги <style> вырезаны на этапе парсинга
            'content' => $parsed['content'],
            'status' => 'draft',
            'page_type' => 'article',
            'meta_robots' => 'index, follow',
            'canonical' => $canonical,
            'og_title' => $parsed['title'],
            'og_description' => $parsed['description'],
            'scripts' => json_encode(array_values($scripts), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $id = $this->pageService->createPage($data);

        // СОХРАНЯЕМ SCHEMA.ORG РАЗМЕТКУ (привязка к странице согласно архитектуре БД)
        if (!empty($parsed['schemas'])) {
            $this->pageService->addSchemaForPage($id, $parsed['schemas']);
        }

        // Стили из <style>-блоков пишутся в neutral-style.css только после подтверждения импорта
        if (!empty($parsed['styles'])) {
            if (!$this->parser->commitStyles($parsed['styles'])) {
                $this->adminService->setFlash('warning', 'Не удалось записать стили в storage/neutral-style.css');
            }
        }

        unset($_SESSION['import_preview']);

        $this->adminService->setFlash('success', 'Страница импортирована! ID: ' . $id);

        return Response::redirect('/admin/pages/' . $id . '/edit');
    }
}