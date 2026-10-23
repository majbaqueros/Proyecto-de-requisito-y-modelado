<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de notificacion. */
final class NotificacionController extends BaseController {
    public function index(array $params = []): void {
        Http::json($this->many($this->db,'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$this->user['id']]));
    
    }
    public function marcarLeida(array $params = []): void {
        $n=$this->execSql($this->db,'UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?',[(int)$params[1],$this->user['id']]);
        if (!$n) { Http::fail('Notificación no encontrada.',404); }
        Http::json(['ok'=>true]);
    
    }
}
