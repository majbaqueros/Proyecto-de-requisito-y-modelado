<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Baja lógica: la trazabilidad clínica y financiera nunca se destruye. */
final class ServicioUsuarios {
    public function __construct(private PDO $db) {}
    public function cambiarActivo(int $targetId, bool $active, int $actorId): array {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("SELECT set_config('saludcita.actor_id',?,true)")->execute([(string)$actorId]);
            $st=$this->db->prepare('SELECT id,name,role,active FROM users WHERE id=? FOR UPDATE');$st->execute([$targetId]);
            $user=$st->fetch(PDO::FETCH_ASSOC);
            if(!$user){$this->db->rollBack();return ['status'=>'not_found'];}
            if(!$active && $targetId===$actorId){$this->db->rollBack();return ['status'=>'self'];}
            if(!$active && $user['role']==='administrador' && in_array($user['active'],[true,1,'1','t'],true)){
                $st=$this->db->query("SELECT id FROM users WHERE role='administrador' AND active=TRUE FOR UPDATE");
                if(count($st->fetchAll(PDO::FETCH_ASSOC))<=1){$this->db->rollBack();return ['status'=>'last_admin'];}
            }
            if(in_array($user['active'],[true,1,'1','t'],true)!==$active){
                // PostgreSQL necesita un booleano tipado; execute([$active, ...]) puede convertir false en ''.
                $stmt = $this->db->prepare(
                    'UPDATE users SET active = ?, session_version = session_version + 1 WHERE id = ?'
                );
                $stmt->bindValue(1, $active, PDO::PARAM_BOOL);
                $stmt->bindValue(2, $targetId, PDO::PARAM_INT);
                $stmt->execute();
            }
            $this->db->commit();return ['status'=>$active?'restored':'deactivated','name'=>$user['name']];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
