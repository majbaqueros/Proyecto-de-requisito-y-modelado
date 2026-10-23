<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Conteo transaccional por dirección IP y cuenta; intervalo configurable por entorno. */
final class LoginThrottle {
    public function __construct(private PDO $db) {}
    public static function maxAttempts(): int { return max(2,min(20,(int)(getenv('AUTH_MAX_ATTEMPTS') ?: 5))); }
    public static function lockMinutes(): int { return max(1,min(120,(int)(getenv('AUTH_LOCK_MINUTES') ?: 5))); }
    public static function fingerprint(string $email,string $role,string $ip): string {
        return hash('sha256',strtolower(trim($email)).'|'.$role.'|'.$ip);
    }
    /** Debe invocarse dentro de transacción, con bloqueo para evitar carreras de intentos. */
    public function lock(string $fingerprint): array {
        $this->db->prepare('INSERT INTO login_attempts(fingerprint) VALUES(?) ON CONFLICT DO NOTHING')->execute([$fingerprint]);
        $this->db->prepare('UPDATE login_attempts SET failed_count=0,locked_until=NULL,updated_at=NOW() WHERE fingerprint=? AND locked_until IS NOT NULL AND locked_until<=NOW()')->execute([$fingerprint]);
        $stmt=$this->db->prepare('SELECT failed_count,EXTRACT(EPOCH FROM (locked_until-NOW()))::integer AS remaining FROM login_attempts WHERE fingerprint=? FOR UPDATE');
        $stmt->execute([$fingerprint]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['failed_count'=>0,'remaining'=>0];
    }
    public function fail(string $fingerprint): int {
        $max=self::maxAttempts(); $minutes=self::lockMinutes();
        $stmt=$this->db->prepare("UPDATE login_attempts SET failed_count=CASE WHEN updated_at < NOW()-INTERVAL '30 minutes' THEN 1 ELSE failed_count+1 END, updated_at=NOW() WHERE fingerprint=? RETURNING failed_count");
        $stmt->execute([$fingerprint]);
        $count=(int)$stmt->fetchColumn();
        if($count>=$max){
            $this->db->prepare("UPDATE login_attempts SET locked_until=NOW()+(? * INTERVAL '1 minute') WHERE fingerprint=?")->execute([$minutes,$fingerprint]);
            return $minutes*60;
        }
        return 0;
    }
    public function clear(string $fingerprint): void {
        $this->db->prepare('DELETE FROM login_attempts WHERE fingerprint=?')->execute([$fingerprint]);
    }
}
