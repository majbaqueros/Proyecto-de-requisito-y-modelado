<?php
declare(strict_types=1);
namespace App\Core;

final class Auth {
    public const ROLES = ['paciente', 'recepcionista', 'medico', 'administrador'];
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function requireLogin(): array {
        $user = self::user();
        if (!$user) { Http::fail('Debes iniciar sesión.', 401); }
        return $user;
    }
    public static function roles(string ...$allowed): array {
        $user = self::requireLogin();
        if (!in_array($user['role'], $allowed, true)) { Http::fail('Acceso no autorizado para tu rol.', 403); }
        return $user;
    }
    public static function token(): string {
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf'];
    }
    public static function checkCsrf(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(self::token(), $token)) { Http::fail('Token CSRF inválido.', 419); }
    }
    public static function login(array $dbUser): void {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $dbUser['id'],
            'name' => $dbUser['name'],
            'email' => $dbUser['email'],
            'role' => $dbUser['role']
        ];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
