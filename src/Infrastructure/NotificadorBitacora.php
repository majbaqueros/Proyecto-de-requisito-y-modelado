<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\NotificadorInterface;

/** Extensión OCP de ejemplo: cambia el canal sin cambiar GestorCitas. */
final class NotificadorBitacora implements NotificadorInterface {
    public function enviar(int $usuarioId, string $titulo, string $mensaje): void {
        error_log("Notificación a {$usuarioId}: {$titulo} - {$mensaje}");
    }
}
