# SaludCita — Alta de administradores y acceso con Google

## Estado del módulo
El código de Google está preparado en la rama de revisión; NO está activado en Render ni en main hasta pasar las pruebas y configurar credenciales en Google Cloud.

## Administradores: dos caminos
**Opción recomendada, incluida en V6:** acceder como Administrador en SaludCita → Usuarios → Crear usuario → rol Administrador. Guardar una contraseña robusta. No es necesario reiniciar PostgreSQL.

**Con comando en Docker local:** desde la raíz del proyecto, ejecutar:

```powershell
docker compose up -d --build
docker compose exec app php scripts/install.php
docker compose exec -it app php scripts/crear_admin.php
```

El script solicita nombre, correo y contraseña de al menos 12 caracteres de manera interactiva, oculta la contraseña en pantalla, la almacena con password_hash y rechaza correos existentes. Este comando administra la base de Docker local, NO la de Render.

En Render Free se utiliza la interfaz Administrador → Usuarios. No modificar la tabla users con SQL directo para asignar roles; no elevar automáticamente administradores por correo ni usar contraseñas de demostración.

## Autenticación Google: arquitectura y seguridad
- PHP ejecuta OpenID Connect Authorization Code + PKCE, state, nonce y sesión HTTP-only.
- El backend intercambia el código por un ID Token, valida su firma criptográfica RSA con certificados de Google, iss, aud, exp, nonce y email_verified.
- Las cuentas existentes se vinculan explícitamente mediante el botón **Vincular Google** estando logueado con su contraseña.
- Solo se permite vincular un Google cuyo correo verificado coincida con la cuenta de SaludCita; un Google no puede vincularse a varias cuentas.
- **Google no crea usuarios, médicos, pacientes ni administradores automáticamente**. Para nuevos pacientes, usar Crear cuenta de paciente y vincular después.
- Login mediante el botón **Continuar con Google** solamente si la identidad Google ya está vinculada.
- La tabla oauth_identities guarda el identificador estable sub de Google, no tokens de sesión ni contraseñas.
- El usuario conserva su rol asignado en SaludCita; nunca se toma un rol desde Google.
- Si Google OAuth no está configurado, los botones no se muestran y las cuentas con contraseña funcionan igual.

## Configurar Google Cloud
1. Entrar en https://console.cloud.google.com/ y crear o seleccionar un proyecto académico.
2. Configurar la pantalla de consentimiento en **Google Auth Platform**, con nombre de aplicación, email de soporte y audiencia. Para modo Testing, añadir las cuentas de prueba autorizadas.
3. Crear un **OAuth client ID** de tipo **Web application**.
4. En Authorized redirect URIs, registrar exactamente:
   - Nube: https://saludcita-web.onrender.com/api/auth/google/callback
   - Local (si se usará también Docker): http://localhost:8080/api/auth/google/callback
5. Copiar Client ID y Client Secret exclusivamente a las variables privadas de Render o .env local. Nunca publicarlas en GitHub.
6. En **Render → saludcita-web → Environment** crear:
   - GOOGLE_OAUTH_CLIENT_ID = Client ID del proyecto Google
   - GOOGLE_OAUTH_CLIENT_SECRET = Client Secret, mantener oculto
   - GOOGLE_OAUTH_REDIRECT_URI = https://saludcita-web.onrender.com/api/auth/google/callback
7. Para Docker local, añadir los tres valores a un archivo .env privado (las claves ya aparecen en docker-compose.yml), ejecutar docker compose up -d --build y revisar la página.
8. Ejecutar migraciones: en Docker **docker compose exec app php scripts/install.php**. En Render, init_production.php aplica migraciones durante el despliegue.

## Uso de Google
1. Iniciar sesión de forma tradicional como Paciente, Médico, Recepción o Administrador.
2. Presionar **Vincular Google** y seleccionar **esa misma cuenta de correo** en Google.
3. Cerrar sesión; elegir el perfil correspondiente; presionar **Continuar con Google**.
4. El perfil y los permisos permanecen en la base PostgreSQL de SaludCita.

## Casos de prueba manuales que aún deben ejecutarse
- Sin variables Google: login por contraseña funciona y botón no aparece.
- Con variables Google: botón aparece; consentimiento funciona en HTTPS y callback retorna al rol.
- Vínculo con correo distinto: rechazado.
- Intentar Google sin vínculo previo: rechazado.
- Médico intenta entrar como Administrador: rechazado.
- Cuenta desactivada en SaludCita: login Google rechazado.
- Vincular Google a dos usuarios distintos: rechazado.
- Caducidad o manipulación de state, nonce y tokens: rechazados.
- Alta de administrador con email existente: rechazado.
- Confirmar migraciones y bitácora en PostgreSQL local y Render.

Los ensayos integrados con Google real y PostgreSQL aún están pendientes. No entregar cuentas reales de pacientes; usar datos ficticios.
