# Guía de Despliegue en Producción — Social Dashboard

> Esta guía está dirigida al equipo de IT que recibe el paquete de release
> y lo instala en el servidor de producción.
> **No se requiere consola, Node.js ni Composer.** Todo se realiza via
> panel de control de hosting (cPanel, Plesk o similar), FTP y phpMyAdmin.

---

## Requisitos del servidor

| Componente | Versión mínima |
|---|---|
| PHP | 8.4 |
| MariaDB / MySQL | 10.5 / 8.0 |
| Servidor web | Apache (con `mod_rewrite` habilitado) |
| Extensiones PHP requeridas | `pdo_mysql`, `zip`, `gd`, `xml`, `dom`, `xmlwriter`, `mbstring` |

> Si no sabe si estas extensiones están activas, consulte con su proveedor
> de hosting o verifícelas desde el panel de control en la sección PHP.

---

## Estructura del paquete de release

```
/
├── index.html          ← Frontend (archivos estáticos listos para servir)
├── assets/
├── .htaccess           ← Configuración del servidor web (incluido en el release)
├── backend/
│   ├── index.php
│   ├── .htaccess       ← Aquí se configuran las variables de entorno
│   ├── schema.sql      ← Estructura inicial de la base de datos
│   ├── vendor/         ← Dependencias PHP pre-instaladas (no modificar)
│   └── ...
└── DEPLOYMENT.md
```

---

## Paso 1 — Base de datos (via phpMyAdmin)

1. Acceda a **phpMyAdmin** desde el panel de control de su hosting.
2. Cree una nueva base de datos con el nombre `social_dashboard`
   y cotejamiento `utf8mb4_unicode_ci`.
3. Cree un nuevo usuario de base de datos con una contraseña segura
   y asígnele **todos los privilegios** sobre la base de datos `social_dashboard`.
   *(En cPanel esto se hace desde "Bases de datos MySQL" → "Agregar usuario a base de datos".)*
4. En phpMyAdmin, seleccione la base de datos `social_dashboard`,
   vaya a la pestaña **Importar** y cargue el archivo `backend/schema.sql`
   incluido en el release.

---

## Paso 2 — Backend PHP

### 2.1 — Subir archivos

Suba el contenido de la carpeta `backend/` del release a la carpeta
de su servidor web destinada a la API. Por ejemplo:
- `/public_html/backend/` (si el dashboard vive en la raíz del dominio)
- `/public_html/api/` (si se sirve como subdominio o subdirectorio)

Puede usar el **Administrador de Archivos** de su panel de control
o un cliente FTP (FileZilla, WinSCP, etc.).

> **Importante:** El archivo `backend/.htaccess` debe subirse también.
> Asegúrese de que su cliente FTP esté configurado para mostrar y subir
> archivos ocultos (los que comienzan con `.`).

### 2.2 — Configurar variables de entorno

Abra el archivo `backend/.htaccess` con el editor de texto del
Administrador de Archivos y añada estas líneas **al inicio del archivo**,
reemplazando los valores de ejemplo con los datos reales de su base de datos:

```apache
SetEnv DB_HOST      127.0.0.1
SetEnv DB_NAME      social_dashboard
SetEnv DB_USER      dashboard_user
SetEnv DB_PASSWORD  CONTRASEÑA_SEGURA
SetEnv UPLOAD_PASSWORD mogul360secret
```

Guarde el archivo.

> **Nota:** `UPLOAD_PASSWORD` es la clave que el usuario del dashboard
> debe ingresar para subir reportes Excel. Puede cambiarla por cualquier
> valor que prefiera.

---

## Paso 3 — Frontend

Suba **todos los archivos de la raíz del release** (excepto la carpeta
`backend/` y este archivo `DEPLOYMENT.md`) al directorio raíz de documentos
de su servidor web (por ejemplo `/public_html/`).

El archivo `.htaccess` de la raíz ya está incluido en el release.
Debe subirse junto con el resto de archivos.

---

## Paso 4 — Verificación

### 4.1 — API respondiendo

Abra su navegador y acceda a:

```
http://su-dominio.com/backend/api/report-metadata
```

Debe ver una respuesta en formato JSON: `{}` si no hay datos cargados aún,
o un objeto con información de mes/año si ya existen reportes.

### 4.2 — Frontend accesible

Abra `http://su-dominio.com` en el navegador. El dashboard debe cargar
mostrando los paneles vacíos, sin mensajes de error.

### 4.3 — Carga de datos (prueba funcional)

1. Haga clic en **"Cargar nuevo reporte semanal"** en el pie de página.
2. Ingrese la `UPLOAD_PASSWORD` que configuró en el Paso 2.
3. Suba un archivo Excel válido.
4. Verifique que los gráficos y métricas se actualicen correctamente.

### 4.4 — Descarga de reporte

Haga clic en el botón de descarga del encabezado y verifique que se
descargue un archivo `.docx` con los datos del reporte.

---

## Variables de entorno — referencia rápida

| Variable | Descripción | Ejemplo |
|---|---|---|
| `DB_HOST` | Host del servidor de base de datos | `127.0.0.1` |
| `DB_NAME` | Nombre de la base de datos | `social_dashboard` |
| `DB_USER` | Usuario de la base de datos | `dashboard_user` |
| `DB_PASSWORD` | Contraseña del usuario DB | `CONTRASEÑA_SEGURA` |
| `UPLOAD_PASSWORD` | Clave para autorizar la carga de Excel | `mogul360secret` |

---

## Problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| La API devuelve error 500 | Variables de entorno no configuradas | Revisar `backend/.htaccess` |
| La API devuelve error 404 | `mod_rewrite` no habilitado o `.htaccess` no subido | Verificar con el proveedor de hosting |
| No se puede subir Excel | `UPLOAD_PASSWORD` incorrecta | Verificar el valor en `backend/.htaccess` |
| El frontend muestra pantalla en blanco | `.htaccess` de la raíz no subido | Subir el archivo `.htaccess` de la raíz del release |
