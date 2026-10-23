<?php
declare(strict_types=1);
/**
 * Diagnóstico LOCAL de SaludCita / Mailpit.
 * No cambia contraseñas, no emite códigos reales y no imprime secretos.
 * Solo envía un mensaje de prueba al buzón local de Mailpit.
 */
require_once dirname(__DIR__).'/app/Core/Autoload.php';

use App\Core\Database;
use App\Infrastructure\CorreoSmtp;

$email = strtolower(trim((string)($argv[1] ?? 'paciente@clinica.local')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Correo de prueba no válido.\n");
    exit(2);
}

$host = (string)(getenv('SMTP_HOST') ?: '');
$port = (string)(getenv('SMTP_PORT') ?: '');
$mode = (string)(getenv('SMTP_ENCRYPTION') ?: '');
$from = (string)(getenv('SMTP_FROM') ?: '');
$root = dirname(__DIR__);
$key = (string)(getenv('APP_KEY') ?: '');
$keyFile = trim((string)(getenv('APP_KEY_FILE') ?: ''));
if ($keyFile==='') $keyFile = $root.'/storage/app.key';
$keyIsReadable = is_file($keyFile) && is_readable($keyFile);
$keyContent = $keyIsReadable ? @file_get_contents($keyFile) : false;
$keyReady = strlen($key)>=32 || ($keyContent!==false && strlen(trim($keyContent))>=32);
$effectiveUser = function_exists('posix_geteuid') ? @posix_getpwuid(posix_geteuid()) : null;
echo 'Usuario de ejecución PHP: '.($effectiveUser['name'] ?? 'desconocido')."\n";
if (strlen($key)<32) echo 'Archivo de clave legible por PHP: '.($keyIsReadable?'SÍ':'NO')."\n";
echo 'Almacenamiento de clave: '.($keyFile===$root.'/storage/app.key'?'carpeta local':'volumen privado de Docker')."\n";

echo "Diagnóstico SaludCita: recuperación por correo\n";
echo "SMTP_HOST: ".($host ?: '(sin configurar)')."\n";
echo "SMTP_PORT: ".($port ?: '(sin configurar)')."\n";
echo "SMTP_ENCRYPTION: ".($mode ?: '(sin configurar)')."\n";
echo "SMTP_FROM: ".($from ?: '(sin configurar)')."\n";
echo "Clave de recuperación (APP_KEY o storage/app.key): ".($keyReady?'OK':'FALTA')."\n";

try {
    $db=Database::connect();
    $stmt=$db->prepare('SELECT active FROM users WHERE email=?');
    $stmt->execute([$email]);
    $active=$stmt->fetchColumn();
    echo "Usuario de prueba: ".($active === false?'NO REGISTRADO':(in_array($active,[true,1,'1','t'],true)?'ACTIVO':'INACTIVO'))."\n";
    $stmt=$db->prepare('SELECT EXISTS (SELECT 1 FROM password_resets r JOIN users u ON r.user_id=u.id WHERE u.email=? AND r.expires_at>NOW())');
    $stmt->execute([$email]);
    echo "Solicitud de recuperación vigente: ".(in_array($stmt->fetchColumn(),[true,1,'1','t'],true)?'SÍ':'NO')."\n";
} catch (Throwable $e) {
    fwrite(STDERR,"La comprobación de base de datos falló: ".$e->getMessage()."\n");
    exit(1);
}

if (!$keyReady) {
    fwrite(STDERR,"Falta la clave o permisos. Ejecuta: docker compose exec app php scripts/install.php\n");
    exit(1);
}
if ($host !== 'mailpit' || $port !== '1025' || $mode !== 'none') {
    fwrite(STDERR,"El diagnóstico solo envía un correo de prueba usando Mailpit local (mailpit:1025, sin cifrado).\n");
    exit(1);
}
try {
    (new CorreoSmtp())->enviar($email, 'SaludCita: prueba de Mailpit', 'Este es un mensaje de prueba. No incluye códigos ni modifica contraseñas.');
    echo "ENVÍO DE PRUEBA CORRECTO. Abre http://localhost:8025\n";
} catch (Throwable $e) {
    fwrite(STDERR,"ERROR SMTP: ".$e->getMessage()."\n");
    exit(1);
}
