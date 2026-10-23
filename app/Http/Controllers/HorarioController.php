<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Services\ServicioHorarios;

/** Gestión de la disponibilidad semanal, independiente del catálogo de médicos. */
final class HorarioController extends BaseController {
    public function index(array $params = []): void {
        // Los perfiles autenticados pueden consultar horarios; solo el administrador puede modificarlos.
        $args = [];
        $where = '';
        if (isset($_GET['doctor_id']) && ctype_digit((string)$_GET['doctor_id'])) {
            $where = 'WHERE s.doctor_id = ?';
            $args[] = (int)$_GET['doctor_id'];
        }
        $sql = <<<SQL
            SELECT s.id,s.doctor_id,s.weekday,s.start_time,s.end_time,
                   u.name AS doctor_name,
                   (SELECT COUNT(*) FROM appointments a
                    WHERE a.doctor_id=s.doctor_id
                      AND a.status IN ('pendiente','confirmada')
                      AND a.scheduled_at >= CURRENT_TIMESTAMP
                      AND EXTRACT(DOW FROM a.scheduled_at) = s.weekday
                      AND a.scheduled_at::time >= s.start_time
                      AND (a.scheduled_at::time + INTERVAL '30 minutes') <= s.end_time
                   )::int AS future_appointments
            FROM schedules s
            JOIN doctors d ON d.id=s.doctor_id
            JOIN users u ON u.id=d.user_id
            {$where}
            ORDER BY u.name,s.weekday,s.start_time
        SQL;
        Http::json($this->many($this->db, $sql, $args));
    }

    public function crear(array $params = []): void {
        Auth::roles('administrador');
        $b = Http::body();
        Http::requireFields($b, ['doctor_id','weekday','start_time','end_time']);
        $time = '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/';
        if (!ctype_digit((string)$b['doctor_id']) || (int)$b['doctor_id'] < 1 ||
            !ctype_digit((string)$b['weekday']) || (int)$b['weekday'] > 6 ||
            !preg_match($time,(string)$b['start_time']) || !preg_match($time,(string)$b['end_time']) ||
            $b['start_time'] >= $b['end_time']) {
            Http::fail('Horario inválido.',422);
        }
        $overlap = $this->one($this->db,
            'SELECT COUNT(*) AS n FROM schedules WHERE doctor_id=? AND weekday=? AND start_time < ? AND end_time > ?',
            [(int)$b['doctor_id'],(int)$b['weekday'],$b['end_time'],$b['start_time']]
        );
        if ((int)$overlap['n'] > 0) { Http::fail('El horario se superpone con otro existente.',409); }
        $row = $this->one($this->db,
            'INSERT INTO schedules(doctor_id,weekday,start_time,end_time) VALUES (?,?,?,?) RETURNING *',
            [(int)$b['doctor_id'],(int)$b['weekday'],$b['start_time'],$b['end_time']]
        );
        Http::json($row,201);
    }

    public function eliminar(array $params = []): void {
        Auth::roles('administrador');
        $id=(int)($params[1]??0);
        if ($id < 1) { Http::fail('Identificador de horario inválido.',422); }
        $result=(new ServicioHorarios($this->db))->eliminar($id);
        if ($result['status']==='not_found') { Http::fail('El horario ya no existe.',404); }
        if ($result['status']==='blocked') {
            Http::fail('No se puede eliminar este horario: hay '.$result['appointments'].' cita(s) futura(s) pendientes o confirmadas. Cancela o reprograma esas citas primero.',409);
        }
        Http::json(['ok'=>true,'message'=>'Horario eliminado correctamente.','id'=>$id]);
    }
}
