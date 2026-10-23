<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\CitaRepositoryInterface;
use App\Models\Cita;
use PDO;

final class PostgresCitaRepository implements CitaRepositoryInterface {
    public function __construct(private PDO $db) {}
    public function disponible(Cita $cita): bool {
        $sql = <<<'SQL'
            SELECT s.id FROM schedules s
            WHERE s.doctor_id = :doctor
              AND s.weekday = EXTRACT(DOW FROM CAST(:fecha AS timestamp))
              AND CAST(:hora AS time) >= s.start_time
              AND CAST(:hora AS time) + INTERVAL '30 minutes' <= s.end_time
            FOR SHARE
        SQL;
        $p = $this->db->prepare($sql);
        $p->execute([
            'doctor' => $cita->medicoId,
            'fecha' => $cita->fechaHora,
            'hora' => substr($cita->fechaHora, 11)
        ]);
        if (!$p->fetchColumn()) { return false; }
        $p = $this->db->prepare("SELECT COUNT(*) FROM appointments WHERE scheduled_at = ? AND status <> 'cancelada' AND (doctor_id = ? OR patient_id = ?)");
        $p->execute([$cita->fechaHora, $cita->medicoId, $cita->pacienteId]);
        return (int) $p->fetchColumn() === 0;
    }
    public function guardar(Cita $cita): array {
        $p = $this->db->prepare('INSERT INTO appointments (patient_id, doctor_id, scheduled_at, reason, created_by) VALUES (?, ?, ?, ?, ?) RETURNING id, status, scheduled_at');
        $p->execute([$cita->pacienteId, $cita->medicoId, $cita->fechaHora, $cita->motivo, $cita->creadoPor]);
        return $p->fetch();
    }
}
