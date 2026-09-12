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

## 2. Probar en local con Docker (recomendado para desarrollo)

Requisitos: Docker y Docker Compose.

```bash
docker compose up --build
```

Esto levanta dos contenedores:

- **app** — PHP 8.2 + Apache con `pdo_mysql`, sirviendo el proyecto en
  <http://localhost:8080>
- **db** — MySQL 8.0 (accesible en `localhost:3307` si quieres conectarte con un
  cliente externo)

La primera vez que arranca, MySQL importa automáticamente, en orden,
`database/schema.sql`, `database/seed_expedientes.sql` y
`database/seed_usuarios.sql`, así que ya tienes los 18 expedientes de prueba y los
usuarios listos.

El contenedor `app` recibe la configuración de base de datos por variables de
entorno (`DB_HOST=db`, `DB_NAME=cvb_sistema`, `DB_USER=cvb`, `DB_PASS=cvb`), que
`config/database.php` ya sabe leer — no hace falta editar ningún archivo.

Comandos útiles:

```bash
docker compose up -d          # en segundo plano
docker compose logs -f app    # ver logs
docker compose down           # detener
docker compose down -v        # detener y BORRAR la base de datos (re-importa al volver a subir)
```

El código está montado como volumen, así que cualquier cambio en los archivos PHP
se refleja al instante sin reconstruir. El contenedor `app` además manda
`Cache-Control: no-store` para `.html/.js/.css` (ver `docker/dev-no-cache.conf`),
así que el navegador siempre sirve la última versión.

Si cambiaste el `Dockerfile` o el `.conf`, reconstruye:

```bash
docker compose up -d --build
```

### Migraciones sobre una base ya creada

MySQL solo importa `database/*.sql` la **primera** vez (volumen vacío). Si ya
tienes datos y necesitas aplicar un cambio de esquema, corre el archivo de
migración a mano:

```bash
docker exec -i cvb_db mysql -uroot -proot cvb_sistema < database/migration_2026_09_notarias_contacto.sql
```

## 3. Instalación en tu servidor

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

Si ya tenías el sistema instalado de una versión anterior, aplica la migración de
datos de contacto de notarías:
```
mysql -u TU_USUARIO -p cvb_sistema < database/migration_2026_09_notarias_contacto.sql
```

## 4. Credenciales de prueba (cámbialas antes de producción)

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

Para cambiar una contraseña puedes usar el Admin Portal → **Administración** →
*Editar* en la notaría → campo *Nueva contraseña*. (Alternativamente, generar el
hash con `password_hash('tu_nueva_password', PASSWORD_BCRYPT)` y actualizar la
columna `password` en `usuarios`.)

## 4.b. Administración de notarías (Admin Portal → Administración)

Desde esa pantalla el admin puede:

- **Crear** una notaría con nombre (obligatorio) y, opcionalmente, correo,
  teléfono y su usuario + contraseña de acceso al Portal Cliente (ambos juntos).
- **Editar** nombre, correo, teléfono, usuario de acceso y contraseña.
- **Activar / desactivar** (baja lógica): una notaría inactiva no aparece al crear
  expedientes pero conserva su historial.
- **Eliminar**: solo si no tiene expedientes ni usuarios ligados. Si los tiene, la
  API responde 409 y la interfaz ofrece *desactivar* en su lugar.

Endpoint: `api/notarias.php` (`GET` / `GET ?todas=1` / `POST` / `PUT ?id=` / `DELETE ?id=`).

## 5. Regla de negocio clave (aislamiento por notaría)

Un usuario con `rol = 'cliente'` **solo** puede ver los expedientes cuyo `notaria_id`
coincide con el de su propio usuario. Esto se aplica en `api/expedientes.php`
(`listarExpedientes` y `obtenerExpediente`) y no depende del frontend — aunque alguien
manipule el HTML, la API sigue filtrando por sesión en el servidor.

## 6. Cálculo de etapas y estatus (idéntico al Excel de seguimiento)

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

## 7. Datos de prueba

`database/seed_expedientes.sql` carga los 18 expedientes reales que venían en el
Excel de seguimiento (`Dashboard_Seguimiento_Expedientes.xlsx`), con sus fechas,
valores y notarías originales, para que puedas probar el sistema con datos reales
desde el primer momento.

## 8. Próximos pasos sugeridos

- Añadir recuperación de contraseña.
- Agregar geocodificación automática (dirección → lat/lng) al guardar un expediente,
  para que el mapa del detalle sea más preciso (hoy usa un iframe de Google Maps por
  dirección de texto, sin necesidad de API key).
- Exportar expediente a PDF real (hoy el botón "Exportar" usa la impresión del navegador).
- Gestión de usuarios admin (hoy `administracion.html` gestiona notarías y el
  usuario de acceso de cada una, pero no los usuarios con `rol='admin'`).
# cvb_system
