<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Services\{ServicioUsuarios,LoginThrottle};
function ok(bool $value,string $message): void {if(!$value){fwrite(STDERR,"ERROR: $message\n");exit(1);}echo "OK: $message\n";}
final class FakeUserStatement extends PDOStatement {
    public function __construct(private string $sql,private FakeUserDatabase $db){}
    public function bindValue(int|string $param, mixed $value, int $type=PDO::PARAM_STR): bool {
        if (str_contains($this->sql,'UPDATE users')) $this->db->boundParams[(int)$param] = ['value'=>$value,'type'=>$type];
        return true;
    }
    public function execute(?array $params=null): bool {if(str_contains($this->sql,'UPDATE users'))$this->db->updated++;return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {return $this->db->user??false;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {return array_fill(0,$this->db->adminCount,['id'=>1]);}
}
final class FakeUserDatabase extends PDO {
    public int $commits=0,$rollbacks=0,$updated=0;
    public array $boundParams=[];
    private bool $transaction=false;
    public function __construct(public ?array $user,public int $adminCount=2){}
    public function beginTransaction():bool{$this->transaction=true;return true;}
    public function commit():bool{$this->transaction=false;$this->commits++;return true;}
    public function rollBack():bool{$this->transaction=false;$this->rollbacks++;return true;}
    public function inTransaction():bool{return $this->transaction;}
    public function prepare(string $sql,array $options=[]):PDOStatement|false{return new FakeUserStatement($sql,$this);}
    public function query(string $sql,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{return new FakeUserStatement($sql,$this);}
}
$missing=new FakeUserDatabase(null);
ok((new ServicioUsuarios($missing))->cambiarActivo(20,false,1)['status']==='not_found','Usuario inexistente no se borra');
$self=new FakeUserDatabase(['id'=>1,'name'=>'Admin','role'=>'administrador','active'=>true]);
ok((new ServicioUsuarios($self))->cambiarActivo(1,false,1)['status']==='self','Impide que administrador elimine su cuenta');
$last=new FakeUserDatabase(['id'=>2,'name'=>'Admin','role'=>'administrador','active'=>true],1);
ok((new ServicioUsuarios($last))->cambiarActivo(2,false,1)['status']==='last_admin','Conserva al último administrador');
$other=new FakeUserDatabase(['id'=>2,'name'=>'Paciente','role'=>'paciente','active'=>true]);
ok((new ServicioUsuarios($other))->cambiarActivo(2,false,1)['status']==='deactivated' && $other->updated===1 && $other->commits===1,'Baja lógica e invalidación de sesión');
ok(isset($other->boundParams[1]) && $other->boundParams[1]['value'] === false && $other->boundParams[1]['type'] === PDO::PARAM_BOOL,'Baja envía FALSE con PDO::PARAM_BOOL');
ok(isset($other->boundParams[2]) && $other->boundParams[2]['value'] === 2 && $other->boundParams[2]['type'] === PDO::PARAM_INT,'ID usa PDO::PARAM_INT');
$inactive=new FakeUserDatabase(['id'=>2,'name'=>'Paciente','role'=>'paciente','active'=>false]);
ok((new ServicioUsuarios($inactive))->cambiarActivo(2,true,1)['status']==='restored' && $inactive->updated===1,'Permite reactivar sin perder historial');
ok(isset($inactive->boundParams[1]) && $inactive->boundParams[1]['value'] === true && $inactive->boundParams[1]['type'] === PDO::PARAM_BOOL,'Reactivación envía TRUE con PDO::PARAM_BOOL');
ok(LoginThrottle::maxAttempts()>=2 && LoginThrottle::lockMinutes()>=1,'Límite de intentos y bloqueo son configurables');
ok(LoginThrottle::fingerprint(' TEST@MAIL.COM ','paciente','127.0.0.1')===LoginThrottle::fingerprint('test@mail.com','paciente','127.0.0.1'),'Huella de inicio de sesión normaliza correo');
$routes=require dirname(__DIR__).'/routes/api.php';
ok((bool)array_filter($routes,fn($r)=>str_contains($r[1],'forgot-password')),'Ruta de recuperación incluida');
ok((bool)array_filter($routes,fn($r)=>str_contains($r[1],'/api/audit')),'Ruta de bitácora incluida');
echo "PRUEBAS DE SEGURIDAD UNITARIAS CORRECTAS.\n";
