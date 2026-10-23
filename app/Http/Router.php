<?php
declare(strict_types=1);
namespace App\Http;

use App\Core\{Auth, Http};
use App\Contracts\NotificadorInterface;
use PDO;

/** Front Controller -> Router -> Controller -> Service -> Repository. */
final class Router {
    public function __construct(private PDO $db, private NotificadorInterface $notifier) {}

    public function dispatch(string $path, string $method): void {
        $routes = require dirname(__DIR__, 2) . '/routes/api.php';
        foreach ($routes as [$verb, $pattern, $class, $action, $public]) {
            if ($verb !== $method || !preg_match($pattern, $path, $params)) { continue; }
            $user = $public ? Auth::user() : Auth::requireLogin();
            $controller = new $class($this->db, $user, $this->notifier);
            $controller->$action($params);
            return;
        }
        Http::fail('Ruta no encontrada.', 404);
    }
}
