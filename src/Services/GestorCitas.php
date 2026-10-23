<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\CitaRepositoryInterface;
use App\Contracts\NotificadorInterface;
use App\Models\Cita;

final class GestorCitas {
    public function __construct(
        private CitaRepositoryInterface $repositorio,
        private NotificadorInterface $notificador,
        private ValidadorDisponibilidad $validador
    ) {}
    public function agendar(Cita $cita, int $usuarioPacienteId): array {
        $this->validador->verificar($cita);
        $registrada = $this->repositorio->guardar($cita);
        // La cita ya ha sido persistida: los fallos de aviso no deben revertirla.
        try {
            $this->notificador->enviar(
                $usuarioPacienteId,
                'Cita registrada',
                'Tu cita médica fue registrada para el ' . $cita->fechaHora . '. Estado: pendiente.'
            );
        } catch (\Throwable $e) { error_log('Notificación fallida: ' . $e->getMessage()); }
        return $registrada;
    }
}
