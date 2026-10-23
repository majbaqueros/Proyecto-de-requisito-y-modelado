# SaludCita V5.4 — Clave de recuperación en volumen privado de Docker

## Error que soluciona

El diagnóstico ejecutado como `www-data` mostró `Clave de recuperación (APP_KEY o storage/app.key): FALTA` aunque `install.php` finalizaba sin error. Apache no podía leer la clave ubicada en el directorio `storage` compartido con Windows. La nueva versión **no depende de los permisos del bind mount de Windows** para guardar el secreto.

## Solución aplicada

- `docker-compose.yml` monta el volumen privado persistente `saludcita_secrets` en `/var/lib/saludcita` dentro del contenedor `app`.
- `APP_KEY_FILE=/var/lib/saludcita/app.key` indica dónde leer la clave, sin colocar el secreto en variables mostradas por Docker.
- `scripts/install.php` crea el secreto **solo si no existe**. Si había una clave válida en `storage/app.key`, la copia al volumen privado; **no rota la clave existente**. Ajusta grupo `www-data` y permisos 0750/0640 sobre el nuevo volumen Linux.
- `app/Services/RecuperacionClave.php` y `scripts/diagnosticar_correo.php` respetan `APP_KEY_FILE`.

## Actualización sin perder datos (Windows + VS Code)

1. **Haz respaldo de tu carpeta y datos importantes**. Abre el ZIP de parche y copia **solo sus archivos** a tu carpeta actual `clinica-citas-solid`, conservando `.env`, `storage/` y el volumen PostgreSQL. Si usas el ZIP completo, fusiona su contenido con tu proyecto: no elimines la carpeta existente.
2. Dentro de `clinica-citas-solid`, ejecuta uno por uno:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
docker compose exec -u www-data app php scripts/diagnosticar_correo.php
```

3. El diagnóstico debe indicar `Clave de recuperación ...: OK` y `ENVÍO DE PRUEBA CORRECTO`. Abre **http://localhost:8025** para ver el mensaje.
4. Después abre **http://localhost:8080**, entra en «¿Olvidaste tu contraseña?» y solicita un código a `paciente@clinica.local` (solo cuenta demo activa). Introduce el código real de Mailpit en SaludCita.

## Si aún falla

```powershell
docker compose ps
docker compose logs --tail=60 app mailpit
docker compose exec app sh -c 'ls -ld /var/lib/saludcita && ls -l /var/lib/saludcita/app.key'
```

**Nunca compartas el contenido de `app.key`, un código de recuperación ni contraseñas.** La última orden solo muestra metadatos. No ejecutes `docker compose down -v`: eliminaría también los volúmenes de PostgreSQL y claves. Docker Desktop debe permanecer ejecutándose para usar el sistema.

**Límites de verificación:** pruebas PHP y JS ejecutadas fuera de Docker. No se verificó el correo integrado en la computadora del usuario; hay que ejecutar el diagnóstico dentro de Docker para confirmarlo.
