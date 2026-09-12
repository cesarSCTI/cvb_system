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

## 3. Instalación en tu servidor (cPanel)

No hay build ni dependencias de Node/Composer: es subir archivos y crear la base
de datos. Pasos con los nombres reales de cPanel:

### 3.1. Dónde subir el proyecto — decide esto primero

`assets/js/api.js` calcula solo la URL de la API a partir de dónde se cargó el
propio script, así que el sistema funciona **sin tocar código** sin importar
si lo instalas en la raíz de un dominio, en un subdominio, o en una subcarpeta:

- Dominio o subdominio dedicado (`https://cvb.tudominio.com/`) → sube el
  contenido de `cvb_sistema/` directo al `document root` que cPanel asigne a
  ese (sub)dominio.
- Subcarpeta de un dominio existente (`https://tudominio.com/cvb/`) → crea
  esa carpeta dentro de `public_html/` y sube el contenido ahí. También
  funciona, no hace falta editar `assets/js/api.js`.

En ambos casos, **no subas la carpeta del proyecto dentro de otra carpeta con
el mismo nombre repetido** (evita `public_html/cvb/cvb_sistema/...`): lo que
debe quedar directo bajo esa carpeta es `admin/`, `cliente/`, `api/`, `index.php`, etc.

### 3.2. Subir los archivos

- **Administrador de archivos de cPanel**: comprime el proyecto en `.zip` en tu
  máquina, súbelo con "Subir", y en cPanel usa "Extraer" sobre el `.zip` dentro
  de la carpeta destino.
- o **FTP/SFTP** con las credenciales que da cPanel ("Cuentas FTP" o SSH si tu
  plan lo incluye).
- No necesitas subir `docker/`, `Dockerfile`, `docker-compose.yml` ni `.git/`
  (son solo para desarrollo local) — puedes omitirlos al comprimir.

### 3.3. Crear la base de datos MySQL

En cPanel → **"MySQL® Databases"**:
1. Crea una base de datos (cPanel le pondrá el prefijo de tu cuenta, ej.
   `usuario_cvb`).
2. Crea un usuario MySQL con una contraseña fuerte (prefijo similar, ej.
   `usuario_cvbadmin`).
3. En "Add User to Database" dale privilegios **ALL PRIVILEGES** sobre esa base.
4. Anota los tres nombres completos con prefijo: base, usuario y contraseña —
   los vas a necesitar en el paso 3.5.

### 3.4. Importar el esquema y los datos de prueba

Entra a **phpMyAdmin** desde cPanel, selecciona tu base de datos (la que
creaste en 3.3) y usa la pestaña **"Importar"**, en este orden:

1. `database/schema_cpanel.sql` — variante de `schema.sql` sin `CREATE DATABASE`
   ni `USE` (en cPanel la base ya la creaste tú desde la interfaz; tu usuario
   MySQL normalmente no tiene permiso para crear bases nuevas por SQL).
2. `database/seed_expedientes.sql`
3. `database/seed_usuarios.sql`

Si más adelante actualizas el sistema y aparece un archivo
`database/migration_*.sql` nuevo, impórtalo también (una sola vez, en orden de fecha).

### 3.5. Configurar la conexión

La mayoría de los planes cPanel no exponen variables de entorno a PHP, así que
edita directamente `config/database.php` con los valores del paso 3.3:

```php
return [
    'host'    => getenv('DB_HOST') ?: 'localhost',
    'dbname'  => getenv('DB_NAME') ?: 'usuario_cvb',
    'user'    => getenv('DB_USER') ?: 'usuario_cvbadmin',
    'pass'    => getenv('DB_PASS') ?: 'la-contraseña-que-creaste',
    'charset' => 'utf8mb4',
];
```

(Los `getenv(...)` se dejan igual; simplemente casi nunca habrá esa variable
definida en cPanel y usará el valor fijo de la derecha.)

### 3.6. PHP y permisos

1. cPanel → **"Select PHP Version"** (o "MultiPHP Manager"): elige **PHP 8.0
   o superior** para ese dominio/carpeta.
2. En la misma pantalla, pestaña "Extensions": confirma que `pdo_mysql` esté
   marcada (casi siempre lo está por defecto).
3. No se requieren carpetas con permisos de escritura especiales: el sistema
   no sube archivos ni escribe logs propios.

### 3.7. Seguridad antes de dar la URL a nadie

- El repo incluye un `.htaccess` en la raíz que bloquea el acceso directo a
  `.sql`, `.git`, `.env`, `.md` y evita listar carpetas sin `index` — súbelo
  junto con el resto (si tu hosting usa Apache/LiteSpeed con `.htaccess`
  habilitado, que es lo normal en cPanel, se aplica solo).
- Cambia las contraseñas de prueba (ver sección 4) antes de compartir la URL:
  el admin puede cambiar la suya desde el propio sistema (menú de usuario →
  "Editar mi perfil"); las de notarías desde Administración → Editar.
- Activa HTTPS: cPanel → **"SSL/TLS Status"** → AutoSSL (gratis, Let's Encrypt)
  y luego **"Force HTTPS Redirect"** en el mismo dominio.

### 3.8. Verificar

Entra a `https://tu-dominio/` (o `https://tu-dominio/cvb/` si usaste subcarpeta):
debe mostrarte el selector Portal Cliente / Admin Portal. Prueba ambos logins.

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
