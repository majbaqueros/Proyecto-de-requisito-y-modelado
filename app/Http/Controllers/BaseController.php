<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth, Http};
use App\Contracts\NotificadorInterface;
use PDO;
use Throwable;

/** Consultas compartidas y perfiles; las rutas nunca contienen SQL. */
abstract class BaseController {
    public function __construct(
        protected PDO $db,
        protected ?array $user,
        protected NotificadorInterface $notifier
    ) {}

    protected function one(PDO $db, string $sql, array $args = []): ?array {
        $stmt = $db->prepare($sql); $stmt->execute($args);
        $item = $stmt->fetch(); return $item === false ? null : $item;
    }

    protected function many(PDO $db, string $sql, array $args = []): array {
        $stmt = $db->prepare($sql); $stmt->execute($args); return $stmt->fetchAll();
    }

    protected function execSql(PDO $db, string $sql, array $args = []): int {
        $stmt = $db->prepare($sql); $stmt->execute($args); return $stmt->rowCount();
    }

    protected function patientId(PDO $db, int $userId): int {
        $row = $this->one($db, 'SELECT id FROM patients WHERE user_id = ?', [$userId]);
        if (!$row) { Http::fail('Perfil de paciente no encontrado.', 403); }
        return (int)$row['id'];
    }

    protected function doctorId(PDO $db, int $userId): int {
        $row = $this->one($db, 'SELECT id FROM doctors WHERE user_id = ?', [$userId]);
        if (!$row) { Http::fail('Perfil de médico no encontrado.', 403); }
        return (int)$row['id'];
    }

    protected function createUser(array $body, string $role): int {
        return (new \App\Services\RegistroUsuarios($this->db))->crear($body,$role);
    }

    protected function cita(PDO $db, int $id): array {
        $row = $this->one($db, 'SELECT a.*, p.user_id AS patient_user_id, d.user_id AS doctor_user_id FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id WHERE a.id = ?', [$id]);
        if (!$row) { Http::fail('La cita no existe.', 404); }
        return $row;
    }

    protected function canSeeCita(PDO $db, array $user, array $c): void {
        if ($user['role'] === 'paciente' && (int)$c['patient_user_id'] !== $user['id']) { Http::fail('Acceso denegado.', 403); }
        if ($user['role'] === 'medico' && (int)$c['doctor_user_id'] !== $user['id']) { Http::fail('Acceso denegado.', 403); }
    }

    protected function notifySafely(NotificadorInterface $notifier, int $userId, string $title, string $message): void {
        try { $notifier->enviar($userId, $title, $message); }
        catch (Throwable $e) { error_log('Falló aviso: ' . $e->getMessage()); }
    }
}
