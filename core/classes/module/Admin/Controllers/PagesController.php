<?php
declare(strict_types=1);

namespace Core\Module\Admin\Controllers;

use Core\Module\Admin\Services\PageService;
use Core\Module\Admin\Services\AdminService;
use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Template\Template;

class PagesController
{
    private PageService $pageService;
    private AdminService $adminService;
    private Template $template;

    public function __construct(
        PageService $pageService,
        AdminService $adminService,
        Template $template
    ) {
        $this->pageService = $pageService;
        $this->adminService = $adminService;
        $this->template = $template;
    }

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $data = $this->pageService->getPages($page);
        $flash = $this->adminService->getFlash();

        $html = $this->template->render('admin/pages/list', [
            'title' => 'Страницы',
            'pages' => $data['pages'],
            'pagination' => [
                'page' => $data['page'],
                'totalPages' => $data['totalPages'],
                'total' => $data['total'],
            ],
            'flash' => $flash,
            'csrf_token' => '',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $authors = $this->pageService->getAuthors();
        $flash = $this->adminService->getFlash();

        $html = $this->template->render('admin/pages/edit', [
            'title' => 'Новая страница',
            'page' => null,
            'authors' => $authors,
            'flash' => $flash,
            'csrf_token' => '',
        ]);

        return Response::html($html);
    }

    public function store(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/admin/pages');
        }

        $data = [
            'title' => strip_tags(trim($request->input('title', ''))),
            'description' => trim($request->input('description', '')),
            'keywords' => trim($request->input('keywords', '')),
            'canonical' => trim($request->input('canonical', '')),
            'og_title' => strip_tags(trim($request->input('og_title', ''))),
            'og_description' => trim($request->input('og_description', '')),
            'meta_robots' => $request->input('meta_robots', 'index, follow'),
            'og_image' => trim($request->input('og_image', '')),
            'reading_time' => $request->input('reading_time') ? (int) $request->input('reading_time') : null,
            'author_id' => $request->input('author_id') ? (int) $request->input('author_id') : null,
            'slug' => trim($request->input('slug', '')),
            'content' => $request->input('content', ''), // БЕЗ санитизации
            'scripts' => $request->input('scripts') ? json_encode(explode(',', $request->input('scripts'))) : null,
            'page_type' => $request->input('page_type', 'article'),
            'parent_id' => $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            'status' => $request->input('status', 'draft'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_in_menu' => (int) $request->input('is_in_menu', 0),
        ];

        if (empty($data['slug'])) {
            $data['slug'] = $this->pageService->generateSlug($data['title']);
        }

        if (empty($data['title']) || empty($data['slug'])) {
            $this->adminService->setFlash('error', 'Заголовок и slug обязательны');
            return Response::redirect('/admin/pages/new');
        }

        $id = $this->pageService->createPage($data);
        $this->adminService->setFlash('success', 'Страница создана');

        return Response::redirect('/admin/pages/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        $id = (int) $request->getAttribute('id');
        $page = $this->pageService->getPage($id);

        if (!$page) {
            $this->adminService->setFlash('error', 'Страница не найдена');
            return Response::redirect('/admin/pages');
        }

        $authors = $this->pageService->getAuthors();
        $flash = $this->adminService->getFlash();

        $html = $this->template->render('admin/pages/edit', [
            'title' => 'Редактирование: ' . $page['title'],
            'page' => $page,
            'authors' => $authors,
            'flash' => $flash,
            'csrf_token' => '',
        ]);

        return Response::html($html);
    }

    public function update(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/admin/pages');
        }

        $id = (int) $request->getAttribute('id');
        $page = $this->pageService->getPage($id);

        if (!$page) {
            $this->adminService->setFlash('error', 'Страница не найдена');
            return Response::redirect('/admin/pages');
        }

        $data = [
            'title' => strip_tags(trim($request->input('title', ''))),
            'description' => trim($request->input('description', '')),
            'keywords' => trim($request->input('keywords', '')),
            'canonical' => trim($request->input('canonical', '')),
            'og_title' => strip_tags(trim($request->input('og_title', ''))),
            'og_description' => trim($request->input('og_description', '')),
            'meta_robots' => $request->input('meta_robots', 'index, follow'),
            'og_image' => trim($request->input('og_image', '')),
            'reading_time' => $request->input('reading_time') ? (int) $request->input('reading_time') : null,
            'author_id' => $request->input('author_id') ? (int) $request->input('author_id') : null,
            'slug' => trim($request->input('slug', '')),
            'content' => $request->input('content', ''), // БЕЗ санитизации
            'scripts' => $request->input('scripts') ? json_encode(explode(',', $request->input('scripts'))) : null,
            'page_type' => $request->input('page_type', 'article'),
            'parent_id' => $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            'status' => $request->input('status', 'draft'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_in_menu' => (int) $request->input('is_in_menu', 0),
        ];

        if (empty($data['title']) || empty($data['slug'])) {
            $this->adminService->setFlash('error', 'Заголовок и slug обязательны');
            return Response::redirect('/admin/pages/' . $id . '/edit');
        }

        $this->pageService->updatePage($id, $data);
        $this->adminService->setFlash('success', 'Страница обновлена');

        return Response::redirect('/admin/pages/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/admin/pages');
        }

        $id = (int) $request->getAttribute('id');
        $page = $this->pageService->getPage($id);

        if (!$page) {
            $this->adminService->setFlash('error', 'Страница не найдена');
            return Response::redirect('/admin/pages');
        }

        $this->pageService->deletePage($id);
        $this->adminService->setFlash('success', 'Страница удалена');

        return Response::redirect('/admin/pages');
    }
}