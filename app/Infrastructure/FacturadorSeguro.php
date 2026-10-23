<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\FacturadorInterface;

/** Descuento de ejemplo académico, no una póliza de seguros real. */
final class FacturadorSeguro implements FacturadorInterface {
    public function tipo(): string { return 'seguro'; }
    public function calcular(float $montoBase): array {
        $subtotal = round($montoBase, 2);
        $descuento = round($subtotal * 0.20, 2);
        return ['subtotal' => $subtotal, 'descuento' => $descuento, 'total' => round($subtotal - $descuento, 2)];
    }
}
