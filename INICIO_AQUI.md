# SaludCita — Proyecto actualizado, v3

**Empezar aquí:** Esta es una actualización del proyecto en PHP/PostgreSQL que ya ejecutabas en Docker. No es un proyecto diferente ni requiere Node.js. **Haz respaldo de la carpeta y base de datos antes de reemplazar el contenido.**

## Documentos para la materia

- `docs/SaludCita_Documentacion_Requisitos_Analisis_v3.docx`: documento editable de 38 páginas con índice, perfil, marco teórico, modelo de negocio, captura de requisitos, análisis, 16 casos de uso y diagramas.
- `docs/SaludCita_Documentacion_Requisitos_Analisis_v3.pdf`: versión lista para lectura.
- `docs/DOCUMENTO_REQUISITOS_Y_ANALISIS_COMPLETO.md`: origen editable con vínculos a los 25 diagramas y capturas de prototipo.
- `docs/diagramas/`: diagramas en PNG.
- `docs/prototipos/`: capturas de la interfaz. Las capturas autenticadas usan **datos simulados**.

## Cambios visuales

- Login mejorado, con controles para mostrar contraseña y cambiar tema.
- Dashboard con tarjetas, indicadores, vistas por rol y animaciones ligeras.
- Buscador de secciones con `Ctrl + K` en cualquier pantalla autenticada.
- Búsqueda en tiempo real y filtro por estado para el listado de citas.
- Diseño adaptable a teléfonos y opción claro/oscuro guardada localmente.

## Instalar o actualizar en tu Windows

Abre Docker Desktop y usa una terminal de VS Code **dentro de la carpeta que contiene `docker-compose.yml`**:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
```

Después abre http://localhost:8080 y pulsa `Ctrl + F5`. Comprueba que la base ya esté creada. El instalador añade datos de demostración si faltan. Nunca ejecutes `docker compose down -v` sobre datos que quieras conservar.

## Afirmaciones y pruebas

El código de servicios y rutas pasó las pruebas unitarias `php tests/run.php`. Se verificó JavaScript con `node --check` y navegación visual en Chromium usando respuestas API simuladas. **No se ejecutó prueba integrada contra PostgreSQL ni la instalación real de Docker del estudiante en este entorno.**

Proyecto académico sin integración fiscal, aseguradora ni mensajería real. No apto para historias clínicas de pacientes reales sin controles y revisión normativos adicionales.
