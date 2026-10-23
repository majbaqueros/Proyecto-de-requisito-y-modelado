# SaludCita v3 — Proyecto universitario (Requisitos, Análisis y SOLID)

**Documento de proyecto actualizado:** [`docs/DOCUMENTO_REQUISITOS_Y_ANALISIS_COMPLETO.md`](docs/DOCUMENTO_REQUISITOS_Y_ANALISIS_COMPLETO.md). La documentación tiene Perfil, Marco Teórico, Modelo de Negocio, Captura de Requisitos (1.1–1.5), Análisis (2.1–2.4), 16 casos de uso en dos ciclos, 25 diagramas, tablas de clases y trazabilidad.

**Interfaz mejorada (HTML/CSS/JavaScript):** diseño de login/panel actualizado, modo claro-oscuro (preferencia local), buscador de secciones `Ctrl + K`, tablas filtrables de citas, tarjetas resumen con contadores animados y diseño adaptativo móvil. Todas estas mejoras están en `public/assets/app.js` y `public/assets/styles.css`, sin instalar Node ni cambiar el backend de PHP.

**Advertencia de privacidad:** prototipo de curso. No cargar historias clínicas ni contraseñas reales, ni usar como sistema de atención en producción. Los datos de demostración y descuentos son ficticios.

### Actualizar desde SaludCita v2 sin perder la base de datos

1. Copia de seguridad de la carpeta del proyecto y de la base de datos si contiene datos importantes.
2. Reemplaza los archivos del directorio `clinica-citas-solid` por los de este paquete.
3. Con Docker Desktop abierto, en esa carpeta ejecuta:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
```

4. Abre `http://localhost:8080` y actualiza la pestaña con `Ctrl + F5`.

**No ejecutes `docker compose down -v`: eliminaría el volumen PostgreSQL.** El script de instalación usa operaciones de inicialización/semilla; antes de ejecutarlo sobre una base con datos propios, haz respaldo.

### Pruebas realizadas y límites

- `php tests/run.php`: prueba de servicios SOLID, reglas de cita y mapeo de rutas.
- Sintaxis `php -l` y `node --check` revisada.
- Interfaz comprobada en Chromium con respuestas simuladas: login, dashboard, agenda, filtros, `Ctrl + K`, modo oscuro y diseño móvil.
- **Pendiente:** probar todas las rutas con PostgreSQL y Docker de la computadora del estudiante. La visualización no equivale a verificación de integración.

---

# SaludCita — Citas médicas, SOLID y UML

**Segundo parcial — Ingeniería de Requisitos y Modelado**

Sistema web académico de citas médicas, inspirado en la **organización de carpetas** del proyecto de referencia *Taller Mitsü*, pero desarrollado como aplicación propia e independiente. El objetivo es demostrar análisis de requisitos, casos de uso, diagramas UML, arquitectura en capas, separación por responsabilidades, **SRP/OCP/DIP**, e implementación.

**Stack:** PHP 8.3 + Apache, PostgreSQL 16, HTML, CSS y JavaScript. Entorno local con Docker Compose. **No es un proyecto Laravel** ni utiliza Node.js, React o Java; toma únicamente el patrón organizativo de controladores, servicios y rutas del ejemplo. No requiere Composer ni npm para ejecutarse con Docker.

## Iniciar o actualizar desde VS Code (Windows)

Abre la terminal **dentro de `clinica-citas-solid`**, donde está el archivo `docker-compose.yml`:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
```

Abre **http://localhost:8080**. Docker debe indicar **Engine running**; VS Code puede estar cerrado.

Si ya tienes una versión anterior en esa misma carpeta, **haz una copia de seguridad primero** y reemplaza sus archivos por los de esta nueva versión. Luego ejecuta `docker compose up -d --build` y `docker compose exec app php scripts/install.php`. Los servicios usan un volumen para los datos de PostgreSQL: **no uses `docker compose down -v`**, porque eliminaría el volumen y los datos. El instalador usa `CREATE TABLE IF NOT EXISTS` y datos de demostración reutilizables; no borra usuarios existentes.

**Comprobar servicios:** `docker compose ps`. **Pruebas:** `docker compose exec app php tests/run.php`. **Detener sin eliminar datos:** `docker compose stop`. Si el navegador conserva la versión anterior, actualiza con `Ctrl + F5`.

## Usuarios de prueba (datos ficticios)

| Perfil | Correo | Contraseña |
|---|---|---|
| Paciente | `paciente@clinica.local` | `Paciente123*` |
| Recepcionista | `recepcion@clinica.local` | `Recep123*` |
| Médico | `medico@clinica.local` | `Medico123*` |
| Administrador | `admin@clinica.local` | `Admin123*` |

La pantalla inicial ofrece **Usar demo**. También puedes entrar a `/paciente`, `/recepcionista`, `/medico` o `/administrador`. Existe **Crear cuenta** para registrar únicamente **pacientes**. Los otros tres roles solo los puede crear personal autorizado.

## Funciones por rol

| Funcionalidad | Paciente | Recepción | Médico | Administración |
|---|---|---|---|---|
| Inicio de sesión y panel individual | ✓ | ✓ | ✓ | ✓ |
| Registro de paciente | Propio | ✓ | — | ✓ |
| Reservar cita médica | Para sí mismo | ✓ | — | ✓ |
| Ver/actualizar citas | Propias; cancelar | Confirmar/cancelar | Propias; completar | Todas |
| Historia clínica | Consultar propia | — | Registrar/consultar propia agenda | — |
| Facturas y pagos | Ver propias | Emitir y registrar pago | — | Emitir y registrar pago |
| Campana de avisos/notificaciones | Propias | Propias | Propias | Propias |
| Médicos, especialidades y horarios | Consultar | Consultar | Consultar | Crear y consultar |
| Personal / cuentas administrativas | — | — | — | Crear / consultar |

**Facturación y pagos:** simulación académica en bolivianos (Bs). La tarifa de «seguro» aplica un descuento demostrativo del 20%; no se conecta a aseguradoras, bancos, sistemas de pago reales ni al SIN. Las notificaciones son **internas**, no envían correos reales ni WhatsApp.

## Estructura por clases (similar al proyecto del otro grupo)

```text
clinica-citas-solid/
├── app/
│   ├── Core/                 # Sesiones, conexión PDO, respuestas HTTP, autoload
│   ├── Http/
│   │   ├── Router.php        # Despacho de rutas
│   │   ├── Controllers/      # Auth, Dashboard, Cita, Paciente/Usuario,
│   │   │                     # Catálogo, Historia Clínica, Factura, Notificación
│   │   └── Requests/         # Validaciones y normalización de formularios
│   ├── Contracts/            # Interfaces de citas, facturación y notificación
│   ├── Models/               # Entidad Cita
│   ├── Services/             # GestorCitas, disponibilidad, registro de usuarios,
│   │                         # estados, cobros, catálogo de estrategias
│   └── Infrastructure/       # Implementaciones PostgreSQL, en memoria y avisos
├── routes/api.php            # Tabla de rutas y acciones
├── resources/views/app.html  # Documento HTML de la aplicación
├── public/
│   ├── index.php             # Front controller breve
│   └── assets/app.js         # Pantallas JS; HTML/CSS
├── database/schema.sql       # Base de datos y restricciones
├── scripts/install.php       # Instalación y datos de prueba
├── tests/run.php             # Pruebas unitarias SIN PostgreSQL
├── docs/REQUISITOS_Y_UML.md  # Casos de uso y diagramas
├── docs/ESTRUCTURA_Y_ENTREGA.md
├── Dockerfile
└── docker-compose.yml
```

### SOLID: una razón de cambio por clase

- **`GestorCitas`:** orquesta registro, validación y notificación de citas.
- **`ValidadorDisponibilidad`:** valida fecha futura, intervalos de 30 min y disponibilidad.
- **`PoliticaEstadosCita`:** decide transiciones de estado autorizadas.
- **`ServicioCobros`:** coordina cálculo y guardado de facturas.
- **`RegistroUsuarios`:** crea usuarios y contraseñas cifradas con hash.
- **`CitaRepositoryInterface`:** almacenamiento intercambiable (`PostgresCitaRepository`, `RepositorioCitasMemoria`).
- **`NotificadorInterface`:** canales intercambiables (`NotificadorEnSistema`, `NotificadorBitacora`).
- **`FacturadorInterface`:** estrategias intercambiables (`FacturadorNormal`, `FacturadorSeguro`).
- **`CatalogoFacturadores`:** registra y selecciona estrategias sin `if` de tipos dentro de `ServicioCobros`.

**OCP:** agregar `FacturadorConvenio implements FacturadorInterface`, `NotificadorCorreo implements NotificadorInterface` o un nuevo repositorio sin reescribir `GestorCitas`/`ServicioCobros`; la implementación nueva debe registrarse en el punto de composición. **DIP:** los servicios dependen de interfaces.

## Demostración en clase

1. Entrar como **Administrador**: mostrar médicos, especialidades, horarios y cuentas.
2. Entrar como **Paciente** o registrar uno nuevo: buscar médico, reservar un turno futuro y comprobar la campana de notificaciones.
3. Intentar reservar el mismo turno dos veces para verificar reglas de disponibilidad.
4. Entrar como **Recepcionista**: confirmar la cita, facturarla y registrar pago.
5. Volver al **Paciente**: revisar la factura pagada y las notificaciones.
6. Entrar como **Médico**: ver citas asignadas y registrar una historia clínica de una cita confirmada pasada de los datos demo.
7. Mostrar `app/Contracts`, `app/Services`, `app/Infrastructure` y ejecutar las pruebas de SOLID.
8. Presentar diagramas de casos de uso, clases, paquetes, secuencias, comunicación y despliegue de `docs/`.

## Diferencia entre Docker, Vercel y Railway

- **Docker:** empaqueta y ejecuta PHP y PostgreSQL **en tu computadora**; no es un dominio de Internet.
- **Vercel:** plataforma frecuente para frontend web; una página publicada allí no ejecutará sin más el servidor PHP y la BD de este proyecto local.
- **Railway:** plataforma posible para desplegar servicios y PostgreSQL. Publicar SaludCita requeriría adaptar variables de entorno, seguridad, persistencia, HTTPS y configurar alojamiento público.
- **Node.js no es Java:** Node ejecuta JavaScript en servidor. Para este proyecto mantenemos PHP conforme a la tecnología elegida en la pizarra.

**La versión entregada funciona en local con Docker; no está desplegada a Internet.** No cambiaremos una implementación operativa solo porque otro equipo haya elegido otro stack.

## Seguridad y alcance

Es una **demostración universitaria**, no un producto clínico real. Incluye control de roles en servidor, hash de contraseñas, consultas preparadas, cookie HTTP-only y token CSRF. Faltan, entre otros: límites contra ataques de fuerza bruta, auditoría de acceso a historias médicas, gestión de consentimiento, recuperación segura de contraseñas, copias de seguridad automatizadas, cifrado específico de datos médicos y evaluación de normativa aplicable. No uses nombres, correos, documentos ni historias médicas reales.

Para el detalle de requisitos y diagramas, consulta **`docs/REQUISITOS_Y_UML.md`** y **`docs/ESTRUCTURA_Y_ENTREGA.md`**.
