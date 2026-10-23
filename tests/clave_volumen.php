<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';

final class DbSimuladaClave extends PDO { public function __construct() {} }
final class CorreoSimuladoClave implements \App\Contracts\CorreoInterface {
    public function enviar(string $destino, string $asunto, string $mensaje): void {}
}

$key=tempnam(sys_get_temp_dir(),'saludcita_test_key_');
if($key===false)throw new RuntimeException('No se pudo crear archivo de prueba');
putenv('APP_KEY=');
putenv('APP_KEY_FILE='.$key);
try {
    file_put_contents($key,bin2hex(random_bytes(32)));
    $service=new \App\Services\RecuperacionClave(new DbSimuladaClave(),new CorreoSimuladoClave());
    if(!$service)throw new RuntimeException('No se pudo cargar la clave desde APP_KEY_FILE.');
    echo "OK: Recuperación utiliza clave existente en APP_KEY_FILE\n";
    file_put_contents($key,'invalida');
    try {
        new \App\Services\RecuperacionClave(new DbSimuladaClave(),new CorreoSimuladoClave());
        throw new RuntimeException('Debió rechazar una clave demasiado corta.');
    } catch (RuntimeException $e) {
        if(str_contains($e->getMessage(),'Debió'))throw $e;
    }
    echo "OK: Recuperación rechaza clave inválida\n";
} finally {
    @unlink($key);
}
