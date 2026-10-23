<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\CorreoInterface;
use PDO;
use RuntimeException;

/** Código de un solo uso con hash HMAC, vence a los 10 minutos. */
final class RecuperacionClave {
    private string $key;
    public function __construct(private PDO $db,private CorreoInterface $correo){
        $this->key=(string)(getenv('APP_KEY')?:'');
        if(strlen($this->key)<32){
            $file=trim((string)(getenv('APP_KEY_FILE') ?: ''));
            if($file==='') $file=dirname(__DIR__,2).'/storage/app.key';
            if(is_file($file) && !is_readable($file)){
                throw new RuntimeException('La clave existe, pero Apache no puede leerla. Ejecuta scripts/install.php para reparar permisos del volumen privado.');
            }
            if(is_file($file)){
                $content=@file_get_contents($file);
                if($content===false)throw new RuntimeException('No se pudo leer la clave. Revisa los permisos para www-data.');
                $this->key=trim($content);
            }
        }
        if(strlen($this->key)<32)throw new RuntimeException('Falta APP_KEY: ejecuta php scripts/install.php para crear la clave de seguridad.');
    }
    private function hashCode(int $uid,string $code): string {return hash_hmac('sha256',$uid.':'.$code,$this->key);}
    /** Protege solicitudes masivas con límite temporal por cuenta/IP. */
    private function throttle(string $email,string $ip): bool {
        $hash=hash('sha256',$email.'|'.$ip);
        $this->db->prepare('INSERT INTO password_reset_requests(fingerprint) VALUES(?) ON CONFLICT DO NOTHING')->execute([$hash]);
        $s=$this->db->prepare('SELECT attempts,EXTRACT(EPOCH FROM(NOW()-window_started_at)) AS window_age,EXTRACT(EPOCH FROM(NOW()-last_requested_at)) AS since_last FROM password_reset_requests WHERE fingerprint=? FOR UPDATE');
        $s->execute([$hash]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if($r && (float)$r['window_age']<3600 && ((int)$r['attempts']>=5 || ((int)$r['attempts']>0 && (float)$r['since_last']<60)))return false;
        $this->db->prepare("UPDATE password_reset_requests SET attempts=CASE WHEN window_started_at<NOW()-INTERVAL '1 hour' THEN 1 ELSE attempts+1 END,window_started_at=CASE WHEN window_started_at<NOW()-INTERVAL '1 hour' THEN NOW() ELSE window_started_at END,last_requested_at=NOW() WHERE fingerprint=?")->execute([$hash]);
        return true;
    }
    public function solicitar(string $email,string $ip): void {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \DomainException('Correo no válido.');
        $this->db->beginTransaction();
        try{
            $allowed=$this->throttle($email,$ip);
            $stmt=$this->db->prepare('SELECT id FROM users WHERE email=? AND active=true');$stmt->execute([$email]);$uid=(int)$stmt->fetchColumn();
            if(!$allowed || !$uid){$this->db->commit();return;}
            $code=(string)random_int(100000,999999);
            $hash=$this->hashCode($uid,$code);
            $stmt=$this->db->prepare("INSERT INTO password_resets(user_id,code_hash,expires_at,attempts,requested_at) VALUES(?,?,NOW()+INTERVAL '10 minutes',0,NOW()) ON CONFLICT(user_id) DO UPDATE SET code_hash=EXCLUDED.code_hash,expires_at=EXCLUDED.expires_at,attempts=0,requested_at=NOW()");
            $stmt->execute([$uid,$hash]);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        // Enviar fuera de la transacción; nunca insertar el código en bitácora.
        try {
            $this->correo->enviar($email,'Codigo de recuperacion SaludCita',"Solicitaste recuperar tu contraseña de SaludCita.\n\nTu código de recuperación es: {$code}\n\nEste código vence en 10 minutos y solo puede usarse una vez.\nSi no solicitaste el cambio, ignora este mensaje.");
        } catch (\Throwable $e) {
            // Si el SMTP falla, no dejar un código utilizable sin haber sido enviado.
            // Permite solicitar otro inmediatamente después de corregir Mailpit.
            try {
                $stmt=$this->db->prepare('DELETE FROM password_resets WHERE user_id=? AND code_hash=?');
                $stmt->execute([$uid,$hash]);
                $fingerprint=hash('sha256',$email.'|'.$ip);
                $stmt=$this->db->prepare("UPDATE password_reset_requests SET attempts=GREATEST(attempts-1,0),last_requested_at=NOW()-INTERVAL '2 minutes' WHERE fingerprint=?");
                $stmt->execute([$fingerprint]);
            } catch (\Throwable $cleanupError) {
                error_log('No se pudo limpiar una solicitud de recuperación fallida: '.$cleanupError->getMessage());
            }
            throw $e;
        }
    }
    public function confirmar(string $email,string $code,string $password): bool {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\d{6}$/D',$code) || strlen($password)<8 || strlen($password)>200){
            throw new \DomainException('Introduce un correo válido, código de seis dígitos y contraseña de 8 a 200 caracteres.');
        }
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT r.user_id,r.code_hash,r.attempts,(r.expires_at>NOW()) AS valid FROM password_resets r JOIN users u ON u.id=r.user_id WHERE u.email=? AND u.active=TRUE FOR UPDATE OF r');
            $s->execute([$email]);$record=$s->fetch(PDO::FETCH_ASSOC);
            if(!$record || !in_array($record['valid'],[true,1,'1','t'],true) || (int)$record['attempts']>=5){$this->db->commit();return false;}
            $uid=(int)$record['user_id'];
            if(!hash_equals($record['code_hash'],$this->hashCode($uid,$code))){
                $this->db->prepare('UPDATE password_resets SET attempts=attempts+1 WHERE user_id=?')->execute([$uid]);
                $this->db->commit();return false;
            }
            $this->db->prepare('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
            $this->db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$uid]);
            $this->db->commit();return true;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
