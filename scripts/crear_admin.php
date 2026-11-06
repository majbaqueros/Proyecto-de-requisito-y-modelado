<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Core\Database;

/**
 * Crear un administrador desde una terminal confiable del contenedor.
 * No usar SQL para convertir cuentas de paciente o médico en administrador.
 * Ejecuta: docker compose exec -it app php scripts/crear_admin.php
 */
if (PHP_SAPI !== 'cli' || !function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    fwrite(STDERR,"Se requiere terminal interactiva (TTY). No pasar contraseñas como argumentos.\n");
    exit(1);
}
function prompt(string $label): string {
    fwrite(STDOUT,$label);
    $value=fgets(STDIN);
    if ($value === false) throw new RuntimeException('Entrada cancelada.');
    return trim($value);
}
function secret(string $label): string {
    fwrite(STDOUT,$label);
    if (DIRECTORY_SEPARATOR === '/') @shell_exec('stty -echo');
    try {
        $value=fgets(STDIN);
    } finally {
        if (DIRECTORY_SEPARATOR === '/') @shell_exec('stty echo');
        fwrite(STDOUT,"\n");
    }
    if($value===false) throw new RuntimeException('Entrada cancelada.');
    return rtrim($value,"\r\n");
}
try {
    $name=prompt('Nombre del nuevo administrador: ');
    $email=strtolower(prompt('Correo electrónico: '));
    if(strlen($name)<3 || strlen($name)>120)throw new RuntimeException('Nombre inválido (3-120 caracteres).');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Correo inválido.');
    $password=secret('Contraseña nueva (mínimo 12 caracteres): ');
    $confirm=secret('Repetir contraseña: ');
    if(strlen($password)<12 || strlen($password)>200 || !hash_equals($password,$confirm))
        throw new RuntimeException('Las contraseñas no coinciden o no cumplen la longitud.');
    $db=Database::connect();
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT id FROM users WHERE email=? FOR UPDATE');
        $stmt->execute([$email]);
        if($stmt->fetchColumn())throw new RuntimeException('Ese correo ya existe: crea una cuenta nueva desde Administración → Usuarios.');
        $stmt=$db->prepare("INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,'administrador',TRUE) RETURNING id");
        $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $id=(int)$stmt->fetchColumn();
        $db->commit();
    } catch (\Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
    fwrite(STDOUT,"Administrador #{$id} creado. La contraseña no se mostró ni se guardó en texto plano.\n");
} catch(\Throwable $e) {
    fwrite(STDERR,"No se pudo crear el administrador: ".$e->getMessage()."\n");
    exit(1);
}
