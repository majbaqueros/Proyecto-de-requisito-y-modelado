<?php
declare(strict_types=1);
namespace App\Contracts;

interface FacturadorInterface {
    public function tipo(): string;
    /** @return array{subtotal:float,descuento:float,total:float} */
    public function calcular(float $montoBase): array;
}
