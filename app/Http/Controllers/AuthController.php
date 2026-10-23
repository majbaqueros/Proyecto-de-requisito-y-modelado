<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Models\Cita;
use App\Services\{GestorCitas, ServicioCobros, ValidadorDisponibilidad};
use App\Infrastructure\{PostgresCitaRepository, FacturadorNormal, FacturadorSeguro, PostgresFacturaRepository};

/** Controlador de auth. */
final class AuthController extends BaseController {
    public function login(array $params = []): void {
        $b = Http::body(); Http::requireFields($b, ['email','password','role']);
        if (!in_array($b['role'], Auth::ROLES, true)) { Http::fail('Rol inválido.', 422); }
        $record = $this->one($this->db, 'SELECT * FROM users WHERE email = ? AND active = TRUE', [strtolower(trim((string)$b['email']))]);
        if (!$record || $record['role'] !== $b['role'] || !password_verify((string)$b['password'], $record['password_hash'])) {
            Http::fail('Credenciales incorrectas o rol no correspondiente.', 401);
        }
        Auth::login($record);
        Http::json(['user' => Auth::user(), 'csrf' => Auth::token()]);
    
    }
    public function me(array $params = []): void {
        $this->user = Auth::user();
        $profileId = null;
        if ($this->user && $this->user['role'] === 'paciente') { $profileId = $this->patientId($this->db, $this->user['id']); }
        if ($this->user && $this->user['role'] === 'medico') { $profileId = $this->doctorId($this->db, $this->user['id']); }
        Http::json(['user' => $this->user, 'csrf' => Auth::token(), 'profile_id' => $profileId]);
    
    }
    public function register(array $params = []): void {
        if (Auth::user()) { Http::fail('Cierra la sesión antes de crear otra cuenta.', 403); }
        $b = \App\Http\Requests\RegistrarPacienteRequest::validar(Http::body());
        $document = $b['document'];
        $this->db->beginTransaction();
        $userId = $this->createUser($b, 'paciente');
        $stmt = $this->db->prepare('INSERT INTO patients(user_id,document,birth_date,address) VALUES(?,?,?,?)');
        $stmt->execute([$userId, $document, ($b['birth_date'] ?? '') ?: null, trim((string)($b['address'] ?? ''))]);
        $this->db->commit();
        Http::json(['ok'=>true,'message'=>'Cuenta creada. Inicia sesión como Paciente.'], 201);
    }

    public function logout(array $params = []): void {
        Auth::logout(); Http::json(['ok' => true]);
    
    }
}
