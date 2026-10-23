<?php
/** Tabla declarativa: método, expresión de ruta, clase, acción, acceso público. */
use App\Http\Controllers\{AuthController,DashboardController,CatalogoController,UsuarioController,CitaController,HistoriaClinicaController,FacturaController,NotificacionController};
return [
    ['POST', '~^/api/auth/login$~', AuthController::class, 'login', true],
    ['GET', '~^/api/auth/me$~', AuthController::class, 'me', true],
    ['POST', '~^/api/auth/register$~', AuthController::class, 'register', true],
    ['POST', '~^/api/auth/logout$~', AuthController::class, 'logout', true],
    ['GET', '~^/api/dashboard$~', DashboardController::class, 'index', false],
    ['GET', '~^/api/specialties$~', CatalogoController::class, 'especialidades', false],
    ['POST', '~^/api/specialties$~', CatalogoController::class, 'crearEspecialidad', false],
    ['GET', '~^/api/doctors$~', CatalogoController::class, 'medicos', false],
    ['POST', '~^/api/doctors$~', CatalogoController::class, 'crearMedico', false],
    ['GET', '~^/api/schedules$~', CatalogoController::class, 'horarios', false],
    ['POST', '~^/api/schedules$~', CatalogoController::class, 'crearHorario', false],
    ['GET', '~^/api/patients$~', UsuarioController::class, 'pacientes', false],
    ['POST', '~^/api/patients$~', UsuarioController::class, 'crearPaciente', false],
    ['GET', '~^/api/users$~', UsuarioController::class, 'usuarios', false],
    ['POST', '~^/api/users$~', UsuarioController::class, 'crearUsuario', false],
    ['GET', '~^/api/appointments$~', CitaController::class, 'index', false],
    ['POST', '~^/api/appointments$~', CitaController::class, 'agendar', false],
    ['PATCH', '~^/api/appointments/(\d+)/status$~', CitaController::class, 'cambiarEstado', false],
    ['GET', '~^/api/records$~', HistoriaClinicaController::class, 'index', false],
    ['POST', '~^/api/records$~', HistoriaClinicaController::class, 'registrar', false],
    ['GET', '~^/api/invoices$~', FacturaController::class, 'index', false],
    ['POST', '~^/api/invoices$~', FacturaController::class, 'emitir', false],
    ['PATCH', '~^/api/invoices/(\d+)/pay$~', FacturaController::class, 'pagar', false],
    ['GET', '~^/api/notifications$~', NotificacionController::class, 'index', false],
    ['PATCH', '~^/api/notifications/(\d+)/read$~', NotificacionController::class, 'marcarLeida', false],

];
