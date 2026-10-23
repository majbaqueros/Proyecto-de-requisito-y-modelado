<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de dashboard. */
final class DashboardController extends BaseController {
    public function index(array $params = []): void {
        $role = $this->user['role'];
        $condition = 'TRUE'; $arg = [];
        if ($role === 'paciente') { $condition = 'a.patient_id = ?'; $arg[] = $this->patientId($this->db, $this->user['id']); }
        if ($role === 'medico') { $condition = 'a.doctor_id = ?'; $arg[] = $this->doctorId($this->db, $this->user['id']); }
        $counts = $this->one($this->db, "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE a.status = 'pendiente') AS pendientes, COUNT(*) FILTER (WHERE a.status = 'confirmada') AS confirmadas, COUNT(*) FILTER (WHERE a.status = 'completada') AS completadas FROM appointments a WHERE {$condition}", $arg);
        $recent = $this->many($this->db, "SELECT a.id,a.scheduled_at,a.status,up.name AS patient_name,ud.name AS doctor_name FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users up ON up.id=p.user_id JOIN doctors d ON d.id=a.doctor_id JOIN users ud ON ud.id=d.user_id WHERE {$condition} ORDER BY a.scheduled_at DESC LIMIT 5", $arg);
        $unread = $this->one($this->db, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND read_at IS NULL', [$this->user['id']]);
        Http::json(['counts' => $counts, 'recent' => $recent, 'unread' => (int)$unread['n']]);
    
    }
}
