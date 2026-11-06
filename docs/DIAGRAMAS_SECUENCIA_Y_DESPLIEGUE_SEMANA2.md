# Diseño de SaludCita — Diagramas de secuencia del Ciclo 1 y Ciclo 2

Estos diagramas complementan los 16 diagramas de comunicación existentes en `docs/diagramas/`.
Representan **la arquitectura actual del proyecto**, no reemplazan los diagramas conceptuales de análisis.
Los mensajes son resumidos: se omiten validaciones secundarias y detalles SQL.

## CU01 — Iniciar sesión

```mermaid
sequenceDiagram
    actor U as Usuario
    participant V as Vista JS
    participant C as AuthController
    participant S as LoginThrottle y Auth
    participant DB as PostgreSQL
    U->>V: Solicita iniciar sesión
    V->>C: POST /api/auth/login
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Verificar contraseña, rol y límite de intentos.

## CU02 — Registrar paciente

```mermaid
sequenceDiagram
    actor U as Paciente
    participant V as Vista JS
    participant C as AuthController
    participant S as RegistroUsuarios
    participant DB as PostgreSQL
    U->>V: Solicita registrar paciente
    V->>C: POST /api/auth/register
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Crear usuario y perfil paciente en transacción.

## CU03 — Administrar usuarios

```mermaid
sequenceDiagram
    actor U as Administrador
    participant V as Vista JS
    participant C as UsuarioController
    participant S as RegistroUsuarios
    participant DB as PostgreSQL
    U->>V: Solicita administrar usuarios
    V->>C: POST /api/users
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Alta de administrador o recepción; baja lógica en DELETE /api/users/{id}.

## CU04 — Gestionar médicos y especialidades

```mermaid
sequenceDiagram
    actor U as Administrador
    participant V as Vista JS
    participant C as CatalogoController
    participant S as RegistroUsuarios
    participant DB as PostgreSQL
    U->>V: Solicita gestionar médicos y especialidades
    V->>C: POST /api/doctors
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Crear usuario médico y asociarlo a su especialidad.

## CU05 — Configurar y eliminar horarios

```mermaid
sequenceDiagram
    actor U as Administrador
    participant V as Vista JS
    participant C as HorarioController
    participant S as ServicioHorarios
    participant DB as PostgreSQL
    U->>V: Solicita configurar y eliminar horarios
    V->>C: POST /api/schedules
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Validar intervalo; DELETE bloquea si existen citas futuras.

## CU06 — Agendar una cita

```mermaid
sequenceDiagram
    actor U as Paciente / Recepción
    participant V as Vista JS
    participant C as CitaController
    participant S as GestorCitas y ValidadorDisponibilidad
    participant DB as PostgreSQL
    U->>V: Solicita agendar una cita
    V->>C: POST /api/appointments
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Evitar duplicados por médico/paciente; enviar aviso.

## CU07 — Consultar agenda

```mermaid
sequenceDiagram
    actor U as Paciente / Médico / Recepción
    participant V as Vista JS
    participant C as CitaController
    participant S as Control de roles
    participant DB as PostgreSQL
    U->>V: Solicita consultar agenda
    V->>C: GET /api/appointments
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Filtrar citas propias para paciente o médico.

## CU08 — Gestionar estado de cita

```mermaid
sequenceDiagram
    actor U as Paciente / Médico / Recepción
    participant V as Vista JS
    participant C as CitaController
    participant S as PoliticaEstadosCita
    participant DB as PostgreSQL
    U->>V: Solicita gestionar estado de cita
    V->>C: PATCH /api/appointments/{id}/status
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Validar transición permitida y avisar al paciente.

## CU09 — Registrar consulta médica

```mermaid
sequenceDiagram
    actor U as Médico
    participant V as Vista JS
    participant C as HistoriaClinicaController
    participant S as Reglas de consulta
    participant DB as PostgreSQL
    U->>V: Solicita registrar consulta médica
    V->>C: POST /api/records
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Verificar cita propia pasada y guardar historia clínica.

## CU10 — Consultar historia clínica

```mermaid
sequenceDiagram
    actor U as Paciente / Médico
    participant V as Vista JS
    participant C as HistoriaClinicaController
    participant S as Control de acceso clínico
    participant DB as PostgreSQL
    U->>V: Solicita consultar historia clínica
    V->>C: GET /api/records
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Consultar solo registros del paciente o médico autorizados.

## CU11 — Emitir factura

```mermaid
sequenceDiagram
    actor U as Recepcionista
    participant V as Vista JS
    participant C as FacturaController
    participant S as ServicioCobros y FacturadorInterface
    participant DB as PostgreSQL
    U->>V: Solicita emitir factura
    V->>C: POST /api/invoices
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Calcular importe y guardar factura vinculada a cita.

## CU12 — Registrar pago

```mermaid
sequenceDiagram
    actor U as Recepcionista
    participant V as Vista JS
    participant C as FacturaController
    participant S as Validación de factura
    participant DB as PostgreSQL
    U->>V: Solicita registrar pago
    V->>C: PATCH /api/invoices/{id}/pay
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Marcar emitida como pagada y notificar.

## CU13 — Consultar facturas

```mermaid
sequenceDiagram
    actor U as Paciente / Recepción
    participant V as Vista JS
    participant C as FacturaController
    participant S as Control de permisos
    participant DB as PostgreSQL
    U->>V: Solicita consultar facturas
    V->>C: GET /api/invoices
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Filtrar facturación propia en perfil paciente.

## CU14 — Gestionar notificaciones

```mermaid
sequenceDiagram
    actor U as Usuario
    participant V as Vista JS
    participant C as NotificacionController
    participant S as NotificadorInterface
    participant DB as PostgreSQL
    U->>V: Solicita gestionar notificaciones
    V->>C: GET /api/notifications
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Listar avisos y marcar leído con PATCH.

## CU15 — Alta de paciente en recepción

```mermaid
sequenceDiagram
    actor U as Recepcionista
    participant V as Vista JS
    participant C as UsuarioController
    participant S as RegistroUsuarios
    participant DB as PostgreSQL
    U->>V: Solicita alta de paciente en recepción
    V->>C: POST /api/patients
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Crear cuenta con perfil de paciente.

## CU16 — Consultar indicadores

```mermaid
sequenceDiagram
    actor U as Usuario autenticado
    participant V as Vista JS
    participant C as DashboardController
    participant S as Consultas de indicadores
    participant DB as PostgreSQL
    U->>V: Solicita consultar indicadores
    V->>C: GET /api/dashboard
    C->>S: Validar rol y datos
    S->>DB: Consultar o modificar datos
    DB-->>S: Resultado
    S-->>C: Reglas cumplidas / error
    C-->>V: Respuesta JSON
    V-->>U: Mostrar resultado
```

**Regla principal:** Filtrar datos según perfil de acceso.

## Arquitectura lógica: paquetes

```mermaid
flowchart TB
    View["Presentación: resources/views y public/assets"] --> Controller["Aplicación: routes y Http/Controllers"]
    Controller --> Services["Servicios: GestorCitas, ServicioCobros, Politicas"]
    Services --> Contracts["Contratos: Interfaces SOLID"]
    Infrastructure["Infraestructura: Repositorios, notificaciones, correo"] -. "Implementa" .-> Contracts
    Infrastructure --> DB[("PostgreSQL")]
    Controller --> Infrastructure
```

## Arquitectura física: despliegue local y nube

```mermaid
flowchart TB
    L["Navegador local"] --> A["Docker: Apache + PHP 8.3"]
    A --> P[("Docker PostgreSQL 16")]
    A -. "SMTP de pruebas" .-> M["Mailpit local"]
    U["Navegador en Internet"] --> W["Render Web Service: Apache + PHP"]
    W --> R[("Render PostgreSQL")]
    W -. "Google OAuth: HTTPS" .-> G["Google Identity"]
```

## Funcionalidades complementarias

- **Google OIDC:** autentica mediante la cuenta previamente vinculada, sin conceder rol nuevo.
- **Bitácora:** triggers en PostgreSQL registran INSERT/UPDATE/DELETE en tablas supervisadas.
- **Tratamientos:** nueva ampliación de la segunda semana, ligada a CU09 y CU10; no altera el número original de CU.

## Revisión previa a la entrega

- Actualizar la versión DOCX/PDF para incorporar los diagramas de diseño y las funciones nuevas.
- Comprobar cada diagrama con un recorrido real por la aplicación y capturas de ejecución.
- Comprobar que las reglas de seguridad se respetan con los cuatro roles y que la base de datos se inicializa correctamente.
