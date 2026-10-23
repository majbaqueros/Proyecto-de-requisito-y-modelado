<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\FacturadorInterface;

final class FacturadorNormal implements FacturadorInterface {
    public function tipo(): string { return 'normal'; }
    public function calcular(float $montoBase): array {
        return ['subtotal' => round($montoBase, 2), 'descuento' => 0.0, 'total' => round($montoBase, 2)];
    }
}
