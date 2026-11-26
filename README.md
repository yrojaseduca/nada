# Nido

Aplicación web Nido. Esta guía explica la preparación del servidor de producción y el flujo habitual para publicar cambios.

## Requisitos

- Git y acceso al repositorio `yrojaseduca/nido`.
- Docker Engine y Docker Compose Plugin en el servidor.
- Un Droplet Ubuntu LTS con al menos 2 GB de RAM recomendado.
- Dominio `nido.listocasa.es` apuntando a la IP pública del Droplet.

## 1. Preparar el servidor por primera vez

### 1.1. DNS y Droplet

En el DNS de DonDominio crea un registro A:

```text
Tipo: A    Nombre: nido    Valor: IP_DEL_DROPLET    TTL: automático o 3600
```

En DigitalOcean crea un Droplet Ubuntu LTS, selecciona una clave SSH y activa backups automáticos. Permite `22` (SSH), `80` (HTTP) y `443` (HTTPS) en el firewall. No expongas el puerto `3306` de MariaDB.

Comprueba la propagación DNS:

```bash
dig +short nido.listocasa.es
```

Conéctate al servidor (sustituye la IP si cambia):

```bash
ssh root@209.97.179.224
```

### 1.2. Instalar Docker en Ubuntu

Como `root` en el Droplet, instala Docker Engine y el plugin Compose siguiendo las instrucciones oficiales para Ubuntu. Después comprueba:

```bash
docker --version
docker compose version
```

### 1.3. Configurar lectura de GitHub desde el Droplet

La clave SSH personal de tu ordenador sirve para subir cambios. El servidor necesita una Deploy Key propia, de solo lectura.

En el Droplet:

```bash
mkdir -p /root/.ssh
chmod 700 /root/.ssh
ssh-keygen -t ed25519 -C "nido-digitalocean" -f /root/.ssh/nido_github
cat /root/.ssh/nido_github.pub
```

Añade la clave pública mostrada en GitHub, dentro de `yrojaseduca/nido`: `Settings → Deploy keys → Add deploy key`. No actives escritura.

Crea `/root/.ssh/config` en el servidor con este contenido:

```ssh
Host github-nido
    HostName github.com
    User git
    IdentityFile /root/.ssh/nido_github
    IdentitiesOnly yes
```

Protege la clave y prueba el acceso:

```bash
chmod 600 /root/.ssh/config /root/.ssh/nido_github
ssh -T git@github-nido
```

### 1.4. Clonar el proyecto

En el Droplet:

```bash
mkdir -p /opt
cd /opt
git clone git@github-nido:yrojaseduca/nido.git nido
cd /opt/nido
```

### 1.5. Configurar el entorno de producción

Desde `/opt/nido`, crea el archivo de entorno. No lo subas a Git:

```bash
cp .env.example .env
chmod 600 .env
nano .env
```

Configura como mínimo valores equivalentes a estos, con contraseñas largas y únicas:

```env
PROJECT_NAME=nido
HOST_PORT=80
APP_ENV=production
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=nido
DB_USERNAME=nido
DB_PASSWORD=CAMBIAR_POR_UN_SECRETO
DB_ROOT_PASSWORD=CAMBIAR_POR_OTRO_SECRETO
```

Añade credenciales de servicios opcionales solo en el `.env` del servidor. Las variables `VITE_*` que deban quedar embebidas en el frontend han de estar disponibles durante su compilación.

### 1.6. Primer arranque

En `/opt/nido`:

```bash
make prod-up
make status
```

Las migraciones pueden ejecutarse con `make db-migrate`. Verifica el endpoint de salud:

```bash
curl -f http://127.0.0.1/api/health
```

El compose de producción actual sirve HTTP. Configura un proxy TLS y un certificado válido para `nido.listocasa.es` antes de considerar HTTPS habilitado. El DNS por sí solo no configura HTTPS.

## 2. Flujo habitual: desarrollar y subir cambios

### 2.1. En tu ordenador

Actualiza `main` antes de empezar y trabaja en una rama para cada cambio:

```bash
git switch main
git pull --ff-only origin main
git switch -c nombre-del-cambio
```

Desarrolla y valida el cambio. Los targets disponibles incluyen `make test`, `make test-unit` y `make frontend-build`; algunos requieren que el entorno Docker esté levantado. Revisa el `Makefile` para el flujo completo de calidad (`make check`).

Antes de confirmar:

```bash
git diff --check
git status --short
git diff
```

No añadas `.env`, claves privadas, `vendor`, `node_modules` ni `dist`. Confirma y publica la rama usando el remoto configurado para tu clave personal:

```bash
git add archivos-del-cambio
git commit -m "Describe el cambio"
git push -u origin nombre-del-cambio
```

Integra la rama en `main` mediante el flujo de revisión habitual del repositorio. No publiques directamente desde una rama sin integrar.

### 2.2. Publicar en el Droplet

Una vez que el cambio está integrado en `main`, entra al servidor y actualiza el checkout:

```bash
ssh root@209.97.179.224
cd /opt/nido
git status --short
git pull --ff-only origin main
```

Si `git status` muestra cambios locales, detente y consérvalos antes de actualizar; no los descartes con reset o checkout.

Antes de una migración importante, crea una copia de la base de datos (consulta la sección de copias de seguridad). Compila y levanta la versión nueva:

```bash
make prod-up
make status
```

Si el cambio añade una migración que no se ejecutó automáticamente, ejecuta el target existente:

```bash
make db-migrate
```

Comprueba salud y logs:

```bash
curl -f http://127.0.0.1/api/health
make logs
```

`make logs` queda siguiendo los logs; pulsa `Ctrl+C` para salir. Confirma también que la página carga desde `https://nido.listocasa.es` cuando TLS esté configurado.

## 3. Copias de seguridad de MariaDB

Antes de cambios de esquema importantes, crea una copia desde `/opt/nido` en el Droplet. El comando carga las variables de `.env` para pasar credenciales al cliente:

```bash
set -a
. ./.env
set +a
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml exec -T mariadb \
    mariadb-dump -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
    > "backup-$(date +%Y%m%d-%H%M%S).sql"
```

Descarga las copias fuera del Droplet y prueba periódicamente su restauración. Protege los archivos de backup porque contienen datos de producción.

## 4. Comandos útiles

Los targets válidos se definen en el `Makefile`:

- `make status`: estado de contenedores.
- `make logs`: logs en vivo.
- `make prod-up`: compila frontend y levanta producción.
- `make prod-build`: recompila frontend y reinicia Nginx; úsalo solo si el cambio no requiere reconstruir/recrear los demás servicios.
- `make db-migrate`: ejecuta migraciones.
- `make test`, `make test-unit`, `make test-docker`: suites de pruebas.
- `make frontend-build`: compila/valida frontend dentro del entorno Docker.

No guardes secretos del servidor en el repositorio. El archivo `.env` de producción pertenece únicamente al Droplet.
