<?php
declare(strict_types=1);
namespace App\Contracts;

interface FacturaRepositoryInterface {
    public function guardar(int $citaId, string $tipo, array $calculo): array;
}
