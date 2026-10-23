<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

/** Una sola responsabilidad: reglas para eliminar franjas de atención. */
final class ServicioHorarios {
    public function __construct(private PDO $db) {}

    /** @return array{status:string,appointments?:int} */
    public function eliminar(int $id): array {
        $this->db->beginTransaction();
        try {
            // Bloqueo de la franja: evita que se reserve en paralelo si el repositorio de citas
            // obtiene FOR SHARE sobre el horario en la misma transacción de agendamiento.
            $stmt = $this->db->prepare('SELECT id,doctor_id,weekday,start_time,end_time FROM schedules WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $schedule=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$schedule) {
                $this->db->rollBack();
                return ['status'=>'not_found'];
            }
            $count=$this->db->prepare(<<<'SQL'
                SELECT COUNT(*) FROM appointments a
                WHERE a.doctor_id = ?
                  AND a.status IN ('pendiente','confirmada')
                  AND a.scheduled_at >= CURRENT_TIMESTAMP
                  AND EXTRACT(DOW FROM a.scheduled_at) = ?
                  AND a.scheduled_at::time >= CAST(? AS time)
                  AND (a.scheduled_at::time + INTERVAL '30 minutes') <= CAST(? AS time)
            SQL);
            $count->execute([
                $schedule['doctor_id'],$schedule['weekday'],
                $schedule['start_time'],$schedule['end_time']
            ]);
            $pending=(int)$count->fetchColumn();
            if ($pending > 0) {
                $this->db->rollBack();
                return ['status'=>'blocked','appointments'=>$pending];
            }
            $delete=$this->db->prepare('DELETE FROM schedules WHERE id=?');
            $delete->execute([$id]);
            $this->db->commit();
            return ['status'=>'deleted'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }
}
