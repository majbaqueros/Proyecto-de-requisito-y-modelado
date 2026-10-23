<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\FacturaRepositoryInterface;
use PDO;

final class PostgresFacturaRepository implements FacturaRepositoryInterface {
    public function __construct(private PDO $db) {}
    public function guardar(int $citaId, string $tipo, array $calculo): array {
        $p = $this->db->prepare('INSERT INTO invoices (appointment_id, billing_type, subtotal, discount, total) VALUES (?,?,?,?,?) RETURNING *');
        $p->execute([$citaId, $tipo, $calculo['subtotal'], $calculo['descuento'], $calculo['total']]);
        return $p->fetch();
    }
}
