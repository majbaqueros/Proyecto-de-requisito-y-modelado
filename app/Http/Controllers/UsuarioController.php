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
}
