<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de factura. */
final class FacturaController extends BaseController {
    public function index(array $params = []): void {
        Auth::roles('paciente','recepcionista','administrador');
        $args=[]; $where='TRUE';
        if ($this->user['role']==='paciente') { $where='a.patient_id=?'; $args[]=$this->patientId($this->db,$this->user['id']); }
        if ($this->user['role']==='medico') { $where='a.doctor_id=?'; $args[]=$this->doctorId($this->db,$this->user['id']); }
        $sql="SELECT i.*,a.scheduled_at,up.name AS patient_name,ud.name AS doctor_name FROM invoices i JOIN appointments a ON a.id=i.appointment_id JOIN patients p ON p.id=a.patient_id JOIN users up ON up.id=p.user_id JOIN doctors d ON d.id=a.doctor_id JOIN users ud ON ud.id=d.user_id WHERE {$where} ORDER BY i.issued_at DESC";
        Http::json($this->many($this->db,$sql,$args));
    
    }
    public function emitir(array $params = []): void {
        Auth::roles('recepcionista','administrador');
        $b=Http::body(); Http::requireFields($b,['appointment_id','billing_type']);
        $c=$this->cita($this->db,(int)$b['appointment_id']);
        if (!in_array($c['status'],['confirmada','completada'],true)) { Http::fail('Solo se factura una cita confirmada o completada.'); }
        $med=$this->one($this->db,'SELECT consultation_price FROM doctors WHERE id=?',[$c['doctor_id']]);
        $type=(string)$b['billing_type'];
        $calculator=(new \App\Services\CatalogoFacturadores(
            new FacturadorNormal(), new FacturadorSeguro()
        ))->seleccionar($type);
        $servicio=new ServicioCobros($calculator,new PostgresFacturaRepository($this->db));
        $invoice=$servicio->generar((int)$c['id'],(float)$med['consultation_price']);
        $this->notifySafely($this->notifier,(int)$c['patient_user_id'],'Factura emitida','Se generó la factura #'.$invoice['id'].' por Bs '.$invoice['total'].'.');
        Http::json($invoice,201);
    
    }
    public function pagar(array $params = []): void {
        Auth::roles('administrador','recepcionista');
        $invoice=$this->one($this->db,'SELECT i.*,p.user_id AS patient_user_id FROM invoices i JOIN appointments a ON a.id=i.appointment_id JOIN patients p ON p.id=a.patient_id WHERE i.id=?',[(int)$params[1]]);
        if (!$invoice) { Http::fail('Factura no encontrada.',404); }
        if ($invoice['status']==='pagada') { Http::fail('La factura ya está pagada.'); }
        $this->execSql($this->db,"UPDATE invoices SET status='pagada',paid_at=NOW() WHERE id=? AND status='emitida'",[(int)$params[1]]);
        $this->notifySafely($this->notifier,(int)$invoice['patient_user_id'],'Pago registrado','Se registró el pago de tu factura #'.$params[1].'.');
        Http::json(['ok'=>true]);
    
    }
}
