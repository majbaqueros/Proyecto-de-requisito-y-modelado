<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\NotificadorInterface;
use PDO;

final class NotificadorEnSistema implements NotificadorInterface {
    public function __construct(private PDO $db) {}
    public function enviar(int $usuarioId, string $titulo, string $mensaje): void {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
        $stmt->execute([$usuarioId, $titulo, $mensaje]);
    }
}
