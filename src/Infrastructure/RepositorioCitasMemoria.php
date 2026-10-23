<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\CitaRepositoryInterface;
use App\Models\Cita;

/** Adaptador alternativo para probar OCP sin PostgreSQL. */
final class RepositorioCitasMemoria implements CitaRepositoryInterface {
    private array $citas = [];
    public function disponible(Cita $cita): bool {
        foreach ($this->citas as $existente) {
            if ($existente->fechaHora === $cita->fechaHora &&
                ($existente->medicoId === $cita->medicoId || $existente->pacienteId === $cita->pacienteId)) {
                return false;
            }
        }
        return true;
    }
    public function guardar(Cita $cita): array {
        $this->citas[] = $cita;
        return ['id' => count($this->citas), 'status' => 'pendiente', 'scheduled_at' => $cita->fechaHora];
    }
}
