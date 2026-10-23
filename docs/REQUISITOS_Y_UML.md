# Análisis y diseño — SaludCita

**Asignatura:** Ingeniería de Requisitos y Modelado. **Proyecto académico:** gestión de citas médicas.

## 1. Objetivo

Administrar citas médicas en una clínica con autenticación por cuatro roles, control de disponibilidad,
registro de atención, facturación sencilla y notificaciones internas. El diseño utiliza interfaces para
poder extender facturación, notificaciones y persistencia sin modificar el gestor de citas.

## 2. Actores y permisos

- **Paciente:** ver agenda propia, solicitar y cancelar citas, consultar médicos, historias clínicas propias,
  facturas propias y avisos.
- **Recepcionista:** alta de pacientes, reserva/confirmación/cancelación de citas, emisión de facturas y registro de pagos.
- **Médico:** ver agenda propia, completar una cita y registrar síntomas, diagnóstico y tratamiento de sus pacientes.
- **Administrador:** altas de profesionales, recepción y administradores; configuración de especialidades
  y horarios, gestión global de citas y facturación. No accede automáticamente a historias clínicas.

## 3. Requisitos funcionales

| ID | Requisito | Actor principal |
|---|---|---|
| RF-01 | Iniciar sesión según rol | Todos |
| RF-02 | Registrar pacientes | Recepción, administrador |
| RF-03 | Dar de alta médicos y especialidades | Administrador |
| RF-04 | Configurar horario semanal de médicos | Administrador |
| RF-05 | Agendar cita en intervalos de 30 minutos y verificar disponibilidad | Paciente, recepción, administrador |
| RF-06 | Consultar, confirmar y cancelar citas según permisos | Todos, según rol |
| RF-07 | Registrar consulta y tratamiento, con cita asociada | Médico |
| RF-08 | Generar factura normal o con descuento de seguro de demostración | Recepción, administrador |
| RF-09 | Registrar pago de factura | Recepción, administrador |
| RF-10 | Leer notificaciones internas | Cada usuario |
| RF-11 | Gestionar usuarios administrativos | Administrador |

## 4. Requisitos no funcionales

- **RNF-01 Seguridad:** contraseñas con `password_hash`; sesión HTTP-only, token CSRF, consultas preparadas y control de rol en cada endpoint.
- **RNF-02 Integridad:** índices únicos parciales impiden dobles reservas activas por médico y por paciente.
- **RNF-03 Modularidad:** aplicar SRP, OCP y DIP a orquestación, notificación, facturación y persistencia.
- **RNF-04 Usabilidad:** panel responsive para los cuatro roles, validaciones visibles.
- **RNF-05 Mantenibilidad:** capas y clases separadas en carpetas, pruebas unitarias sin conexión a BD.

## 5. Reglas de negocio

- La fecha de una reserva debe ser futura y el minuto debe ser `00` o `30`.
- El turno tiene **30 minutos** y debe caber completamente en un horario semanal habilitado.
- No puede haber dos citas no canceladas para el mismo médico y turno, ni dos citas del mismo paciente a la misma hora.
- Estados permitidos: `pendiente` → `confirmada` → `completada`; `pendiente` o `confirmada` → `cancelada`.
- Solo las citas confirmadas o completadas se pueden facturar; máximo una factura por cita.
- Solo el médico asignado puede registrar una consulta de su cita pasada/actual; máximo un registro por cita.
- La tarifa de `seguro` (20 %) es ilustrativa, no hay integración con aseguradora ni emisión fiscal.

## 6. Diagrama de casos de uso (representación Mermaid)

```mermaid
flowchart LR
    Pac[Paciente] --> CU1([Solicitar y cancelar cita])
    Pac --> CU2([Consultar historia y factura])
    Rec[Recepcionista] --> CU3([Registrar paciente])
    Rec --> CU4([Confirmar cita])
    Rec --> CU5([Facturar y registrar pago])
    Med[Médico] --> CU6([Revisar agenda])
    Med --> CU7([Registrar consulta y tratamiento])
    Adm[Administrador] --> CU8([Gestionar médicos y horarios])
    Adm --> CU9([Administrar usuarios])
    Adm --> CU4
    Adm --> CU5
    CU1 -. incluye .-> CU10([Validar disponibilidad])
```

## 7. Diagrama de clases — OCP y SRP

```mermaid
classDiagram
  direction TB
  class Cita {
    +int pacienteId
    +int medicoId
    +string fechaHora
    +string motivo
  }
  class GestorCitas {
    +agendar(Cita, usuarioPacienteId)
  }
  class ValidadorDisponibilidad {
    +verificar(Cita)
  }
  class CitaRepositoryInterface {
    <<interface>>
    +disponible(Cita) bool
    +guardar(Cita) array
  }
  class NotificadorInterface {
    <<interface>>
    +enviar(usuario, titulo, mensaje) void
  }
  class FacturadorInterface {
    <<interface>>
    +tipo() string
    +calcular(precio) array
  }
  class FacturaRepositoryInterface {
    <<interface>>
    +guardar(cita, tipo, calculo) array
  }
  class ServicioCobros {
    +generar(citaId, precio) array
  }
  class PostgresCitaRepository
  class RepositorioCitasMemoria
  class NotificadorEnSistema
  class NotificadorBitacora
  class FacturadorNormal
  class FacturadorSeguro
  class PostgresFacturaRepository
  GestorCitas --> CitaRepositoryInterface
  GestorCitas --> NotificadorInterface
  GestorCitas --> ValidadorDisponibilidad
  ValidadorDisponibilidad --> CitaRepositoryInterface
  GestorCitas ..> Cita
  ServicioCobros --> FacturadorInterface
  ServicioCobros --> FacturaRepositoryInterface
  CitaRepositoryInterface <|.. PostgresCitaRepository
  CitaRepositoryInterface <|.. RepositorioCitasMemoria
  NotificadorInterface <|.. NotificadorEnSistema
  NotificadorInterface <|.. NotificadorBitacora
  FacturadorInterface <|.. FacturadorNormal
  FacturadorInterface <|.. FacturadorSeguro
  FacturaRepositoryInterface <|.. PostgresFacturaRepository
```

## 8. Diagrama de secuencia — CU-01 Agendar cita

```mermaid
sequenceDiagram
  actor P as Paciente
  participant UI as Interfaz web
  participant API as API PHP
  participant G as GestorCitas
  participant V as ValidadorDisponibilidad
  participant R as CitaRepositoryInterface
  participant N as NotificadorInterface
  P->>UI: Selecciona médico y fecha
  UI->>API: POST /api/appointments
  API->>G: agendar(cita, paciente)
  G->>V: verificar(cita)
  V->>R: disponible(cita)
  R-->>V: bool
  alt Está disponible
    G->>R: guardar(cita)
    R-->>G: Cita creada
    G->>N: enviar(confirmación)
    G-->>API: Cita creada
    API-->>UI: 201 Created
  else No disponible
    V-->>API: Error de negocio
    API-->>UI: 422 Horario no disponible
  end
  UI-->>P: Muestra resultado
```

## 9. Diagrama de paquetes (diseño lógico en capas)

```mermaid
flowchart TB
  P[Presentación: HTML / CSS / JS] --> A[Aplicación: API, GestorCitas, ServicioCobros]
  A --> D[Dominio: Cita, interfaces, validador]
  I[Infraestructura: repositorios y notificador] -. implementa interfaces .-> D
  I --> DB[(PostgreSQL)]
  A --> I
```

## 10. Diagrama de despliegue (diseño físico)

```mermaid
flowchart LR
  B[Navegador web] -->|HTTP puerto 8080| W[Contenedor PHP 8.3 + Apache]
  W -->|PDO / red interna| DB[(Contenedor PostgreSQL 16)]
  DB --> DISK[(Volumen persistente)]
```

## 11. Extensión OCP

**Cambio en facturación:** escribir `FacturadorConvenio implements FacturadorInterface` y registrarlo en la configuración / composición de dependencias, sin cambiar `ServicioCobros`.

**Cambio en notificaciones:** escribir `NotificadorEmail implements NotificadorInterface` (requiere servidor SMTP) y usarlo al construir `GestorCitas`, sin tocar su lógica.

**Cambio en base de datos:** implementar `CitaRepositoryInterface` y enlazar el nuevo adaptador en el punto de composición.

La selección de implementaciones se realiza actualmente en la ruta API (composición de objetos). Agregar un tipo seleccionable de factura exige exponerlo en la selección de estrategias de esa capa, **pero no modificar las clases de negocio existentes**.

## 12. Criterios de aceptación

1. Ingresar con los cuatro perfiles de ejemplo.
2. Paciente agenda un turno disponible; se crea una notificación interna.
3. Intentar otra reserva para ese mismo médico/horario devuelve error.
4. Recepcionista confirma la cita y genera factura normal o con descuento.
5. Recepcionista registra pago; paciente ve factura y aviso.
6. Médico consulta su agenda y registra síntomas, diagnóstico y tratamiento solo de citas propias.
7. Paciente ve únicamente su historia clínica y su facturación.
8. El administrador puede crear un médico y sus horarios, sin poder leer historias clínicas.
9. Las pruebas de servicios funcionan sin PostgreSQL gracias al repositorio en memoria.

## 13. Trabajo fuera del alcance del prototipo

No integra SMS, WhatsApp, SMTP real, facturación electrónica del SIN, pagos reales, sistema hospitalario,
recetas firmadas, historia clínica interoperable ni controles legales/compliance completos. No usar datos
médicos reales en el entorno de demostración.

## 14. Diagrama de secuencia — CU-02 Emitir factura

```mermaid
sequenceDiagram
    actor R as Recepcionista
    participant UI as Interfaz
    participant API as API PHP
    participant S as ServicioCobros
    participant F as FacturadorInterface
    participant REP as FacturaRepositoryInterface
    R->>UI: Elige cita confirmada y tipo de factura
    UI->>API: POST /api/invoices
    API->>API: Comprueba permisos, estado y precio
    API->>S: generar(cita, precio)
    S->>F: calcular(precio)
    F-->>S: Subtotal, descuento y total
    S->>REP: guardar(cita, tipo, importes)
    REP-->>S: Factura emitida
    S-->>API: Factura
    API-->>UI: 201 + importes
```

## 15. Diagrama de secuencia — CU-03 Registrar consulta

```mermaid
sequenceDiagram
    actor M as Médico
    participant UI as Interfaz
    participant API as API PHP
    participant DB as PostgreSQL
    M->>UI: Registra síntomas, diagnóstico y tratamiento
    UI->>API: POST /api/records
    API->>DB: Busca cita y médico asignado
    DB-->>API: Datos de cita
    API->>API: Verifica titularidad y fecha
    alt Cita autorizada y ya iniciada
      API->>DB: BEGIN; guarda registro; completa cita; COMMIT
      DB-->>API: Confirmación
      API-->>UI: Registro creado
    else No autorizada
      API-->>UI: Error de acceso / validación
    end
```

## 16. Diagrama de comunicación — CU-01 Registrar cita

Los mismos participantes del diagrama de secuencia se representan por las siguientes relaciones y mensajes numerados:

```mermaid
flowchart LR
    P[1. Paciente] -->|1: solicita agendar| UI[2. Interfaz]
    UI -->|2: POST citas| API[3. API PHP]
    API -->|3: agendar| G[4. GestorCitas]
    G -->|4: verificar| V[5. Validador]
    V -->|5: disponible| R[(6. Repositorio de citas)]
    G -->|6: guardar| R
    G -->|7: enviar aviso| N[7. Notificador]
```

## 17. Evolución de la estructura — Inspiración del proyecto de referencia

En la segunda versión se separaron las rutas y el enrutador de los controladores, de forma similar a la organización del proyecto de ejemplo Laravel (sin copiar su framework). Las clases de HTTP están ahora en `app/Http/Controllers` y `app/Http/Requests`; las rutas se declaran en `routes/api.php`, la página está en `resources/views`, los contratos permanecen en `app/Contracts`, la lógica de negocio está en `app/Services` y los adaptadores en `app/Infrastructure`.

## 18. RF adicionales de la versión mejorada

| ID | Requisito adicional | Criterio de aceptación |
|---|---|---|
| RF-12 | Registro autónomo de pacientes | Un visitante puede registrar paciente y no puede atribuirse rol médico/administrador |
| RF-13 | Campana de notificaciones | El usuario consulta solo sus mensajes y puede marcar como leído |
| RF-14 | Resumen de estado de pagos | Paciente ve su saldo de facturas emitidas, pagadas y estado «al día» |

En particular, el registro de paciente es independiente del formulario de alta utilizado por recepción. La política de estados de citas se separó en `PoliticaEstadosCita` y el selector de estrategia de factura en `CatalogoFacturadores`, evitando cambios a `ServicioCobros` al introducir un nuevo cálculo.


## 19. Diagrama de clases del dominio (relaciones de datos)

Este diagrama completa el de interfaces y responsabilidades con la estructura relacional del caso de estudio:

```mermaid
classDiagram
  class Usuario {
    +id
    +nombre
    +email
    +rol
  }
  class Paciente {
    +id
    +documento
    +fechaNacimiento
  }
  class Medico {
    +id
    +especialidad
    +precioConsulta
  }
  class Horario {
    +diaSemana
    +inicio
    +fin
  }
  class Cita {
    +fechaHora
    +motivo
    +estado
  }
  class HistoriaClinica {
    +sintomas
    +diagnostico
    +tratamiento
  }
  class Factura {
    +subtotal
    +descuento
    +total
    +estado
  }
  class Notificacion {
    +titulo
    +mensaje
    +leidaEn
  }
  Usuario "1" -- "0..1" Paciente : perfil paciente
  Usuario "1" -- "0..1" Medico : perfil médico
  Usuario "1" -- "0..*" Notificacion : recibe
  Paciente "1" -- "0..*" Cita : solicita
  Medico "1" -- "0..*" Cita : atiende
  Medico "1" -- "0..*" Horario : configura
  Cita "1" -- "0..1" HistoriaClinica : genera
  Cita "1" -- "0..1" Factura : origina
```

**Nota:** estas clases conceptuales representan tablas y relaciones de dominio. La implementación PHP utiliza `App\Models\Cita` como entidad explícita y consultas preparadas con PDO para las demás; no utiliza un ORM Laravel ni presupone que todas las tablas tengan una clase ActiveRecord.
