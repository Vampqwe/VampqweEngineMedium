<?php
declare(strict_types=1);

namespace Core\Module\Admin\Services;

use Core\System\Config\Config;

/**
 * Проверка супер-администратора из config.env.
 * Супер-админ не хранится в БД — он всегда доступен,
 * даже если БД удалена или повреждена.
 */
class SuperAdminAuth
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Проверить, является ли пользователь супер-админом
     */
    public function isSuperAdmin(string $email, string $password): bool
    {
        $adminEmail = $this->config->get('SUPER_ADMIN_EMAIL', '');
        $adminHash = $this->config->get('SUPER_ADMIN_PASSWORD', '');

        if ($adminEmail === '' || $adminHash === '') {
            return false;
        }

        if (!hash_equals($adminEmail, $email)) {
            return false;
        }

        return password_verify($password, $adminHash);
    }

    /**
     * Получить данные супер-админа
     */
    public function getSuperAdminData(): array
    {
        return [
            'id' => 0, // Супер-админ не в БД
            'email' => $this->config->get('SUPER_ADMIN_EMAIL', ''),
            'name' => $this->config->get('SUPER_ADMIN_NAME', 'Супер Администратор'),
            'role' => 'super_admin',
            'is_super' => true,
        ];
    }

    /**
     * Сгенерировать хэш пароля (для CLI-утилит)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}