<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';

use App\Services\ServicioHorarios;

/** Fakes de PDO: prueban decisiones del servicio sin necesitar PostgreSQL. */
final class HorarioStatementFake extends PDOStatement {
    public array $params=[];
    public function __construct(private string $query, private HorarioPdoFake $db) {}
    public function execute(?array $params=null): bool {
        $this->params=$params??[];
        if (str_contains($this->query,'DELETE FROM schedules')) { $this->db->deletes++; }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        return $this->db->schedule ?? false;
    }
    public function fetchColumn(int $column=0): mixed {return $this->db->pending;}
}
final class HorarioPdoFake extends PDO {
    public bool $transaction=false;
    public int $deletes=0;
    public int $commits=0;
    public int $rollbacks=0;
    public function __construct(public ?array $schedule,public int $pending=0) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false {return new HorarioStatementFake($query,$this);}
    public function beginTransaction(): bool {$this->transaction=true;return true;}
    public function commit(): bool {$this->transaction=false;$this->commits++;return true;}
    public function rollBack(): bool {$this->transaction=false;$this->rollbacks++;return true;}
    public function inTransaction(): bool {return $this->transaction;}
}
function verify(bool $ok,string $description): void {if(!$ok){throw new Exception('ERROR: '.$description);} echo 'OK: '.$description."\n";}
$missing=new HorarioPdoFake(null);
verify((new ServicioHorarios($missing))->eliminar(99)['status']==='not_found','Horario inexistente se rechaza');
verify($missing->deletes===0 && $missing->rollbacks===1,'No se elimina nada si horario no existe');
$record=['id'=>1,'doctor_id'=>2,'weekday'=>1,'start_time'=>'08:00:00','end_time'=>'12:00:00'];
$occupied=new HorarioPdoFake($record,2);
verify((new ServicioHorarios($occupied))->eliminar(1)===['status'=>'blocked','appointments'=>2],'Bloquea franja con citas futuras activas');
verify($occupied->deletes===0 && $occupied->rollbacks===1,'Citas protegidas: no ejecuta DELETE');
$available=new HorarioPdoFake($record,0);
verify((new ServicioHorarios($available))->eliminar(1)['status']==='deleted','Permite eliminar franja sin citas activas futuras');
verify($available->deletes===1 && $available->commits===1,'Eliminación confirmada dentro de transacción');
echo "PRUEBAS DE ELIMINACIÓN DE HORARIOS CORRECTAS.\n";
