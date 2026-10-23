<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Core/Autoload.php';

use App\Models\Cita;
use App\Infrastructure\{FacturadorNormal,FacturadorSeguro,RepositorioCitasMemoria};
use App\Contracts\{NotificadorInterface,FacturaRepositoryInterface};
use App\Services\{GestorCitas,ValidadorDisponibilidad,ServicioCobros};

function check(bool $condition, string $name): void {
    if (!$condition) { fwrite(STDERR, "FALLÓ: {$name}\n"); exit(1); }
    echo "OK: {$name}\n";
}
class SpyNotifier implements NotificadorInterface {
    public array $sent=[];
    public function enviar(int $usuarioId, string $titulo, string $mensaje): void {
        $this->sent[]=[$usuarioId,$titulo,$mensaje];
    }
}
class SpyInvoices implements FacturaRepositoryInterface {
    public array $saved=[];
    public function guardar(int $citaId, string $tipo, array $calculo): array {
        $this->saved[]=[$citaId,$tipo,$calculo];
        return ['id'=>count($this->saved),'total'=>$calculo['total']];
    }
}
$base=new FacturadorNormal(); $secure=new FacturadorSeguro();
check($base->tipo()==='normal','Facturador normal usa contrato');
check($base->calcular(120)['total']===120.0,'Factura normal sin descuento');
check($secure->tipo()==='seguro','Facturador de seguro es implementación independiente');
check($secure->calcular(120)['descuento']===24.0,'Seguro descuenta 20% (demo)');
check($secure->calcular(120)['total']===96.0,'Seguro calcula total correctamente');

$spy=new SpyInvoices();
$billing=new ServicioCobros($secure,$spy);
$result=$billing->generar(5,120);
check($result['total']===96.0 && $spy->saved[0][1]==='seguro','ServicioCobros admite cualquier facturador');
$repo=new RepositorioCitasMemoria();
$notice=new SpyNotifier();
$gestor=new GestorCitas($repo,$notice,new ValidadorDisponibilidad($repo));
$future=(new DateTimeImmutable('+3 days'))->setTime(10,0)->format('Y-m-d H:i:s');
$cita=new Cita(1,1,$future,'Control general',1);
$created=$gestor->agendar($cita,7);
check($created['status']==='pendiente','GestorCitas registra nueva cita');
check(count($notice->sent)===1 && $notice->sent[0][0]===7,'Notificador independiente recibe evento');
check(!$repo->disponible(new Cita(2,1,$future,'Otra persona',2)),'Médico no puede reservar el mismo horario');
check(!$repo->disponible(new Cita(1,2,$future,'Otro médico',1)),'Paciente no puede reservar el mismo horario');
try{$gestor->agendar(new Cita(2,1,$future,'Doble reserva',2),8);check(false,'Rechaza doble reserva');}
catch(DomainException $e){check(true,'Rechaza doble reserva');}
try{$gestor->agendar(new Cita(1,1,'2001-01-01 10:00:00','Pasado',1),7);check(false,'Rechaza pasado');}
catch(DomainException $e){check(true,'Rechaza citas pasadas');}
try{$gestor->agendar(new Cita(1,1,substr($future,0,14).'15:00','Intervalo',1),7);check(false,'Valida intervalos');}
catch(DomainException $e){check(true,'Valida intervalos');}
$catalogo=new \App\Services\CatalogoFacturadores(new FacturadorNormal(),new FacturadorSeguro());
check($catalogo->seleccionar('normal')->calcular(70)['total']===70.0,'Catalogo registra estrategias sin condicionales de negocio');
try {$catalogo->seleccionar('desconocido');check(false,'Catalogo rechaza estrategia desconocida');}
catch (DomainException $e) {check(true,'Catalogo rechaza estrategia desconocida');}
$politica=new \App\Services\PoliticaEstadosCita();
$politica->validar('pendiente','confirmada','recepcionista',$future);
check(true,'Recepcionista puede confirmar cita pendiente');
try {$politica->validar('cancelada','confirmada','recepcionista',$future);check(false,'No reactivar cancelada');}
catch (DomainException $e) {check(true,'Política rechaza reactivar cancelada');}
try {$politica->validar('pendiente','confirmada','paciente',$future);check(false,'Paciente no confirma');}
catch (DomainException $e) {check(true,'Paciente no confirma cita');}
$routes=require dirname(__DIR__).'/routes/api.php';
check(count($routes)===31,'Las 31 rutas están separadas del controlador frontal');
foreach ($routes as $route) {
    check(class_exists($route[2]) && method_exists($route[2], $route[3]),
        'Ruta ' . $route[0] . ' ' . $route[1] . ' apunta a controlador y método reales');
}
check(count(array_filter($routes,fn($r)=>$r[4]))===6,'Solo rutas públicas de autenticación y recuperación están sin login');
echo "\nTODAS LAS PRUEBAS UNITARIAS PASARON.\n";
