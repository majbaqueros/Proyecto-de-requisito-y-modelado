<?php
declare(strict_types=1);
namespace App\Http\Requests;

use App\Core\Http;

/** Valida entradas de registro de pacientes sin crear usuarios ni consultar BD. */
final class RegistrarPacienteRequest {
    public static function validar(array $input): array {
        Http::requireFields($input, ['name','email','password','document']);
        $documento = trim((string)$input['document']);
        if (strlen($documento) < 4 || strlen($documento) > 30) {
            Http::fail('El documento debe tener entre 4 y 30 caracteres.');
        }
        $input['document'] = $documento;
        $birth = trim((string)($input['birth_date'] ?? ''));
        if ($birth !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
            if (!$date || $date->format('Y-m-d') !== $birth || $date > new \DateTimeImmutable('today')) {
                Http::fail('Fecha de nacimiento inválida.');
            }
        }
        return $input;
    }
}
