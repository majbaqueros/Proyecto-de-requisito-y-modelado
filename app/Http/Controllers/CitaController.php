<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de cita. */
final class CitaController extends BaseController {
    public function index(array $params = []): void {
        $args=[]; $where='TRUE';
        if ($this->user['role']==='paciente') { $where='a.patient_id=?'; $args[]=$this->patientId($this->db,$this->user['id']); }
        if ($this->user['role']==='medico') { $where='a.doctor_id=?'; $args[]=$this->doctorId($this->db,$this->user['id']); }
        $sql="SELECT a.id,a.patient_id,a.doctor_id,a.scheduled_at,a.reason,a.status,a.created_at,up.name AS patient_name,ud.name AS doctor_name,s.name AS specialty FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users up ON up.id=p.user_id JOIN doctors d ON d.id=a.doctor_id JOIN users ud ON ud.id=d.user_id JOIN specialties s ON s.id=d.specialty_id WHERE {$where} ORDER BY a.scheduled_at DESC LIMIT 300";
        Http::json($this->many($this->db,$sql,$args));
    
    }
    public function agendar(array $params = []): void {
        Auth::roles('paciente','recepcionista','administrador');
        $b=\App\Http\Requests\AgendarCitaRequest::validar(Http::body());
        $pid=$this->user['role']==='paciente' ? $this->patientId($this->db,$this->user['id']) : (int)($b['patient_id']??0);
        if (!$pid) { Http::fail('Selecciona un paciente.'); }
        $profile=$this->one($this->db,'SELECT user_id FROM patients WHERE id=?',[$pid]);
        if (!$profile) { Http::fail('Paciente no encontrado.'); }
        $date=(string)$b['scheduled_at'];
        $model=new Cita($pid,(int)$b['doctor_id'],$date,trim((string)$b['reason']),$this->user['id']);
        $repo=new PostgresCitaRepository($this->db);
        $gestor=new GestorCitas($repo,$this->notifier,new ValidadorDisponibilidad($repo));
        Http::json($gestor->agendar($model,(int)$profile['user_id']),201);
    
    }
    public function cambiarEstado(array $params = []): void {
        $c=$this->cita($this->db,(int)$params[1]); $this->canSeeCita($this->db,$this->user,$c);
        $b=Http::body(); Http::requireFields($b,['status']); $target=(string)$b['status'];
        try {
            (new \App\Services\PoliticaEstadosCita())->validar(
                (string)$c['status'], $target, (string)$this->user['role'], (string)$c['scheduled_at']
            );
        } catch (\DomainException $e) { Http::fail($e->getMessage(), 422); }
        $changed=$this->execSql($this->db,'UPDATE appointments SET status=? WHERE id=? AND status=?',[$target,$c['id'],$c['status']]);
        if (!$changed) { Http::fail('La cita cambió; vuelve a cargar.',409); }
        $this->notifySafely($this->notifier,(int)$c['patient_user_id'],'Estado de cita','Tu cita #'.$c['id'].' cambió a '.$target.'.');
        Http::json(['ok'=>true,'status'=>$target]);
    
    }
}
