<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de usuario. */
final class UsuarioController extends BaseController {
    public function pacientes(array $params = []): void {
        Auth::roles('administrador','recepcionista');
        Http::json($this->many($this->db, 'SELECT p.id,p.user_id,p.document,p.birth_date,p.address,u.name,u.email,u.phone FROM patients p JOIN users u ON u.id=p.user_id ORDER BY u.name'));
    
    }
    public function crearPaciente(array $params = []): void {
        Auth::roles('administrador','recepcionista');
        $b=\App\Http\Requests\RegistrarPacienteRequest::validar(Http::body());
        $this->db->beginTransaction();
        $uid=$this->createUser($b,'paciente');
        $row=$this->one($this->db,'INSERT INTO patients(user_id,document,birth_date,address) VALUES (?,?,?,?) RETURNING id',[$uid,trim((string)$b['document']),($b['birth_date']??'')?:null,trim((string)($b['address']??''))]);
        $this->db->commit(); Http::json(['id'=>$row['id']],201);
    
    }
    public function usuarios(array $params = []): void {
        Auth::roles('administrador');
        Http::json($this->many($this->db,'SELECT id,name,email,role,phone,active,created_at FROM users ORDER BY id'));
    
    }
    public function crearUsuario(array $params = []): void {
        Auth::roles('administrador');
        $b=Http::body(); Http::requireFields($b,['name','email','password','role']);
        if (!in_array($b['role'],['administrador','recepcionista'],true)) { Http::fail('Utiliza los formularios específicos para médicos y pacientes.'); }
        $id=$this->createUser($b,$b['role']); Http::json(['id'=>$id],201);
    
    }
    public function eliminarUsuario(array $params=[]): void {
        $actor=Auth::roles('administrador');
        $id=(int)($params[1]??0);
        if($id<1)Http::fail('ID de usuario inválido.',422);
        $result=(new \App\Services\ServicioUsuarios($this->db))->cambiarActivo($id,false,(int)$actor['id']);
        if($result['status']==='not_found')Http::fail('Usuario no encontrado.',404);
        if($result['status']==='self')Http::fail('No puedes eliminar tu propia cuenta.',409);
        if($result['status']==='last_admin')Http::fail('No se puede eliminar el último administrador activo.',409);
        Http::json(['ok'=>true,'message'=>'Usuario desactivado. Sus citas e historial se conservaron para auditoría.']);
    }
    public function restaurarUsuario(array $params=[]): void {
        $actor=Auth::roles('administrador');
        $id=(int)($params[1]??0);
        if($id<1)Http::fail('ID de usuario inválido.',422);
        $result=(new \App\Services\ServicioUsuarios($this->db))->cambiarActivo($id,true,(int)$actor['id']);
        if($result['status']==='not_found')Http::fail('Usuario no encontrado.',404);
        Http::json(['ok'=>true,'message'=>'Cuenta reactivada correctamente.']);
    }
}
