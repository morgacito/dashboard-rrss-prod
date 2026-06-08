# Guía de Despliegue en Producción (Sin Docker) - Social Dashboard

Esta guía detalla los pasos para instalar, configurar y desplegar la aplicación **Social Dashboard (Campaña Mogul 360 V1)** en servidores web tradicionales utilizando una infraestructura basada en **PHP 8.4**, **MariaDB 10.5** (o superior) y un servidor web (como **Apache** o **Nginx**).

---

## 1. Requisitos del Sistema

Antes de iniciar el despliegue, asegúrese de tener configurados los siguientes componentes en el servidor:

### Servidor de Base de Datos
* **MariaDB 10.5+** o **MySQL 8.0+**
* Acceso de administrador para la creación de esquemas y usuarios.

### Servidor Web y Entorno PHP
* **Apache** con el módulo `mod_rewrite` habilitado (o Nginx).
* **PHP 8.4** o superior con las siguientes extensiones instaladas y habilitadas:
  * `pdo_mysql` (para la conexión a base de datos).
  * `zip` (requerida por PHPWord y PhpSpreadsheet).
  * `gd` (requerida por PhpSpreadsheet para la manipulación de imágenes/gráficos).
  * `xml` / `dom` / `xmlwriter` (requeridas por PHPWord).
  * `mbstring` (para manejo de codificaciones multibyte).
* **Composer** instalado globalmente en el sistema.

### Entorno de Compilación Frontend
* **Node.js 18+** y **pnpm** (recomendado por seguridad y velocidad) o **npm** (solo necesarios en la máquina de desarrollo o build server para compilar el frontend).

---

## 2. Configuración de la Base de Datos

1. Acceda a la consola de MariaDB/MySQL:
   ```bash
   mysql -u root -p
   ```

2. Cree la base de datos de producción y un usuario dedicado con privilegios adecuados:
   ```sql
   CREATE DATABASE social_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   
   CREATE USER 'dashboard_user'@'localhost' IDENTIFIED BY 'mi_contraseña_segura';
   GRANT ALL PRIVILEGES ON social_dashboard.* TO 'dashboard_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Importe la estructura inicial de tablas desde el archivo [schema.sql](file:///C:/dockerapps/Social%20Dashboard/backend/schema.sql) del repositorio:
   ```bash
   mysql -u dashboard_user -p social_dashboard < /ruta/al/proyecto/backend/schema.sql
   ```

---

## 3. Despliegue del Backend (PHP 8.4)

### Paso A: Copia de archivos e Instalación de dependencias
1. Copie el contenido del directorio `backend/` al directorio público de su servidor web (por ejemplo, `/var/www/html/api/` o directamente a la raíz de un subdominio, p. ej., `/var/www/html/backend/`).
2. Acceda al directorio del backend y ejecute Composer para instalar las dependencias de producción, omitiendo las de desarrollo y optimizando el cargador de clases:
   ```bash
   cd /var/www/html/backend
   composer install --no-dev --optimize-autoloader
   ```

### Paso B: Configuración de Variables de Entorno
La aplicación de PHP obtiene las variables de entorno mediante la función `getenv()`. 

#### Detalle de las Variables de Entorno a Configurar:

| Variable | Descripción | Valor Sugerido / Ejemplo |
| :--- | :--- | :--- |
| `DB_HOST` | Host o dirección IP del servidor de base de datos MariaDB. | `127.0.0.1` |
| `DB_NAME` | Nombre de la base de datos MariaDB creada para el dashboard. | `social_dashboard` |
| `DB_USER` | Usuario de la base de datos con privilegios adecuados. | `dashboard_user` |
| `DB_PASSWORD` | Contraseña correspondiente al usuario de la base de datos. | *Contraseña segura elegida* |
| `UPLOAD_PASSWORD` | Contraseña requerida en la cabecera `X-Upload-Password` para autorizar la carga de archivos Excel en `/api/upload`. | `mogul360secret` |

> [!TIP]
> Se sugiere configurar `UPLOAD_PASSWORD` con el valor `"mogul360secret"` para mantener la coherencia con la clave por defecto del sistema.

Hay dos formas recomendadas de definir estas variables en producción sin Docker:

#### Opción 1: A nivel del Servidor Web Apache (Recomendado por seguridad)
Defina las variables de entorno en el archivo de configuración del **VirtualHost** de Apache o en un archivo `.htaccess` en el directorio raíz del backend:

Si utiliza un **VirtualHost**, añada las siguientes directivas `SetEnv`:
```apache
<VirtualHost *:8000>
    DocumentRoot "/var/www/html/backend"
    ServerName api.socialdashboard.local

    SetEnv DB_HOST 127.0.0.1
    SetEnv DB_NAME social_dashboard
    SetEnv DB_USER dashboard_user
    SetEnv DB_PASSWORD mi_contraseña_segura
    SetEnv UPLOAD_PASSWORD mogul360secret

    <Directory "/var/www/html/backend">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Opción 2: Mediante el archivo `.htaccess` (Si no tiene acceso a la configuración global de Apache)
Añada las directivas `SetEnv` al inicio del archivo [backend/.htaccess](file:///C:/dockerapps/Social%20Dashboard/backend/.htaccess):
```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_NAME social_dashboard
SetEnv DB_USER dashboard_user
SetEnv DB_PASSWORD mi_contraseña_segura
SetEnv UPLOAD_PASSWORD mogul360secret

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### Paso C: Configuración de Permisos de Archivos
Asegúrese de que el servidor web (habitualmente el usuario `www-data` en Ubuntu/Debian o `apache` en RHEL/CentOS) tenga permisos de lectura y ejecución en los archivos PHP, y que los directorios temporales de carga del sistema operativo estén disponibles.
```bash
sudo chown -R www-data:www-data /var/www/html/backend
sudo find /var/www/html/backend -type d -exec chmod 755 {} \;
sudo find /var/www/html/backend -type f -exec chmod 644 {} \;
```

---

## 4. Despliegue del Frontend (React)

Dado que el frontend es una Single Page Application (SPA), la compilaremos para generar archivos estáticos HTML/JS/CSS de producción que pueden ser servidos de manera ultra-rápida por cualquier servidor web en los puertos estándar (80/443).

### Paso A: Compilación del Frontend
1. En su servidor de compilación o máquina local, prepare el archivo de entorno en el directorio `frontend/` (o en la raíz si allí reside el archivo de build del frontend).
2. Configure la variable con la URL del backend PHP de producción. Por ejemplo, en el archivo `.env.production`:
   ```env
   VITE_API_URL=http://api.socialdashboard.local:8000
   ```
3. Instale las dependencias de Node y compile la aplicación:
   ```bash
   cd /ruta/al/proyecto/frontend
   pnpm install
   pnpm run build
   ```
   Esto generará un directorio llamado `dist/` que contiene todos los archivos estáticos de producción compilados y optimizados.

### Paso B: Publicación en el Servidor Web
1. Copie el contenido del directorio `frontend/dist/` a la raíz de documentos de su servidor web principal de producción (por ejemplo, `/var/www/html/`).
2. Si utiliza **Apache**, configure un archivo `.htaccess` en el directorio del frontend para evitar errores de tipo "404 Not Found" al recargar páginas internas (debido al enrutamiento de React Router):
   ```apache
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteBase /
     RewriteRule ^index\.html$ - [L]
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule . /index.html [L]
   </IfModule>
   ```

---

## 5. Verificación del Despliegue

Una vez completado el despliegue de ambos componentes, realice las siguientes comprobaciones para verificar el correcto funcionamiento:

1. **Prueba de Conexión a la API**:
   Abra un navegador o ejecute un comando `curl` para verificar la respuesta del backend:
   ```bash
   curl -I http://api.socialdashboard.local:8000/api/report-metadata
   ```
   Debería recibir una respuesta HTTP `200 OK` con un cuerpo JSON conteniendo la información de mes y año o un JSON vacío `{}` en caso de que no existan metadatos aún en la base de datos.

2. **Acceso al Dashboard**:
   Ingrese a la URL donde se desplegó el frontend (ej. `http://socialdashboard.local`). Los paneles gráficos, tarjetas de KPI y la tabla principal deben cargarse vacíos si no hay datos.

3. **Carga de Datos (Excel)**:
   * Vaya al modal de carga haciendo clic en **"Cargar nuevo reporte semanal"** en el pie de página.
   * Introduzca la contraseña de carga (`UPLOAD_PASSWORD`) configurada en sus variables de entorno.
   * Suba un archivo Excel válido. El sistema debería notificar el éxito de la carga.
   * Verifique que los gráficos y métricas se pueblen de forma inmediata y consistente.

4. **Descarga del Reporte Word**:
   * Haga clic en el botón de descarga en el Header de la aplicación.
   * Verifique que se descargue un archivo `.docx` con el nombre correcto y que este se pueda abrir correctamente mostrando las tablas de la campaña.
