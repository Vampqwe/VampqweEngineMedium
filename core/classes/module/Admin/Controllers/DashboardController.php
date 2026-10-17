<?php
declare(strict_types=1);

namespace Core\Module\Admin\Controllers;

use Core\Module\Admin\Services\AdminService;
use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Template\Template;

class DashboardController
{
    private AdminService $adminService;
    private Template $template;

    public function __construct(AdminService $adminService, Template $template)
    {
        $this->adminService = $adminService;
        $this->template = $template;
    }

    public function index(Request $request): Response
    {
        $stats = $this->adminService->getDashboardStats();
        $recentPages = $this->adminService->getRecentPages(5);
        $recentActivity = $this->adminService->getRecentActivity(4);
        $flash = $this->adminService->getFlash();
        $user = $_SESSION['admin_user'] ?? null;

        $html = $this->template->render('admin/pages/dashboard', [
            'title' => 'Дашборд',
            'stats' => $stats,
            'recentPages' => $recentPages,
            'recentActivity' => $recentActivity,
            'flash' => $flash,
            'user' => $user,
        ]);

        return Response::html($html);
    }
}