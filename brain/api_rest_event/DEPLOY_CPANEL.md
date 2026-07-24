# Guía: desplegar ApiRestEvent (Laravel 12) en hosting compartido con cPanel

Proyecto: Laravel 12, PHP ^8.2, MySQL, Vite/Tailwind, colas vía `database`,
job `SendWhatsappMessageJob`. Laravel ya trae `public/.htaccess` por defecto.

## 0. Antes de nada, confirma con el hosting

- PHP **8.2 o superior**, con extensiones: `mbstring`, `openssl`, `pdo_mysql`,
  `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`. Se elige
  en cPanel → **MultiPHP Manager**.
- Acceso a **SSH** o al menos **Terminal** dentro de cPanel (facilita todo
  enormemente; sin esto, algunos pasos requieren workarounds).
- Composer disponible por SSH, o instalas dependencias localmente y subes
  `vendor/` completo (ver paso 1).
- No cuentes con Node.js en el servidor — el build de Vite se hace **en tu
  máquina**.

## 1. Preparar el proyecto localmente (antes de subir)

```bash
cd ApiRestEvent
composer install --no-dev --optimize-autoloader   # sin fakerphp/faker ni phpunit
npm install && npm run build                       # genera public/build/
```

`--no-dev` es justo lo que causó el error de `fake()` que se dio antes en
local — pero ahí era porque después se corrían *seeders*. En producción real
sí quieres `--no-dev` (sin Faker, sin PHPUnit) porque no se siembran datos de
prueba ahí.

Copia `.env` a `.env.production` y ajusta:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
DB_HOST=localhost          # casi siempre "localhost" en cPanel, no 127.0.0.1
DB_DATABASE=cpaneluser_event
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=algo-fuerte
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

No subas el `.env` local con `APP_DEBUG=true` — expondría stack traces con
rutas del servidor si algo falla.

## 2. Resolver el problema del document root

Laravel sirve desde `public/`, pero cPanel apunta el dominio a
`public_html/`. Dos opciones:

**Opción A — recomendada: subdominio/dominio con document root
personalizado**

Si el plan lo permite, en cPanel → **Domains** puedes crear el
dominio/subdominio apuntando directo a una carpeta `public/` dentro del
proyecto. Estructura:

```
/home/usuario/apirestevent/          <- todo el proyecto Laravel (fuera de public_html)
/home/usuario/apirestevent/public/   <- esta carpeta es el document root del dominio
```

**Opción B — si no se puede cambiar el document root**

Sube todo el proyecto a una carpeta fuera de `public_html` (ej.
`/home/usuario/apirestevent`), y dentro de `public_html` deja solo el
contenido de `public/`, editando `index.php`:

```php
require __DIR__.'/../apirestevent/vendor/autoload.php';
$app = require_once __DIR__.'/../apirestevent/bootstrap/app.php';
```

Las rutas `.env` / `storage_path()` no cambian, Laravel las resuelve
relativas al proyecto real. Esta opción es más frágil ante actualizaciones —
priorizar la A si el plan lo permite.

⚠️ Nunca dejar el repo completo (con `.env`, `app/`, `database/`)
directamente en `public_html` sin más — cualquiera podría descargar el
`.env` con las credenciales de la BD.

## 3. Subir los archivos

- Vía **Git** si cPanel tiene "Git Version Control" (ideal: `git clone`
  directo en el servidor, luego solo `git pull` en cada deploy).
- Si no, subir un `.zip` con todo (incluido `vendor/` y `public/build/`, ya
  generados en el paso 1) por **File Manager** y descomprimirlo ahí — evita
  subir miles de archivos uno por uno por FTP.
- Excluir `.git/`, `node_modules/`, `tests/` del zip para aligerar.

## 4. Base de datos

En cPanel → **MySQL Databases**: crear la BD y el usuario, asignarle todos
los privilegios. cPanel antepone el prefijo de la cuenta
(`usuario_nombrebd`), copiarlo tal cual al `.env`.

## 5. Migraciones y configuración inicial

Con SSH/Terminal:

```bash
cd /home/usuario/apirestevent
cp .env.production .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # solo si se quieren los catálogos (países, tipos_evento, etc.) — opcional en prod real
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`--force` es obligatorio porque `APP_ENV=production` bloquea migraciones sin
él, como salvaguarda.

**Sin SSH**: la mayoría de cPanel modernos traen un Terminal web (cPanel →
Advanced → Terminal) que corre estos mismos comandos. Si de verdad no hay
ninguna consola, el último recurso es crear una ruta temporal protegida por
token que ejecute `Artisan::call('migrate', ['--force' => true])`,
visitarla una vez y borrarla inmediatamente después — no dejarla en el
código.

## 6. Permisos

```bash
chmod -R 775 storage bootstrap/cache
```

Laravel necesita escribir en `storage/logs`, `storage/framework/*` y
`bootstrap/cache`. Si el hosting usa un usuario distinto para PHP-FPM, puede
requerir `chown` — preguntar a soporte si aparecen errores 500 al escribir
logs.

## 7. Las colas (`SendWhatsappMessageJob`)

`QUEUE_CONNECTION=database` significa que los jobs quedan encolados en una
tabla, pero **nadie los procesa** a menos que algo corra `queue:work`. En
shared hosting no se puede dejar un proceso en background corriendo
indefinidamente (el hosting lo mata). La solución estándar:

En cPanel → **Cron Jobs**, cada minuto:

```
* * * * * cd /home/usuario/apirestevent && php artisan schedule:run >> /dev/null 2>&1
```

Y en `routes/console.php` del proyecto agregar:

```php
Schedule::command('queue:work --stop-when-empty --tries=1')->everyMinute();
```

Esto procesa lo que haya en cola y termina el proceso
(`--stop-when-empty`), en vez de quedarse escuchando indefinidamente como
hace `dev` en local.

## 8. Solo tienes File Manager (sin SSH ni Terminal)

Si tu plan de hosting no da acceso a consola, todo el deploy se puede hacer
igual, ajustando los pasos que dependían de `artisan`.

### Error típico: `Access denied for user 'root'@'localhost' (using password: NO)`

Significa que el `.env` que quedó activo en el servidor todavía tiene los
valores de desarrollo (`DB_USERNAME=root`, `DB_PASSWORD=` vacío). En hosting
compartido nunca existe un usuario `root` para tu app — solo el usuario que
cPanel genera con el prefijo de tu cuenta.

**a) Crear la base y el usuario en cPanel**
- cPanel → **MySQL® Databases** → crea la base (queda como
  `cpaneluser_event`)
- **MySQL Users → Add New User** → crea usuario + contraseña
- **Add User to Database** → asígnalo con **ALL PRIVILEGES**

**b) Corregir el `.env` con File Manager**
- Carpeta del proyecto → clic derecho en `.env` → **Edit** (o "Code
  Editor")
- Cambiar:
  ```
  DB_HOST=localhost
  DB_DATABASE=cpaneluser_event
  DB_USERNAME=cpaneluser_usuario
  DB_PASSWORD=la-contraseña-real
  ```
- Guardar

**c) Borrar la caché de config (no hay `artisan config:clear` sin terminal)**
- Ir a `bootstrap/cache/` en File Manager
- Si hay `config.php`, `routes-v7.php`, `packages.php` o `services.php`
  ahí dentro, borrarlos (dejar `.gitignore` si existe). Si esa carpeta solo
  tiene `.gitignore`, no hay nada cacheado y el cambio del `.env` ya aplica
  solo.

### Crear las tablas sin `artisan migrate`

La forma más simple sin terminal: exportar la base local (ya migrada y
sembrada) e importarla directo por phpMyAdmin.

1. En XAMPP local: `http://localhost/phpmyadmin` → selecciona la base
   `event` → pestaña **Exportar** → Rápido / SQL → descarga el `.sql`
   - Para llevarte solo la estructura (sin los eventos de prueba
     sembrados), usar "Personalizado" y marcar solo **Estructura**, sin
     datos.
2. En cPanel → **phpMyAdmin** → selecciona `cpaneluser_event` (vacía) →
   pestaña **Importar** → subir el `.sql` → Continuar

Con eso quedan creadas todas las tablas (y opcionalmente los datos) sin
necesitar consola.

**Alternativa** si se prefiere correr las migraciones reales de Laravel:
crear una ruta temporal protegida por token que llame a
`Artisan::call('migrate', ['--force' => true])`, visitarla una vez desde el
navegador y **borrar el archivo inmediatamente después** — dejarlo expuesto
es un hueco de seguridad grave.

## 9. Checklist final antes de dar por cerrado el deploy

- [ ] `https://tu-dominio.com` carga sin error 500
- [ ] `.env` **no** es accesible públicamente (`https://tu-dominio.com/.env`
      debe dar 403/404)
- [ ] `APP_DEBUG=false` en producción
- [ ] `php artisan storage:link` corrido (si se usa `logo_url`/imágenes
      subidas por usuarios)
- [ ] Cron de `schedule:run` configurado si hay jobs/colas
- [ ] SSL activo (Let's Encrypt gratis suele estar en cPanel → SSL/TLS
      Status)
- [ ] Prueba real de un endpoint, ej. `GET /api/v1/event`, para confirmar
      que la API responde igual que en local
