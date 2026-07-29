#!/bin/bash
#
# cron-schedule-run.sh
#
# Wrapper que cron llama cada minuto en vez de correr `artisan schedule:run`
# directo. Antes que nada deja un timestamp en cron-heartbeat.log — eso
# prueba que el cron del sistema SÍ está disparando cada minuto, algo que
# storage/logs/scheduler.log no puede probar por sí solo (ese archivo recién
# se llena cuando una tarea daily()/cron() de Laravel decide que le toca
# correr, no cada vez que cron invoca schedule:run).
#
# Reemplaza la línea actual del crontab (cPanel > Cron Jobs):
#
#   ANTES:
#   * * * * * /usr/local/bin/ea-php82 /home/inscrito/api.inscrito.net/artisan schedule:run >>/dev/null 2>&1
#
#   DESPUÉS:
#   * * * * * /bin/bash /home/inscrito/api.inscrito.net/cron-schedule-run.sh
#
# No hace falta `chmod +x` ni acceso a terminal para activarlo: al llamarlo
# como `/bin/bash script.sh` (en vez de `./script.sh`) alcanza con que el
# archivo exista y sea legible, que es lo que deja cualquier subida por FTP/
# File Manager.

APP_DIR="/home/inscrito/api.inscrito.net"
PHP_BIN="/usr/local/bin/ea-php82"

HEARTBEAT_LOG="$APP_DIR/storage/logs/cron-heartbeat.log"
CRON_RUN_LOG="$APP_DIR/storage/logs/cron-run.log"

# Prueba de que cron disparó el script — independiente de si Laravel tenía
# algo que hacer o no en este minuto puntual.
echo "$(date '+%Y-%m-%d %H:%M:%S') cron disparó el script" >> "$HEARTBEAT_LOG"

# stdout/stderr de schedule:run en sí (no de los comandos individuales, esos
# van a scheduler.log vía appendOutputTo en routes/console.php) — captura acá
# errores catastróficos de arranque de Laravel (ej. .env roto, autoload
# faltante) que antes se perdían en /dev/null.
"$PHP_BIN" "$APP_DIR/artisan" schedule:run >> "$CRON_RUN_LOG" 2>&1

# Nota: ninguno de estos 3 logs (cron-heartbeat.log, cron-run.log,
# scheduler.log) rota automáticamente — van a crecer indefinidamente.
# cron-heartbeat.log suma ~1 línea/minuto (~500KB/año, no urgente). Si en
# algún momento se vuelve molesto, se puede truncar a mano por File Manager.
