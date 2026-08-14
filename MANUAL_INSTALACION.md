# Manual de instalacion - Sistema CID UPVM

## 1. Requisitos
- PHP 8.1 o superior
- MySQL 8 o MariaDB compatible
- Composer 2
- XAMPP o servidor Apache/Nginx con PHP

## 2. Configuracion inicial
1. Copia el proyecto en tu carpeta web. Ejemplo: `c:/xampp/htdocs/gestorcid`.
2. Copia `.env.example` como `.env`.
3. Ajusta los valores en `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=cidb
DB_USER=root
DB_PASS=
WS_CLIENT_URL=ws://127.0.0.1:8080
WS_BIND_HOST=0.0.0.0
WS_PORT=8080
```

## 3. Base de datos
1. Crea la base de datos:

```sql
CREATE DATABASE cidb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa el archivo `cidb.sql` en la base `cidb`.

Opcional por terminal:

```bash
mysql -u root -p cidb < cidb.sql
```

## 4. Dependencias websocket
1. Abre terminal en la raiz del proyecto.
2. Instala dependencias:

```bash
composer install
```

Esto instalara `cboden/ratchet` para el servidor websocket.

## 5. Arranque de servicios
1. Inicia Apache y MySQL (XAMPP).
2. Inicia el servidor websocket en otra terminal:

```bash
php websocket-server.php
```

3. Abre el sistema en navegador:

```text
http://localhost/gestorcid/
```

### 5.1 Autoarranque en Windows con XAMPP
1. Usa el archivo `websocket-start.bat` que viene en la raiz del proyecto.
2. Pruebalo con doble clic. Debe abrir una consola y mostrar que el websocket quedo activo.
3. Para que arranque automaticamente al iniciar Windows:
	- Presiona `Win + R`.
	- Escribe `shell:startup` y acepta.
	- Crea un acceso directo a `websocket-start.bat` dentro de esa carpeta.
4. Si prefieres que se ejecute aunque nadie abra sesion, usa el Programador de tareas de Windows:
	- Crea una tarea nueva.
	- Desencadenador: `Al iniciar sesion` o `Al iniciar el sistema`.
	- Accion: `Iniciar un programa`.
	- Programa: ruta completa a `websocket-start.bat`.
5. Verifica que `WS_CLIENT_URL` en `.env` siga apuntando a `ws://127.0.0.1:8080` o al puerto que uses.

Nota: Apache y MySQL pueden seguir administrandose desde XAMPP; el websocket corre por separado con PHP.

## 6. Flujo esperado
1. En `registro.php`, al registrar visita se guarda en DB.
2. Al mismo tiempo se emite un evento websocket `new_visit`.
3. En `gestor/index.php`, el dashboard escucha ese evento y se refresca automaticamente cuando llega una visita nueva.

## 7. Verificaciones rapidas
- Si no conecta a DB: revisa `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`.
- Si no hay eventos en tiempo real: verifica que `php websocket-server.php` siga ejecutandose y que `WS_CLIENT_URL` apunte al host/puerto correcto.
- Si la imagen institucional no aparece en registro: confirma que exista al menos un archivo de imagen dentro de la carpeta `img/`.

## 8. Produccion recomendada
- Ejecutar websocket con un supervisor (NSSM, systemd o PM2 segun sistema operativo).
- Usar `wss://` detras de proxy reverso HTTPS.
- Configurar respaldos de la base de datos.
