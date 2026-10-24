# SaludCita — Publicar en Railway (sin Docker ni VS Code para los visitantes)

**Estado:** proyecto preparado para el despliegue, **no publicado todavía**. El dominio se genera al configurar Railway.

## Qué necesitas
- Una cuenta en [Railway](https://railway.com/) conectada a GitHub.
- El repositorio `https://github.com/majbaqueros/Proyecto-de-requisito-y-modelado` con estos archivos nuevos.
- Crédito de prueba o un plan activo en Railway. Comprueba el consumo y límites vigentes.

## 1. Actualizar tu GitHub

Este ZIP contiene el proyecto actualizado en la carpeta `clinica-citas-solid`.
Para publicarlo desde el repositorio que aparece en tu captura, **copia el contenido de esa carpeta al nivel raíz del repositorio**, no dentro de otra carpeta anidada. Incluye `Dockerfile.railway`, `scripts/start_railway.sh`, `scripts/init_production.php` y `public/vista-previa.html`.

Si ya tienes cambios recientes propios, **haz una copia de seguridad y compara** antes de reemplazar archivos. No subas `.env`, claves, contraseñas, volúmenes ni datos de pacientes.

## 2. Crear el proyecto en Railway

1. Abre Railway → **New Project** → **Empty project**.
2. Añade **PostgreSQL** desde `+ New` → `Database` → `PostgreSQL`. Déjalo privado.
3. Añade un servicio web desde **GitHub Repo** y escoge `majbaqueros/Proyecto-de-requisito-y-modelado`, rama `main`.
4. En Variables del servicio web añade los campos indicados abajo. Cuando exista PostgreSQL, elige **Reference Variable** para que apunten a sus `PG...`.
5. **Settings → Build**: Dockerfile path = `Dockerfile.railway` (o pon `RAILWAY_DOCKERFILE_PATH=Dockerfile.railway` entre las variables).
6. Comprueba que el servicio escuche `PORT=80` y que Networking tenga **Generate Domain**.
7. Despliega y revisa `Deploy Logs`: debe decir `SaludCita: esquema de PostgreSQL verificado`.

## 3. Variables del servicio web

| Nombre | Valor |
|---|---|
| `RAILWAY_DOCKERFILE_PATH` | `Dockerfile.railway` |
| `PORT` | `80` |
| `APP_ENV` | `production` |
| `APP_TZ` | `America/La_Paz` |
| `APP_KEY` | clave aleatoria **privada** de 64 caracteres hexadecimales |
| `DB_HOST` | referencia a `Postgres.PGHOST` |
| `DB_PORT` | referencia a `Postgres.PGPORT` |
| `DB_NAME` | referencia a `Postgres.PGDATABASE` |
| `DB_USER` | referencia a `Postgres.PGUSER` |
| `DB_PASSWORD` | referencia a `Postgres.PGPASSWORD` |
| `CLOUD_ADMIN_EMAIL` | correo inicial del administrador (tu correo, no el correo de prueba) |
| `CLOUD_ADMIN_PASSWORD` | contraseña fuerte inicial (mínimo 12 caracteres; **no reutilices** las contraseñas de demostración) |
| `AUTH_MAX_ATTEMPTS` | `5` |
| `AUTH_LOCK_MINUTES` | `5` |

Si tu servicio de base de datos se llama diferente de `Postgres`, usa ese nombre real al seleccionar las referencias. Railway permite añadirlas desde su propia interfaz sin escribir la sintaxis manualmente.

Para generar la clave desde una terminal local con PHP:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

**No publiques la clave ni la contraseña en GitHub.** Tras iniciar el servicio y confirmar que el administrador existe, elimina `CLOUD_ADMIN_PASSWORD` de las variables de Railway. No cambies `APP_KEY` porque invalidarías los códigos de recuperación pendientes.

## 4. Correo de recuperación

Mailpit es solo para pruebas **locales**. En Railway no se instala Mailpit. Si quieres enviar códigos de recuperación, configura las variables `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` con tu proveedor **SMTP real**. Confirma que tu plan de Railway permita la conexión saliente al servidor SMTP. Sin estas variables la recuperación por correo no funcionará.

## 5. URL pública y vista previa

Una vez desplegado, desde el servicio web → **Settings → Networking → Generate Domain** obtendrás algo como `https://tu-app.up.railway.app` (el nombre real lo asigna Railway).

- `https://tu-dominio/vista-previa.html` → recorrido público **sin sesión** y sin datos personales.
- `https://tu-dominio/` → login real de SaludCita.
- `https://tu-dominio/administrador` → panel tras autenticación de administrador.

Todo se ejecuta **en Railway**: los visitantes solo necesitan un navegador; tú tampoco necesitas dejar tu PC encendida.

## 6. Seguridad antes de compartir el dominio

- **Solo datos ficticios**, especialmente en un proyecto con historias clínicas. No subas una copia de tu base local ni documentos reales.
- El servidor cloud crea una **base nueva**; no migra automáticamente los datos de Docker local.
- No compartas el acceso de administrador. Para mostrar pantallas sin permisos, usa `/vista-previa.html`.
- El código público ya no incorpora las claves DEMO en JavaScript y el inicializador cloud no crea cuentas DEMO.
- Los correos reales, contraseñas y claves permanecen **solo en Variables** de Railway, nunca en el repositorio.
- Activa backups del Postgres, revisa costos y configura retención de logs si el proyecto va a permanecer en línea.
- Este es un proyecto universitario y no un sistema sanitario certificado. Realiza una revisión de seguridad independiente antes de tratar datos médicos reales.

## 7. Solucionar fallos típicos

- **No Build / Dockerfile not found**: revisa que los archivos estén en la raíz de GitHub y `RAILWAY_DOCKERFILE_PATH` sea `Dockerfile.railway`.
- **App failed to respond**: revisa `PORT=80` y que no haya otro comando de inicio que reemplace el `CMD` del Dockerfile.
- **Postgres connection refused**: selecciona Reference Variables de PostgreSQL, no `DB_HOST=db` (ese nombre era solo Docker local).
- **Falta cuenta administrativa**: configura `CLOUD_ADMIN_EMAIL` y `CLOUD_ADMIN_PASSWORD`, despliega y consulta logs.
- **Password recovery sin código**: falta SMTP externo; Mailpit no sirve para enviar a Gmail.
- **Cambios en Railway no se ven**: confirma que GitHub tiene los archivos nuevos y redepliega el servicio web.

## Archivos añadidos o cambiados

- `Dockerfile.railway`: imagen de PHP/Apache para Railway.
- `scripts/start_railway.sh`: inicialización y arranque de Apache.
- `scripts/init_production.php`: crea tablas y primer administrador (sin sembrar demos).
- `public/index.php`: añade `/healthz` y cookies Secure en nube.
- `public/assets/app.js`: elimina cuentas DEMO expuestas y referencia local a Mailpit.
- `public/vista-previa.html` y `public/assets/preview/`: vista pública solo lectura.

**Nota:** `docker-compose.yml` y el `Dockerfile` original permanecen intactos para desarrollo local.
