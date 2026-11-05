# SaludCita — Semana 2 / Ciclo 2: seguimiento de tratamientos

## Estado
Esta rama agrega una ampliación funcional a los CU09–CU10 (historia clínica). Los casos CU11–CU16 (facturación, pago, consulta de facturas, avisos, registro desde recepción y dashboard) ya se encontraban en la versión anterior. No se modifican sus reglas de negocio.

## Casos de uso ampliados
- Médico registra un plan vinculado a una historia clínica de una consulta propia.
- Médico suspende, reanuda o finaliza planes de su responsabilidad.
- Paciente consulta únicamente sus planes.
- Recepcionista y Administrador no acceden a la información clínica de seguimiento.

## Persistencia
La migración database/migrations/006_ciclo2_tratamientos.sql crea treatment_plans con FK hacia clinical_records, patients y doctors. Se ejecuta después de 005_security.sql. La bitácora registra las operaciones sin almacenar títulos ni instrucciones clínicas.

## Reglas
TratamientoController: autorización y endpoints; PoliticaTratamientos: validación pura (SRP); PostgreSQL: consistencia y vínculos. Estados: activo a suspendido/finalizado; suspendido a activo/finalizado; finalizado sin transiciones.

## API
- GET /api/treatments (Paciente propio / Médico propio)
- POST /api/treatments (Médico)
- PATCH /api/treatments/{id}/status (Médico asignado)

## Pruebas locales
1. Haz copia de PostgreSQL.
2. docker compose up -d --build
3. docker compose exec app php scripts/install.php
4. docker compose exec app php tests/politica_tratamientos.php
5. Abrir /medico/tratamientos y /paciente/tratamientos.
6. Comprobar permisos y bitácora.

No usar datos clínicos reales. La integración completa con PostgreSQL y Render aún requiere pruebas. No borrar volúmenes.
