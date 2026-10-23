<?php
declare(strict_types=1);
namespace App\Core;

final class Http {
    public static function json(mixed $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function body(): array {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input ?: '{}', true);
        if (!is_array($decoded)) { self::json(['error' => 'JSON inválido'], 400); }
        return $decoded;
    }
    public static function requireFields(array $data, array $names): void {
        foreach ($names as $name) {
            if (!isset($data[$name]) || (is_string($data[$name]) && trim($data[$name]) === '')) {
                self::json(['error' => "Campo requerido: {$name}"], 422);
            }
        }
    }
    public static function fail(string $message, int $status = 422): never {
        self::json(['error' => $message], $status);
    }
}
