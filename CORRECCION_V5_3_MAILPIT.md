# SaludCita V5.3: correo de recuperación y Mailpit

**Qué se corrigió:** El instalador escribía `storage/app.key` con `0700` (directorio) y `0600` (archivo), propietario `root`. Las peticiones HTTP se ejecutan como `www-data`, por lo que podían fallar antes de intentar enviar el correo aunque Mailpit funcionase. El instalador ahora corrige el grupo/permisos (`storage`: `0750`; `app.key`: `0640`) y conserva la clave existente. Además, cuando Mailpit falla, se invalida el código no enviado y se permite reintentar la solicitud sin esperar el minuto de enfriamiento.

## Actualización segura en Windows/VS Code

1. **Respalda tu carpeta** `clinica-citas-solid`. Si guardaste datos reales de prueba, respalda también PostgreSQL. Usa solo datos ficticios.
2. Descomprime el ZIP y **copia el contenido de `clinica-citas-solid` sobre el proyecto existente**, reemplazando archivos; no sustituyas el directorio completo si tiene tu `.env`/`storage/app.key` actuales.
3. Desde la terminal situada dentro de `clinica-citas-solid`, ejecuta **uno por uno**:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
docker compose exec -u www-data app php scripts/diagnosticar_correo.php
```

4. Revisa el mensaje de prueba en **http://localhost:8025**. Después solicita un código desde **http://localhost:8080/recuperar-clave**, usando `paciente@clinica.local` si aún existe y está activo.

**Importante:** el tercer comando usa `-u www-data` para comprobar los permisos reales del usuario web; probar solo como `root` podía producir un falso positivo. La creación o comprobación de tablas no elimina el volumen de PostgreSQL. **Nunca uses `docker compose down -v`.**

Si el envío falla:

```powershell
docker compose ps
docker compose logs --tail=60 app mailpit
```

En `docker-compose.yml`, `SMTP_HOST` debe resolver al servicio `mailpit` y `SMTP_PORT` ser `1025` (puerto interno). Mailpit se abre desde Windows en `http://localhost:8025`. Si tienes `.env`, sus variables SMTP pueden reemplazar los valores por defecto. No compartas `APP_KEY`, códigos ni contraseñas.

## Archivos modificados en V5.3

- `scripts/install.php`: repara permisos existentes sin rotar la clave.
- `app/Services/RecuperacionClave.php`: errores de permisos explícitos y no deja códigos válidos si el SMTP falla.
- `app/Http/Controllers/AuthController.php`: mensaje útil al fallar recuperación en desarrollo, sin exponer si una cuenta existe en producción.
- `scripts/diagnosticar_correo.php`: prueba el transporte SMTP e informa el usuario de ejecución y permisos de la clave sin exponer su contenido.
- Código V5.1 incluido: actualización booleana de usuarios con `PDO::PARAM_BOOL`.

> Este sistema es una demostración universitaria. Mailpit guarda los correos solo en tu entorno local, no los envía a Gmail.
