<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;

/** Única responsabilidad: permitir/prohibir las transiciones de estado de las citas. */
final class PoliticaEstadosCita {
    public function validar(string $actual, string $nuevo, string $rol, string $fechaHora): void {
        $permitidosPorRol = [
            'paciente' => ['cancelada'],
            'recepcionista' => ['confirmada','cancelada'],
            'administrador' => ['confirmada','cancelada'],
            'medico' => ['completada']
        ];
        if (!in_array($nuevo, $permitidosPorRol[$rol] ?? [], true)) {
            throw new DomainException('Tu rol no puede asignar ese estado.');
        }
        $transiciones = [
            'pendiente' => ['confirmada','cancelada'],
            'confirmada' => ['completada','cancelada']
        ];
        if (!in_array($nuevo, $transiciones[$actual] ?? [], true)) {
            throw new DomainException('Transición de estado no permitida.');
        }
        if ($rol === 'paciente' && strtotime($fechaHora) <= time()) {
            throw new DomainException('No puedes cancelar una cita pasada.');
        }
        if ($rol === 'medico' && strtotime($fechaHora) > time()) {
            throw new DomainException('No puedes completar una cita futura.');
        }
    }
}
