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
        $b = Http::body(); Http::requireFields($b,['email','password','role']);
        $email=strtolower(trim((string)$b['email'])); $role=(string)$b['role'];
        if(!in_array($role, Auth::ROLES,true)) Http::fail('Rol inválido.',422);
        $throttle=new \App\Services\LoginThrottle($this->db);
        $fingerprint=\App\Services\LoginThrottle::fingerprint($email,$role,(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $this->db->beginTransaction();
        $row=$throttle->lock($fingerprint);
        if((int)($row['remaining']??0)>0){
            $minutes=(int)ceil((int)$row['remaining']/60);
            $this->db->commit();
            header('Retry-After: '.max(1,(int)$row['remaining']));
            Http::fail("Demasiados intentos fallidos. Vuelve a intentarlo en {$minutes} minuto(s).",429);
        }
        $record=$this->one($this->db,'SELECT * FROM users WHERE email=? AND active=TRUE',[$email]);
        if(!$record || $record['role']!==$role || !password_verify((string)$b['password'],$record['password_hash'])){
            $locked=$throttle->fail($fingerprint);
            $this->db->commit();
            if($locked){header('Retry-After: '.$locked);Http::fail('Demasiados intentos fallidos. Vuelve a intentarlo en '.\App\Services\LoginThrottle::lockMinutes().' minuto(s).',429);}
            Http::fail('Credenciales incorrectas o rol no correspondiente.',401);
        }
        $throttle->clear($fingerprint);
        $this->db->commit();
        Auth::login($record);
        Http::json(['user'=>Auth::user(),'csrf'=>Auth::token()]);
    }
    public function solicitarRecuperacion(array $params=[]): void {
        $b=Http::body();Http::requireFields($b,['email']);
        try{
            (new \App\Services\RecuperacionClave($this->db,new \App\Infrastructure\CorreoSmtp()))->solicitar((string)$b['email'],(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        }catch(\DomainException $e){Http::fail($e->getMessage(),422);}
        catch(\Throwable $e){
            error_log('No se pudo generar o enviar el correo de recuperación: '.$e->getMessage());
            // En desarrollo informamos un problema de configuración en vez de
            // mostrar falsamente el siguiente paso. En producción se conserva
            // la respuesta genérica, para no revelar cuentas existentes.
            if ((getenv('APP_ENV') ?: '') === 'development') {
                Http::fail('No se pudo enviar el código. Revisa Mailpit, SMTP_HOST, APP_KEY y los registros del servidor.', 503);
            }
        }
        Http::json(['ok'=>true,'message'=>'Si el correo está registrado y activo, recibirás un código de recuperación. Revisa tu bandeja de entrada.']);
    }
    public function restablecerClave(array $params=[]): void {
        $b=Http::body();Http::requireFields($b,['email','code','password']);
        try {
            $ok=(new \App\Services\RecuperacionClave($this->db,new \App\Infrastructure\CorreoSmtp()))->confirmar((string)$b['email'],(string)$b['code'],(string)$b['password']);
        } catch (\DomainException $e) {
            Http::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            error_log('No se pudo validar el código de recuperación: '.$e->getMessage());
            if ((getenv('APP_ENV') ?: '') === 'development') {
                Http::fail('Error de configuración de recuperación. Revisa la clave de seguridad y los registros del servidor.', 503);
            }
            Http::fail('No fue posible procesar la recuperación en este momento.', 503);
        }
        if(!$ok)Http::fail('Código incorrecto, vencido o agotó sus intentos. Solicita uno nuevo.',422);
        Http::json(['ok'=>true,'message'=>'Contraseña actualizada. Inicia sesión nuevamente.']);
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
