<?php
declare(strict_types=1);
namespace App\Http\Requests;
use App\Core\Http;

/** Normalización HTTP (las reglas horarias se validan en ValidadorDisponibilidad). */
final class AgendarCitaRequest {
    public static function validar(array $input): array {
        Http::requireFields($input, ['doctor_id','scheduled_at','reason']);
        if (!ctype_digit((string)$input['doctor_id']) || (int)$input['doctor_id'] <= 0) {
            Http::fail('Selecciona un médico válido.');
        }
        $motivo = trim((string)$input['reason']);
        if (strlen($motivo) < 5 || strlen($motivo) > 1000) {
            Http::fail('El motivo debe tener entre 5 y 1000 caracteres.');
        }
        $input['reason'] = $motivo;
        $fecha = str_replace('T', ' ', (string)$input['scheduled_at']);
        if (strlen($fecha) === 16) { $fecha .= ':00'; }
        $input['scheduled_at'] = $fecha;
        return $input;
    }
}
