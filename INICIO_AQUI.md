# SaludCita V5.4 — Empezar aquí

**Problema corregido:** Clave de recuperación no disponible para Apache en Windows con Docker. Ahora se almacena en un volumen persistente privado.

**Instrucciones detalladas:** [`CORRECCION_V5_4_CLAVE_DOCKER.md`](CORRECCION_V5_4_CLAVE_DOCKER.md)

Desde `clinica-citas-solid` ejecuta:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
docker compose exec -u www-data app php scripts/diagnosticar_correo.php
```

- SaludCita: http://localhost:8080
- Mailpit: http://localhost:8025
- Bitácora: http://localhost:8080/administrador/bitacora

**No borres `.env`, `storage/` ni los volúmenes Docker. No uses `docker compose down -v`.** Haz una copia de seguridad antes de reemplazar archivos.
