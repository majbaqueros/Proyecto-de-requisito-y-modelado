# Actualización V5: Usuarios, bitácora y recuperación de contraseñas

## Resumen técnico

El módulo de administración de usuarios permite **desactivar** una cuenta sin eliminar las referencias a citas, pagos e historias clínicas. También permite reactivarla. No es una eliminación física porque el registro médico y financiero debe mantenerse trazable. Queda impedida la desactivación de la cuenta propia y del último administrador activo; al desactivar o recuperar una contraseña se incrementa `session_version`, invalidando sesiones anteriores en la siguiente petición.

### Auditoría de PostgreSQL (PUDS, paquete P1)

`database/migrations/005_security.sql` agrega la tabla `audit_logs` y triggers en nueve tablas (`users`, `patients`, `doctors`, `specialties`, `schedules`, `appointments`, `clinical_records`, `invoices`, `notifications`). Guarda fecha, operación, tabla, ID, origen, ID de usuario cuando la acción viene del sistema, datos antes y después de la operación. Para cambios hechos desde `psql` sin un usuario web identificado, muestra `sql/terminal` y `db_user`.

El trigger **omite** `password_hash`, `session_version` y el contenido de `symptoms`, `diagnosis`, `treatment` y `notes`. Registra que cambió la contraseña sin almacenar la contraseña ni su hash. Las tablas de recuperación tampoco se auditan: nunca aparecen los códigos. La bitácora es solo de lectura para Administrador en `/administrador/bitacora`.

#### Ver en la terminal

```powershell
docker compose exec app php scripts/audit.php 30
docker compose exec app php scripts/audit.php 20 users
docker compose exec db psql -U clinica -d clinica -c "SELECT id, occurred_at, source, table_name, record_id, operation FROM audit_logs ORDER BY id DESC LIMIT 20;"
```

Puedes usar `psql` para revisar o modificar datos en un entorno **de pruebas**. Los cambios directos de SQL también activan el trigger, por ejemplo modificando el teléfono de una cuenta ficticia. No ejecutes modificaciones directas con datos reales sin autorización.

### Recuperación de contraseñas (P1)

Flujo: el usuario pulsa «¿Olvidaste tu contraseña?», escribe su correo, recibe un código de 6 cifras generado con `random_int`, y lo introduce con una contraseña nueva. El código se guarda como HMAC-SHA256 con una clave secreta (`APP_KEY`/`storage/app.key`), nunca en texto plano; vence en 10 minutos, puede usarse una sola vez y se invalida después de cinco códigos incorrectos. La respuesta al solicitar un código es genérica incluso cuando el correo no está registrado.

El correo se envía vía la interfaz `CorreoInterface` y el adaptador `CorreoSmtp`. En Docker, los correos se depositan por defecto en el **buzón Mailpit local**, disponible en `http://localhost:8025`. **Mailpit no envía mensajes a correos reales.** Para producción o envío a Gmail/Outlook configura un proveedor SMTP TLS/STARTTLS en `.env` y recrea el contenedor web; usa una contraseña de aplicación o credencial SMTP, no la contraseña normal de tu cuenta.

No se revela un código de recuperación por la interfaz, API o los logs. Si falla la entrega SMTP se anota una incidencia técnica sin incluir el código.

### Bloqueo de inicio de sesión

`LoginThrottle` usa PostgreSQL para contar los intentos fallidos por correo normalizado, rol e IP de origen. Al alcanzar el máximo responde HTTP 429 y `Retry-After`, informando el tiempo de bloqueo. Se puede configurar con `AUTH_MAX_ATTEMPTS` y `AUTH_LOCK_MINUTES` desde `.env` (por defecto **5 intentos y 5 minutos**; valores permitidos: 2–20 intentos y 1–120 minutos). El conteo se reinicia al vencer el bloqueo y al iniciar sesión correctamente; la retención por intentos aislados es de 30 minutos.

### Casos de uso añadidos

| CU | Actor | Flujo principal | Alternativas |
|---|---|---|---|
| CU17: Desactivar y reactivar usuario | Administrador | Seleccionar cuenta, confirmar, cambiar `active`, invalidar sesiones, registrar auditoría | No puede desactivar su cuenta ni último administrador |
| CU18: Consultar bitácora | Administrador | Ver cambios con fecha, entidad, origen, valores previos y nuevos | Sin eventos o sin acceso |
| CU19: Recuperar contraseña | Cualquier actor | Solicitar código por correo, ingresar código y clave nueva, guardar y reiniciar sesiones | Código incorrecto, vencido, solicitud excesiva, falla SMTP |
| CU20: Bloqueo temporal por intentos | Cualquier actor | Contraseña incorrecta, contar y bloquear, mostrar espera de 5 min | Inicio exitoso limpia contador |

Estos casos complementan los casos de uso ya documentados en el proyecto, no los reemplazan. Los detalles de autenticación forman parte del módulo de Autenticación y Seguridad.

### Archivos principales

- `app/Http/Controllers/UsuarioController.php`: API de desactivar/reactivar.
- `app/Services/ServicioUsuarios.php`: reglas de cuentas.
- `app/Http/Controllers/BitacoraController.php` y `scripts/audit.php`: consulta de auditoría.
- `database/migrations/005_security.sql`: migración y triggers.
- `app/Http/Controllers/AuthController.php`: inicio de sesión y recuperación.
- `app/Services/LoginThrottle.php`: bloqueo configurable.
- `app/Services/RecuperacionClave.php`: código, caducidad y uso único.
- `app/Contracts/CorreoInterface.php` y `app/Infrastructure/CorreoSmtp.php`: desacoplamiento SOLID del medio de envío.
- `public/assets/app.js` y `public/assets/styles.css`: pantallas y controles.

### Verificación

Se verificaron sintaxis PHP/JS y pruebas unitarias. Las operaciones de PostgreSQL, el envío real SMTP y las pantallas conectadas con Docker requieren validación en el equipo del grupo antes de entregar y desplegar públicamente. Usa solamente información ficticia en esta aplicación académica.
