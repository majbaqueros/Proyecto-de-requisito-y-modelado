<?php
declare(strict_types=1);
namespace App\Contracts;
use App\Models\Cita;

interface CitaRepositoryInterface {
    public function disponible(Cita $cita): bool;
    public function guardar(Cita $cita): array;
}
