# Entorno de desarrollo — Spring Boot (Raspberry Pi, 13/08/2026)

Documenta la configuración del servidor que se preparó como entorno de
desarrollo/pruebas para [[PLAN-SPRING-BOOT-APIRESTEVENT-13082026.md]] (versión
Java de `ApiRestEvent`). Es un servidor de **desarrollo**, no el VPS de
producción que describe ese plan (ver "Nota" al final).

## Datos del servidor

| Campo | Valor |
|---|---|
| Host | `192.168.0.12` (red local / home lab) |
| Hostname | `api` |
| Hardware | Raspberry Pi (arquitectura `aarch64`/ARM64) |
| SO | Debian GNU/Linux 13 (trixie) |
| Kernel | `6.18.39+rpt-rpi-2712` |
| Usuario de acceso | `gerd` (sudo con contraseña, no passwordless) |
| Acceso | SSH, puerto 22, autenticación por contraseña |
| Conectividad | **WiFi (`wlan0`), IP dinámica** — ver nota abajo |

**Recursos** (al momento de la instalación): 7.9GB RAM (6.9GB libres), 4
núcleos, 29GB disco (13GB libres, 55% usado).

## Software instalado (13/08/2026)

Todo instalado desde los repos estándar de Debian 13 (`apt`), sin repos de
terceros:

| Paquete | Versión | Comando de verificación |
|---|---|---|
| `openjdk-21-jdk` | OpenJDK 21.0.12 | `java -version` |
| `maven` | Apache Maven 3.9.9 | `mvn -version` |
| `default-mysql-client` | MariaDB client 15.2 | `mysql --version` |
| `docker-compose` | Docker Compose 2.26.1 (subcomando `docker compose`) | `docker compose version` |
| `docker-buildx` | 0.13.1 | `docker buildx version` |
| `docker.io` | 26.1.5+dfsg1 (ya venía instalado) | `docker --version` |
| `git` | 2.47.3 (ya venía instalado) | `git --version` |

**Cambio de configuración**: se agregó `gerd` al grupo `docker`
(`sudo usermod -aG docker gerd`) — antes cualquier comando `docker`
necesitaba `sudo`. Verificado que `docker ps` corre sin `sudo` en una sesión
nueva.

### ⚠️ Detalle de nombres de paquete (Debian, no el repo oficial de Docker)

El Docker instalado viene del repo propio de Debian (`docker.io`), **no**
del repo oficial `download.docker.com`. Por eso el plugin de compose se
llama `docker-compose` en `apt`, no `docker-compose-plugin` como en la
documentación oficial de Docker — ese nombre da `E: Unable to locate
package` en este servidor. Si se reinstala o se replica este setup en otra
máquina Debian/Raspberry Pi OS, usar `docker-compose` + `docker-buildx`.

## nginx — ya estaba instalado (corrección)

La revisión inicial (`nginx -v` por SSH) reportó "no instalado", pero era un
falso negativo: `nginx` vive en `/usr/sbin`, que no está en el `$PATH` de
una sesión SSH no interactiva — el binario invocado sin ruta completa no se
encuentra aunque exista. Confirmado con `dpkg -l` y `systemctl status`:

| Dato | Valor |
|---|---|
| Paquete | `nginx` 1.26.3-3+deb13u7 (repo Debian) |
| Servicio | `nginx.service`, **activo y corriendo** (ya estaba así antes de esta revisión, no lo arrancamos nosotros) |
| Binario | `/usr/sbin/nginx` (usar ruta completa o `sudo nginx -v` en sesiones no interactivas) |

Esto significa que la Fase 0 del plan (levantar el reverse proxy del corte
gradual) **ya tiene nginx disponible en este servidor** — no hace falta
instalarlo cuando se llegue a esa parte. Falta sí revisar/reemplazar la
configuración que tenga puesta hoy (no se tocó ni se inspeccionó su
`nginx.conf` en esta sesión) antes de usarla para el ruteo Spring Boot/
Laravel.

## Qué falta

- Nada de software base — Java, Maven, Docker, cliente MySQL y nginx cubren
  lo necesario para arrancar el esqueleto de la Fase 0 del plan (proyecto
  Spring Boot + `docker compose up` local + conexión a una MySQL de prueba)
  y, más adelante, el reverse proxy del corte gradual.

## Nota importante — IP dinámica por WiFi

Este servidor está conectado por **WiFi con IP asignada por DHCP**, no
Ethernet con IP fija. Para desarrollo esto no importa. Pero si en algún
momento se decide usar esta misma Pi (u otra igual) como el VPS real que
describe el plan — específicamente el paso donde se abre el firewall de la
MySQL de UAT a la IP fija del servidor — **hay que fijar la IP primero**
(reserva DHCP en el router, o configuración estática), porque una IP que
cambia rompe esa regla de firewall sin aviso.

**Why**: dejar registrado el estado exacto de este entorno (versiones,
usuario, particularidad del nombre de paquete) para que cualquiera que lo
retome — incluido variar de máquina — no tenga que re-descubrir el mismo
error de `docker-compose-plugin` ni adivinar por qué `docker` pedía `sudo`.

**How to apply**: si se prepara otra máquina Debian/Raspberry Pi OS para lo
mismo, es el mismo comando de instalación:
```
sudo apt-get update
sudo apt-get install -y openjdk-21-jdk maven default-mysql-client docker-compose docker-buildx
sudo usermod -aG docker <usuario>
```
(cerrar sesión y volver a entrar para que el grupo `docker` tome efecto).
