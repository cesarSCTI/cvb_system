# CVB — Sistema de Seguimiento de Expedientes

Sistema completo (backend PHP puro + MySQL, frontend HTML/JS con Fetch) para dar
seguimiento a expedientes de avalúo entre CVB, notarías y catastro. Basado en los
mockups "Look & Feel" (portal Cliente y portal Administrador) y en la lógica de
negocio real extraída del Excel de seguimiento.

## 1. Estructura del proyecto

```
cvb_sistema/
├── admin/              → Portal administrador (login, dashboard, CRUD, detalle)
├── cliente/             → Portal cliente / notarías (login, dashboard, detalle - solo lectura)
├── api/                 → Endpoints PHP (JSON) consumidos por el frontend vía fetch
├── config/database.php  → Configuración de conexión a MySQL
├── includes/            → db.php (PDO), auth.php (sesión), functions.php (reglas de negocio)
├── assets/              → CSS y JS compartidos
├── database/            → schema.sql y datos de prueba (seed)
└── index.php            → Selector de portal / redirección según sesión
```

## 2. Instalación en tu servidor

1. Sube toda la carpeta `cvb_sistema/` a tu hosting (por ejemplo `public_html/cvb/`).
2. Crea la base de datos e importa el esquema, en este orden:
   ```
   mysql -u TU_USUARIO -p < database/schema.sql
   mysql -u TU_USUARIO -p cvb_sistema < database/seed_expedientes.sql
   mysql -u TU_USUARIO -p cvb_sistema < database/seed_usuarios.sql
   ```
   (o impórtalos en ese orden desde phpMyAdmin).
3. Edita `config/database.php` con los datos reales de tu hosting:
   ```php
   'host'   => 'localhost',
   'dbname' => 'cvb_sistema',
   'user'   => 'tu_usuario_mysql',
   'pass'   => 'tu_password_mysql',
   ```
   También puedes definirlos como variables de entorno (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)
   si tu hosting lo permite.
4. Asegúrate de que tu servidor tenga PHP 8+ con la extensión `pdo_mysql` habilitada.
5. Entra a `https://tu-dominio.com/cvb/` — te dará a elegir entre Portal Cliente y Admin Portal.

## 3. Credenciales de prueba (cámbialas antes de producción)

**Admin Portal** (`admin/login.html`)
- Correo: `admin@cvb.mx`
- Contraseña: `admin2026`

**Portal Cliente** (`cliente/login.html`) — un usuario por notaría, todos con la misma contraseña de prueba:
| Usuario    | Notaría       | Contraseña |
|------------|---------------|------------|
| notaria1   | Lic. Gema     | cvb2026    |
| notaria2   | Lic. Karla    | cvb2026    |
| notaria3   | Lic. Cristy   | cvb2026    |
| notaria4   | Lic. Denisse  | cvb2026    |

Para cambiar una contraseña, genera un nuevo hash con `password_hash('tu_nueva_password', PASSWORD_BCRYPT)`
en PHP y actualiza la columna `password` del usuario en la tabla `usuarios`.

## 4. Regla de negocio clave (aislamiento por notaría)

Un usuario con `rol = 'cliente'` **solo** puede ver los expedientes cuyo `notaria_id`
coincide con el de su propio usuario. Esto se aplica en `api/expedientes.php`
(`listarExpedientes` y `obtenerExpediente`) y no depende del frontend — aunque alguien
manipule el HTML, la API sigue filtrando por sesión en el servidor.

## 5. Cálculo de etapas y estatus (idéntico al Excel de seguimiento)

Todo vive en `includes/functions.php`:

- **Etapa 1**: días calendario entre *Fecha solicitud de valores* y *Fecha entrega de
  valores* (mínimo 1 día).
- **Etapa 2**: días hábiles entre *Fecha solicitud de ingreso* y *Fecha de ingreso a
  catastro*.
- **Etapa 3**: días hábiles entre *Fecha de ingreso a catastro* y *Fecha entrega a
  notaría*.
- **Etapa 4 (Total)**: Etapa 2 + Etapa 3.
- **Estatus consolidado** (en este orden): Entregado → estatus manual (Pagado/Detenido)
  → En Catastro → En Desarrollo → En Cotización.

Estos valores **no se guardan** como columnas fijas en la base de datos: se recalculan
en cada consulta a partir de las fechas, igual que las fórmulas del Excel. Esto evita
que queden desincronizados si se edita una fecha.

## 6. Datos de prueba

`database/seed_expedientes.sql` carga los 18 expedientes reales que venían en el
Excel de seguimiento (`Dashboard_Seguimiento_Expedientes.xlsx`), con sus fechas,
valores y notarías originales, para que puedas probar el sistema con datos reales
desde el primer momento.

## 7. Próximos pasos sugeridos

- Añadir recuperación de contraseña.
- Agregar geocodificación automática (dirección → lat/lng) al guardar un expediente,
  para que el mapa del detalle sea más preciso (hoy usa un iframe de Google Maps por
  dirección de texto, sin necesidad de API key).
- Exportar expediente a PDF real (hoy el botón "Exportar" usa la impresión del navegador).
- Endpoint para gestionar usuarios (altas/bajas) desde `administracion.html`.
# cvb_system
