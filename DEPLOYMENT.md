# Despliegue en producción

Guía para desplegar Nada en Ubuntu/Droplet usando Docker Compose, dominio y HTTPS con Let's Encrypt.

## Regla operativa

Los comandos habituales del proyecto deben ejecutarse mediante el `Makefile`. Esto mantiene el flujo de desarrollo, producción, migraciones, tests y build centralizado y consistente.

Comandos principales:

```bash
make status
make prod-up
make prod-build
make test
make test-unit
make test-docker
make frontend-build
make logs
```

Los comandos `docker compose` directos quedan reservados para diagnósticos, recuperación del servicio o cuando el `Makefile` todavía no cubra una operación concreta. Actualmente la ejecución de migraciones no tiene un target implementado en el `Makefile`, por lo que debe documentarse o añadirse antes de convertirla en un comando operativo estándar.

## 1. Instalar Docker

Ejecutar en el servidor como `root`:

```bash
apt update
apt install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
cat > /etc/apt/sources.list.d/docker.sources <<'EOF'
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: noble
Components: stable
Architectures: amd64
Signed-By: /etc/apt/keyrings/docker.asc
EOF
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker
docker --version
docker compose version
```

## 2. Configurar `/opt/nada/.env`

```dotenv
PROJECT_NAME=nada
HOST_PORT=80
APP_ENV=production
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=nada
DB_USERNAME=nada
DB_PASSWORD=CAMBIAR_POR_UNA_CLAVE_SEGURA
DB_ROOT_PASSWORD=CAMBIAR_POR_OTRA_CLAVE_SEGURA
SUPERADMIN_EMAIL=admin@listocasa.es
SUPERADMIN_PASSWORD=CAMBIAR_POR_UNA_CLAVE_SEGURA
```

No subir `.env` ni sus secretos al repositorio.

## 3. Levantar la aplicación

```bash
cd /opt/nada
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml up -d --build
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml ps
```

Esperar a que MariaDB esté saludable antes de migrar:

```bash
until docker inspect -f '{{.State.Health.Status}}' nada-mariadb-1 | grep -q healthy; do
  sleep 2
done
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml exec php php bin/migrate.php
```

Compilar el frontend:

```bash
docker compose run --rm node sh -c "npm install && npm run build"
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml restart nginx
curl -I http://127.0.0.1
```

## 4. DNS y firewall

Crear un registro `A` para `nada.listocasa.es` apuntando a la IP pública del servidor.

```bash
ufw allow 80/tcp
ufw allow 443/tcp
getent hosts nada.listocasa.es
curl -4 ifconfig.me
```

La IP de DNS debe coincidir con la IP pública.

## 5. Configurar HTTPS en el proyecto

Estos cambios se realizan localmente, se suben al repositorio y se descargan en el servidor. No se sube `/etc/letsencrypt`.

En `docker-compose.yaml`, dentro de `nginx`, añadir:

```yaml
ports:
  - "${HOST_PORT}:80"
  - "443:443"
volumes:
  - /etc/letsencrypt:/etc/letsencrypt:ro
```

En `docker/nginx/default.prod.conf` debe haber redirección HTTP y HTTPS:

```nginx
server {
    listen 80;
    server_name nada.listocasa.es;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name nada.listocasa.es;
    ssl_certificate /etc/letsencrypt/live/nada.listocasa.es/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/nada.listocasa.es/privkey.pem;
    root /var/www/frontend;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ ^/api {
        root /var/www/backend/public;
        include fastcgi_params;
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME /var/www/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param DOCUMENT_ROOT /var/www/backend/public;
    }
}
```

Subir y aplicar los cambios:

```bash
git add docker-compose.yaml docker/nginx/default.prod.conf
git commit -m "Configure HTTPS for production"
git push

# En el servidor
cd /opt/nada
git pull
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml up -d --force-recreate nginx
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml ps
```

Debe aparecer `0.0.0.0:443->443/tcp`.

## 6. Obtener el certificado

```bash
apt install -y certbot
cd /opt/nada
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml stop nginx
certbot certonly --standalone -d nada.listocasa.es --agree-tos --no-eff-email -m tu-correo@dominio.com
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml start nginx
```

Los certificados quedan en `/etc/letsencrypt/live/nada.listocasa.es/` y no deben entrar en Git.

## 7. Renovación automática

Como se usa `standalone`, Certbot detiene Nginx durante la renovación:

```bash
mkdir -p /etc/letsencrypt/renewal-hooks/{pre,post}
```

Crear estos scripts en el servidor:

`/etc/letsencrypt/renewal-hooks/pre/stop-nada-nginx.sh`

```bash
#!/bin/bash
cd /opt/nada
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml stop nginx
```

`/etc/letsencrypt/renewal-hooks/post/start-nada-nginx.sh`

```bash
#!/bin/bash
cd /opt/nada
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml start nginx
```

Permisos, timer y prueba:

```bash
chmod +x /etc/letsencrypt/renewal-hooks/pre/stop-nada-nginx.sh
chmod +x /etc/letsencrypt/renewal-hooks/post/start-nada-nginx.sh
systemctl enable --now certbot.timer
systemctl status certbot.timer
certbot renew --dry-run
```

La prueba debe terminar con `all simulated renewals succeeded`.

## 8. Verificación

```bash
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml ps
curl -I http://nada.listocasa.es
curl -I https://nada.listocasa.es
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml logs --tail=100 nginx
```

HTTP debe redirigir a HTTPS y HTTPS debe devolver `200 OK`.

