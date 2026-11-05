<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
final class PoliticaTratamientos {
    public function validarDatos(string $titulo,string $indicaciones,string $desde,?string $hasta): void {
        if(strlen(trim($titulo))<3 || strlen(trim($titulo))>160) throw new DomainException('Nombre de plan inválido (3-160 caracteres).');
        if(strlen(trim($indicaciones))<5 || strlen(trim($indicaciones))>2500) throw new DomainException('Indicaciones inválidas (5-2500 caracteres).');
        foreach([$desde,$hasta] as $fecha) {
            if($fecha===null) continue;
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
            if(!$d || $d->format('Y-m-d')!==$fecha) throw new DomainException('Fecha inválida. Usa AAAA-MM-DD.');
        }
        if($hasta!==null && $hasta<$desde) throw new DomainException('Fin anterior al inicio.');
    }
    public function validarEstado(string $actual,string $nuevo): void {
        $reglas=['activo'=>['suspendido','finalizado'],'suspendido'=>['activo','finalizado'],'finalizado'=>[]];
        if(!in_array($nuevo,$reglas[$actual]??[],true)) throw new DomainException('Transición de tratamiento no permitida.');
    }
}
