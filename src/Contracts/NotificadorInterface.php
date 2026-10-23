<?php
declare(strict_types=1);
namespace App\Contracts;

interface NotificadorInterface {
    public function enviar(int $usuarioId, string $titulo, string $mensaje): void;
}
