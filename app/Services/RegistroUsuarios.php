<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Http;
use PDO;

/** Creación de usuarios: una responsabilidad independiente de cada controlador. */
final class RegistroUsuarios {
    public function __construct(private PDO $db) {}
    public function crear(array $b, string $role): int {
        Http::requireFields($b, ['name','email','password']);
        $email = strtolower(trim((string)$b['email']));
        $name = trim((string)$b['name']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { Http::fail('Correo electrónico inválido.'); }
        if (strlen($name) < 3 || strlen($name) > 120) { Http::fail('El nombre debe tener entre 3 y 120 caracteres.'); }
        if (strlen((string)$b['password']) < 8) { Http::fail('La contraseña debe tener al menos 8 caracteres.'); }
        if (!in_array($role, ['paciente','recepcionista','medico','administrador'], true)) { Http::fail('Rol inválido.'); }
        $stmt = $this->db->prepare('INSERT INTO users(name,email,password_hash,role,phone) VALUES(?,?,?,?,?) RETURNING id');
        $stmt->execute([$name, $email, password_hash((string)$b['password'], PASSWORD_DEFAULT), $role, trim((string)($b['phone'] ?? ''))]);
        return (int)$stmt->fetchColumn();
    }
}
