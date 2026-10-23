# SaludCita V5.1 — Corrección para eliminar y reactivar usuarios

## Problema solucionado

Al pulsar **Eliminar** desde `/administrador/usuarios`, PostgreSQL devolvía `SQLSTATE[22P02] invalid input syntax for type boolean: ""`. El servicio `ServicioUsuarios` utilizaba `execute([$active, $targetId])` y, para `false`, PDO enviaba una cadena vacía al campo booleano `users.active`.

## Solución

`app/Services/ServicioUsuarios.php` vincula los parámetros con `PDO::PARAM_BOOL` y `PDO::PARAM_INT`. Se amplió `tests/seguridad.php` para comprobar el tipo de los parámetros en la desactivación y reactivación.

## Instalar sin perder datos

1. Conserva una copia de seguridad de tu carpeta actual.
2. Del ZIP de **solo archivos corregidos**, copia `app/Services/ServicioUsuarios.php` y, opcionalmente, `tests/seguridad.php` a las mismas rutas de tu proyecto; sobrescribe los archivos previos.
3. En el `docker-compose.yml` de la V5 el proyecto se monta mediante `.:/var/www/html`, por lo que el código PHP se actualiza al guardarlo; no necesitas repetir `install.php` ni modificar la base de datos.
4. Abre `/administrador/usuarios`, recarga con `Ctrl+F5` y prueba eliminar un usuario de demostración (que no sea tu administrador).
5. Revisa la bitácora:

```powershell
docker compose exec app php scripts/audit.php 20
```

Si sigue apareciendo el error de booleano, revisa que editaste el proyecto en la carpeta usada por Docker con `docker compose exec app grep -n 'PARAM_BOOL' /var/www/html/app/Services/ServicioUsuarios.php`.

**No ejecutes `docker compose down -v` ni `scripts/install.php` para aplicar este parche.**

Nota: la aplicación desactiva usuarios (baja lógica), no los elimina físicamente. Esto conserva las citas y los registros clínicos/financieros.
