<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Core/Autoload.php';

use App\Core\{Auth, Database, Http};
use App\Http\Router;
use App\Infrastructure\NotificadorEnSistema;

date_default_timezone_set(getenv('APP_TZ') ?: 'America/La_Paz');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . $path)) { return false; }
if (!str_starts_with($path, '/api/')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__) . '/resources/views/app.html');
    exit;
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
ini_set('session.use_strict_mode', '1');
session_name('clinic_session');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','PATCH','DELETE'], true)) { Http::fail('Método no permitido', 405); }
if ($method !== 'GET' && !in_array($path, ['/api/auth/login', '/api/auth/forgot-password', '/api/auth/reset-password'], true)) { Auth::checkCsrf(); }
try {
    $db = Database::connect();
    (new Router($db, new NotificadorEnSistema($db)))->dispatch($path,$method);
} catch (DomainException $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    Http::fail($e->getMessage(),422);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    error_log('Error de base de datos: '.$e->getMessage());
    if ($e->getCode()==='23505') { Http::fail('El registro ya existe o ese horario ya está ocupado.',409); }
    if ($e->getCode()==='23503') { Http::fail('Recurso relacionado inexistente.',422); }
    if ($e->getCode()==='23514' || $e->getCode()==='22007') { Http::fail('Datos inválidos.',422); }
    Http::fail('Error interno de base de datos. Revisa la instalación.',500);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    error_log('Error interno: '.$e->getMessage());
    Http::fail('Error interno del servidor.',500);
}
