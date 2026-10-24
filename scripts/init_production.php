<?php
declare(strict_types=1);
// Instalación inicial/actualización idempotente SIN datos de demostración.
// Una única réplica de web en Railway. No sustituye migraciones versionadas futuras.
require_once dirname(__DIR__) . '/app/Core/Autoload.php';
use App\Core\Database;

if (getenv('APP_ENV') !== 'production') {
    fwrite(STDERR, "Solo disponible para APP_ENV=production.\n"); exit(1);
}
$key = (string)(getenv('APP_KEY') ?: '');
if (strlen($key) < 32) {
    fwrite(STDERR, "APP_KEY no configurada; define una clave aleatoria de 64 caracteres hexadecimales en Railway Variables.\n");
    exit(1);
}
$root = dirname(__DIR__);
$db = null;
for ($attempt=1; $attempt<=20; $attempt++) {
    try { $db=Database::connect(); break; }
    catch (PDOException $e) {
        fwrite(STDERR, "Conectando a PostgreSQL (intento {$attempt}/20)...\n");
        sleep(3);
    }
}
if (!$db) { fwrite(STDERR, "No se pudo conectar a PostgreSQL. Revisa DB_HOST, DB_USER, DB_NAME y DB_PASSWORD.\n"); exit(1); }
try {
    $db->exec((string)file_get_contents($root.'/database/schema.sql'));
    $db->exec((string)file_get_contents($root.'/database/migrations/005_security.sql'));
    $adminCount=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='administrador' AND active=TRUE")->fetchColumn();
    if ($adminCount===0) {
        $email=strtolower(trim((string)(getenv('CLOUD_ADMIN_EMAIL') ?: '')));
        $password=(string)(getenv('CLOUD_ADMIN_PASSWORD') ?: '');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
            throw new RuntimeException('Configura CLOUD_ADMIN_EMAIL y CLOUD_ADMIN_PASSWORD (mínimo 12 caracteres) para crear el administrador inicial.');
        }
        $check=$db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetchColumn()) throw new RuntimeException('La cuenta CLOUD_ADMIN_EMAIL existe, pero no es un administrador activo. Revisa el estado antes de continuar.');
        $stmt=$db->prepare("INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,'administrador',TRUE)");
        $stmt->execute(['Administrador SaludCita',$email,password_hash($password,PASSWORD_DEFAULT)]);
        echo "Cuenta administrativa inicial creada. Retira CLOUD_ADMIN_PASSWORD del panel de Railway después del despliegue.\n";
    }
    echo "SaludCita: esquema de PostgreSQL verificado, administrador disponible.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"Inicialización fallida: ".$e->getMessage()."\n");
    exit(1);
}
