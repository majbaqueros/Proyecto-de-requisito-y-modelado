<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\CitaRepositoryInterface;
use App\Models\Cita;
use DomainException;

final class ValidadorDisponibilidad {
    public function __construct(private CitaRepositoryInterface $repositorio) {}
    public function verificar(Cita $cita): void {
        $hora = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $cita->fechaHora);
        if (!$hora || $hora->format('Y-m-d H:i:s') !== $cita->fechaHora) {
            throw new DomainException('Fecha y hora inválidas.');
        }
        $zona = new \DateTimeZone(getenv('APP_TZ') ?: 'America/La_Paz');
        $ahora = new \DateTimeImmutable('now', $zona);
        $elegida = new \DateTimeImmutable($cita->fechaHora, $zona);
        if ($elegida <= $ahora) { throw new DomainException('La cita debe ser futura.'); }
        if ((int) $hora->format('i') % 30 !== 0 || $hora->format('s') !== '00') {
            throw new DomainException('Las citas deben iniciar en intervalos de 30 minutos.');
        }
        if (!$this->repositorio->disponible($cita)) {
            throw new DomainException('Horario no disponible para ese médico o paciente.');
        }
    }
}
