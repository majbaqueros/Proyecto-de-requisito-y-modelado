<?php
declare(strict_types=1);
namespace App\Contracts;
interface CorreoInterface { public function enviar(string $destino,string $asunto,string $mensaje): void; }
