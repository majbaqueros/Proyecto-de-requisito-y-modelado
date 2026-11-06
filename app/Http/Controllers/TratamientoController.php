<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{Auth,Http};
use App\Services\PoliticaTratamientos;
use PDO;
use DomainException;

final class TratamientoController extends BaseController {
    public function index(array $params=[]): void {
        $u=Auth::roles('medico','paciente');
        $medico=$u['role']==='medico';
        $id=$medico?$this->doctorId($this->db,(int)$u['id']):$this->patientId($this->db,(int)$u['id']);
        $where=$medico?'t.doctor_id=?':'t.patient_id=?';
        $sql="SELECT t.id,t.clinical_record_id,t.title,t.instructions,t.start_date,t.end_date,t.status,t.created_at,t.updated_at,
                     up.name patient_name,ud.name doctor_name,r.appointment_id
              FROM treatment_plans t JOIN clinical_records r ON r.id=t.clinical_record_id
              JOIN patients p ON p.id=t.patient_id JOIN users up ON up.id=p.user_id
              JOIN doctors d ON d.id=t.doctor_id JOIN users ud ON ud.id=d.user_id
              WHERE {$where} ORDER BY t.created_at DESC,t.id DESC LIMIT 200";
        Http::json($this->many($this->db,$sql,[$id]));
    }
    public function crear(array $params=[]): void {
        $u=Auth::roles('medico');
        $b=Http::body();Http::requireFields($b,['clinical_record_id','title','instructions','start_date']);
        $rid=filter_var($b['clinical_record_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$rid)Http::fail('Historia clínica inválida.',422);
        $titulo=trim((string)$b['title']);$indicaciones=trim((string)$b['instructions']);
        $inicio=(string)$b['start_date'];$fin=trim((string)($b['end_date']??''))?:null;
        (new PoliticaTratamientos())->validarDatos($titulo,$indicaciones,$inicio,$fin);
        $medico=$this->doctorId($this->db,(int)$u['id']);
        $r=$this->one($this->db,
            'SELECT r.patient_id,p.user_id patient_user_id FROM clinical_records r JOIN patients p ON p.id=r.patient_id WHERE r.id=? AND r.doctor_id=?',
            [(int)$rid,$medico]);
        if(!$r)Http::fail('Historia clínica no autorizada.',403);
        $plan=$this->one($this->db,
            'INSERT INTO treatment_plans(clinical_record_id,patient_id,doctor_id,title,instructions,start_date,end_date) VALUES(?,?,?,?,?,?,?) RETURNING id',
            [(int)$rid,(int)$r['patient_id'],$medico,$titulo,$indicaciones,$inicio,$fin]);
        $this->notifySafely($this->notifier,(int)$r['patient_user_id'],'Plan de tratamiento','Tu médico registró un plan de seguimiento.');
        Http::json(['id'=>(int)$plan['id'],'status'=>'activo'],201);
    }
    public function cambiarEstado(array $params=[]): void {
        $u=Auth::roles('medico');$id=(int)($params[1]??0);
        if($id<1)Http::fail('Tratamiento inválido.',422);
        $body=Http::body();Http::requireFields($body,['status']);
        $nuevo=(string)$body['status'];
        $medico=$this->doctorId($this->db,(int)$u['id']);
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT t.status,p.user_id patient_user_id FROM treatment_plans t JOIN patients p ON p.id=t.patient_id WHERE t.id=? AND t.doctor_id=? FOR UPDATE OF t');
            $s->execute([$id,$medico]);$actual=$s->fetch(PDO::FETCH_ASSOC);
            if(!$actual)throw new DomainException('Tratamiento no encontrado o sin permiso.');
            (new PoliticaTratamientos())->validarEstado((string)$actual['status'],$nuevo);
            $s=$this->db->prepare('UPDATE treatment_plans SET status=?,updated_at=NOW() WHERE id=?');
            $s->execute([$nuevo,$id]);$this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        $this->notifySafely($this->notifier,(int)$actual['patient_user_id'],'Seguimiento actualizado','Un plan cambió a '.$nuevo.'.');
        Http::json(['ok'=>true,'status'=>$nuevo]);
    }
}
