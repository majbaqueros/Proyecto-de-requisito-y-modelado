<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de catalogo. */
final class CatalogoController extends BaseController {
    public function especialidades(array $params = []): void {
        Http::json($this->many($this->db, 'SELECT * FROM specialties ORDER BY name'));
    
    }
    public function crearEspecialidad(array $params = []): void {
        Auth::roles('administrador');
        $b = Http::body(); Http::requireFields($b, ['name']);
        $item = $this->one($this->db, 'INSERT INTO specialties(name) VALUES(?) RETURNING *', [trim((string)$b['name'])]);
        Http::json($item, 201);
    
    }
    public function medicos(array $params = []): void {
        Http::json($this->many($this->db, 'SELECT d.id,d.user_id,u.name,u.email,u.phone,s.name AS specialty,d.specialty_id,d.consultation_price FROM doctors d JOIN users u ON u.id=d.user_id JOIN specialties s ON s.id=d.specialty_id WHERE u.active = TRUE ORDER BY u.name'));
    
    }
    public function crearMedico(array $params = []): void {
        Auth::roles('administrador');
        $b = Http::body(); Http::requireFields($b, ['name','email','password','specialty_id','consultation_price']);
        if (!is_numeric($b['consultation_price']) || (float)$b['consultation_price'] < 0) { Http::fail('Precio inválido.'); }
        $this->db->beginTransaction();
        $uid = $this->createUser( $b, 'medico');
        $row = $this->one($this->db, 'INSERT INTO doctors(user_id,specialty_id,consultation_price) VALUES (?,?,?) RETURNING id', [$uid,(int)$b['specialty_id'],round((float)$b['consultation_price'],2)]);
        $this->db->commit(); Http::json(['id' => $row['id']], 201);
    
    }
}
