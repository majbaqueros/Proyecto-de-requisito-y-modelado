<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de historiaclinica. */
final class HistoriaClinicaController extends BaseController {
    public function index(array $params = []): void {
        Auth::roles('paciente','medico');
        $where=$this->user['role']==='paciente' ? 'r.patient_id=?' : 'r.doctor_id=?';
        $id=$this->user['role']==='paciente' ? $this->patientId($this->db,$this->user['id']) : $this->doctorId($this->db,$this->user['id']);
        $sql="SELECT r.id,r.appointment_id,r.patient_id,r.created_at,r.symptoms,r.diagnosis,r.treatment,r.notes,up.name AS patient_name,ud.name AS doctor_name FROM clinical_records r JOIN patients p ON p.id=r.patient_id JOIN users up ON up.id=p.user_id JOIN doctors d ON d.id=r.doctor_id JOIN users ud ON ud.id=d.user_id WHERE {$where} ORDER BY r.created_at DESC LIMIT 200";
        Http::json($this->many($this->db,$sql,[$id]));
    
    }
    public function registrar(array $params = []): void {
        Auth::roles('medico');
        $b=Http::body(); Http::requireFields($b,['appointment_id','symptoms','diagnosis','treatment']);
        $c=$this->cita($this->db,(int)$b['appointment_id']);
        if ((int)$c['doctor_user_id']!==$this->user['id']) { Http::fail('Esta cita no corresponde al médico.',403); }
        if (!in_array($c['status'],['confirmada','completada'],true)) { Http::fail('La cita debe estar confirmada o completada.'); }
        if (strtotime($c['scheduled_at']) > time()) { Http::fail('No puedes registrar la consulta antes de la cita.'); }
        $this->db->beginTransaction();
        $record=$this->one($this->db,'INSERT INTO clinical_records(appointment_id,patient_id,doctor_id,symptoms,diagnosis,treatment,notes) VALUES (?,?,?,?,?,?,?) RETURNING id',[
            $c['id'],$c['patient_id'],$c['doctor_id'],trim((string)$b['symptoms']),trim((string)$b['diagnosis']),trim((string)$b['treatment']),trim((string)($b['notes']??''))
        ]);
        $this->execSql($this->db,"UPDATE appointments SET status='completada' WHERE id=?",[$c['id']]);
        $this->db->commit();
        $this->notifySafely($this->notifier,(int)$c['patient_user_id'],'Consulta registrada','El médico registró la atención de la cita #'.$c['id'].'.');
        Http::json(['id'=>$record['id']],201);
    
    }
}
