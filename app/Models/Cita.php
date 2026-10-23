<?php
declare(strict_types=1);
namespace App\Models;

final readonly class Cita {
    public function __construct(
        public int $pacienteId,
        public int $medicoId,
        public string $fechaHora,
        public string $motivo,
        public int $creadoPor,
    ) {}
}
