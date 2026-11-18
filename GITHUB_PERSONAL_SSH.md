# Configurar GitHub personal con SSH en WSL

## 1. Verificar la clave SSH

La clave personal existente es:

```text
~/.ssh/id_ed25519_github_personal
~/.ssh/id_ed25519_github_personal.pub
```

Comprobar que existe:

```bash
ls -la ~/.ssh
```

## 2. Añadir la clave al agente SSH

```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519_github_personal
```

Verificar que está cargada:

```bash
ssh-add -l
```

## 3. Configurar el alias de GitHub personal

Editar el archivo SSH:

```bash
nano ~/.ssh/config
```

Contenido recomendado:

```sshconfig
Host gitlab.epc-app.com
    HostName gitlab.epc-app.com
    User git
    IdentityFile ~/.ssh/id_ed25519
    IdentitiesOnly yes

Host github-personal
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_github_personal
    IdentitiesOnly yes
```

Guardar y aplicar permisos:

```bash
chmod 600 ~/.ssh/config
```

## 4. Añadir la clave pública a GitHub

Mostrar la clave pública:

```bash
cat ~/.ssh/id_ed25519_github_personal.pub
```

Copiar todo el contenido y añadirlo en GitHub:

1. Entrar en GitHub con la cuenta `yrojaseduca`.
2. Ir a `Settings`.
3. Entrar en `SSH and GPG keys`.
4. Pulsar `New SSH key`.
5. Poner un título, por ejemplo `PC-YROJAS WSL`.
6. Pegar la clave pública.
7. Guardar.

## 5. Probar la conexión

Como se utiliza el alias `github-personal`, la prueba debe ser:

```bash
ssh -T git@github-personal
```

El resultado esperado es similar a:

```text
Hi yrojaseduca! You've successfully authenticated, but GitHub does not provide shell access.
```

## 6. Configurar el proyecto

Entrar en el proyecto:

```bash
cd ~/casa/Nada
```

Configurar el remoto usando el alias personal:

```bash
git remote set-url origin git@github-personal:yrojaseduca/nada.git
```

Comprobarlo:

```bash
git remote -v
```

Debe aparecer:

```text
origin  git@github-personal:yrojaseduca/nada.git (fetch)
origin  git@github-personal:yrojaseduca/nada.git (push)
```

## 7. Crear el commit y subir el proyecto

```bash
git add -A
git commit -m "Initial commit"
git branch -M main
git push -u origin main
```

A partir de entonces, los siguientes cambios se suben con:

```bash
git add -A
git commit -m "Descripción de los cambios"
git push
```

## 8. Diagnóstico si vuelve a fallar

Comprobar qué clave está usando SSH:

```bash
ssh -vT git@github-personal
```

Comprobar el remoto:

```bash
git remote -v
```

Comprobar las claves cargadas:

```bash
ssh-add -l
```

Si la clave no está cargada:

```bash
ssh-add ~/.ssh/id_ed25519_github_personal
```

Importante: el remoto debe usar `git@github-personal:...`, no `git@github.com:...`, para que se aplique la clave personal configurada.
