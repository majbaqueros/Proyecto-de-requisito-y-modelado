<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\FacturaRepositoryInterface;
use App\Contracts\FacturadorInterface;

final class ServicioCobros {
    public function __construct(
        private FacturadorInterface $facturador,
        private FacturaRepositoryInterface $repositorio
    ) {}
    public function generar(int $citaId, float $precio): array {
        $calculo = $this->facturador->calcular($precio);
        return $this->repositorio->guardar($citaId, $this->facturador->tipo(), $calculo);
    }
}
