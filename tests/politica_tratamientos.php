<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Services\PoliticaTratamientos;
$p=new PoliticaTratamientos();
$p->validarDatos('Control','Indicaciones de prueba','2026-11-05',null);
$p->validarDatos('Control','Indicaciones de prueba','2026-11-05','2026-11-20');
$p->validarEstado('activo','suspendido');
$p->validarEstado('suspendido','activo');
$p->validarEstado('activo','finalizado');
$rechazos=0;
foreach([
 fn()=> $p->validarDatos('xx','Indicaciones','2026-11-05',null),
 fn()=> $p->validarDatos('Control','Indicaciones','2026-02-31',null),
 fn()=> $p->validarDatos('Control','Indicaciones','2026-11-20','2026-11-05'),
 fn()=> $p->validarEstado('finalizado','activo')
] as $test){try{$test();}catch(\DomainException $e){$rechazos++;}}
if($rechazos!==4){fwrite(STDERR,"Error en PoliticaTratamientos\n");exit(1);}
echo "OK: PoliticaTratamientos, 9 casos\n";
