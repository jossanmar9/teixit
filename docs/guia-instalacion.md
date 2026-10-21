# Guía de instalación de Teixit

> ⚠ **Aviso de responsabilidad.** Teixit es software libre y se distribuye tal cual, sin ninguna garantía (licencia AGPL-3.0). El autor no se hace responsable de la pérdida de datos ni de ningún otro daño, directo o indirecto, que pueda derivarse del uso del programa, de un error, de una actualización o de una configuración incorrecta. Cada entidad es responsable de sus datos y de hacer copias de seguridad periódicas en un lugar externo (USB o nube).

## Requisitos

- **Windows** 10 u 11 de 64 bits, o **Linux** con PHP 8.1 o posterior.
- Unos 100 MB de disco.
- No hace falta Internet ni servidor. En Windows tampoco hace falta instalar PHP ni bases de datos: todo va dentro.
- Se puede tener instalado en el mismo ordenador que Compàs (el programa para sociedades musicales): cada uno tiene su carpeta, sus datos y su puerto.

Todas las versiones se descargan de la página de [descargas](https://github.com/jossanmar9/teixit/releases/latest).

## Windows

### Opción A: instalador (recomendada)

1. Descarga `Teixit-X.Y.Z-installador.exe`.
2. Ejecútalo. No pide permisos de administrador: se instala para el usuario actual en `%LOCALAPPDATA%\Programs\Teixit`.
3. Al terminar, abre **Teixit** desde el menú Inicio o el escritorio.

Para **actualizar**, ejecuta el instalador de la versión nueva con Teixit cerrado. Los datos y el archivo `teixit.ini` se conservan. Si lo **desinstalas**, la carpeta `data` no se borra.

### Opción B: portable (sin instalar)

1. Descarga `Teixit-X.Y.Z-portable.zip` y descomprímelo donde quieras (por ejemplo, `Documentos\Teixit` o un pendrive).
2. Abre `Teixit.exe`.

Para actualizar, descomprime la versión nueva y copia en ella la carpeta `data` de la antigua (con Teixit cerrado).

### Si Windows no te deja abrirlo

Teixit es software libre y todavía **no está firmado digitalmente** (un certificado de firma cuesta dinero cada año). Por eso Windows puede mostrar uno de estos avisos:

**«Windows protegió su PC» (SmartScreen).** Es solo un aviso. Pulsa **Más información** y después **Ejecutar de todas formas**. Solo hay que hacerlo la primera vez.

**«Una directiva de Control de aplicaciones bloqueó este archivo» (código 4551).** Es el **Control inteligente de aplicaciones** (Smart App Control), que llevan activado algunos ordenadores nuevos con Windows 11. Bloquea cualquier programa sin firmar, también la versión portable, y Windows no permite hacer una excepción para un solo programa. Las opciones son:

- **Desactivarlo:** *Seguridad de Windows → Control de aplicaciones y navegador → Configuración del Control inteligente de aplicaciones → Desactivado*. Con Windows actualizado (desde abril de 2026) se puede volver a activar después desde el mismo sitio. Decídelo tú o la persona que lleva los ordenadores de la entidad.
- **Usar otro ordenador** que no lo tenga activado, o la **versión para Linux**.

> Comprueba que has descargado Teixit de la página oficial de descargas ([github.com/jossanmar9/teixit/releases](https://github.com/jossanmar9/teixit/releases)). No lo abras si te lo han enviado por correo o desde otra web.

## Linux

### Debian, Ubuntu, Linux Mint y similares: paquete .deb (recomendado)

1. Descarga `teixit_X.Y.Z_all.deb`.
2. Abre un terminal en la carpeta donde lo has descargado y escribe:
   ```
   sudo apt install ./teixit_X.Y.Z_all.deb
   ```
   Instala Teixit y, si faltan, PHP y las extensiones que necesita. También se puede abrir con doble clic desde el gestor de archivos (Centro de software o GDebi).
3. Abre **Teixit** desde el menú de aplicaciones o escribiendo `teixit` en un terminal.

Para **actualizar**, instala el `.deb` de la versión nueva de la misma manera. Para **desinstalar**: `sudo apt remove teixit`. Los datos no se tocan nunca. El puerto y las demás opciones se cambian en `~/.config/teixit/teixit.ini` (créalo si no existe), con el mismo formato que el `teixit.ini` de Windows.

### Otras distribuciones: paquete .tar.gz

Hace falta PHP 8.1 o posterior con las extensiones `pdo_sqlite` y `mbstring` (y `xml` para importar archivos de Excel). En Debian, Ubuntu, Linux Mint y similares:

```
sudo apt install php-cli php-sqlite3 php-mbstring php-xml
```

En Fedora: `sudo dnf install php-cli php-pdo php-mbstring php-xml php-sodium`. En Arch: `sudo pacman -S php php-sqlite` (y activa `pdo_sqlite` en `/etc/php/php.ini`).

#### Instalar

1. Descarga `Teixit-X.Y.Z-linux.tar.gz` y descomprímelo.
2. Dentro de la carpeta `Teixit`, ejecuta:
   ```
   ./installa.sh
   ```
   Se instala para el usuario actual, sin `sudo`, y crea el acceso en el menú de aplicaciones y la orden `teixit`.
3. Abre **Teixit** desde el menú de aplicaciones o escribiendo `teixit` en un terminal.

También se puede usar sin instalar: ejecuta `./teixit.sh` desde la carpeta descomprimida.

Para **actualizar**, descomprime la versión nueva y vuelve a ejecutar `./installa.sh`. Para **desinstalar**, `~/.local/share/teixit/programa/desinstalla.sh`. En ninguno de los dos casos se tocan los datos.

### Cómo funciona en Linux

Teixit se abre en su ventana, hecha con WebKitGTK (el motor web de GNOME). El paquete .deb lo instala si hace falta; con el .tar.gz, instálalo con `sudo apt install python3-gi gir1.2-webkit2-4.1`. Al cerrar la ventana, hace una copia de seguridad si ha habido cambios y se para.

Si WebKitGTK no está, Teixit prueba Chromium, Google Chrome, Microsoft Edge o Brave en modo aplicación.

Si tampoco hay ninguno, se abre el navegador habitual y una ventana de terminal. **Mientras la ventana del terminal esté abierta, Teixit funciona.** Para cerrarlo, pulsa **Ctrl+C** o cierra la ventana.

Los datos se guardan en `~/.local/share/teixit/dades` (o en la carpeta que indique la variable `TEIXIT_DADES`).

## Cómo funciona

Al abrir Teixit, se abre **su ventana**, sin ningún navegador. En Windows se hace con el componente **WebView2**, que traen Windows 10 y 11 (es el que usan muchos programas para mostrar pantallas); en Linux, con WebKitGTK. Al cerrar la ventana, Teixit hace una copia de seguridad (si ha habido cambios) y se para. Las páginas de fuera de Teixit (por ejemplo, la web o GitHub) se abren en el navegador habitual.

En Windows, si falta WebView2, Teixit se abre con Microsoft Edge o Google Chrome en modo aplicación, con un perfil separado; mientras tanto, el icono de Teixit está en la bandeja del sistema, junto al reloj.

Si no hay nada de esto (o si se pone `finestra = 0` en `teixit.ini`), se abre el navegador habitual en `http://localhost:8750` y una ventana pequeña de Teixit. **Mientras la ventana pequeña esté abierta, Teixit funciona.**

Teixit solo se puede usar desde este ordenador: no es accesible desde la red ni desde Internet, y no envía ningún dato fuera (véase la [declaración de privacidad](https://github.com/jossanmar9/teixit/blob/main/PRIVACITAT.md#privacidad-de-teixit)).

La primera vez pide el tipo y el nombre de la entidad y crea la cuenta de administrador y, opcionalmente, la de administrativo/a. Después muestra un **código de recuperación**: imprímelo o apúntalo, porque es lo que permite poner una contraseña nueva si el administrador/a la olvida.

## Carpeta del programa (Windows)

```
Teixit\
├── Teixit.exe        lanzador
├── teixit.ini        configuración del lanzador
├── app\  src\        el programa (PHP, HTML, CSS, JavaScript)
├── php\              PHP portable
├── docs\             manuales y guías
└── data\             LOS DATOS: teixit.db, copias automáticas y registros
```

## Configuración (`teixit.ini`)

Ábrelo con el Bloc de notas (o cualquier editor de texto). Después de cambiarlo, cierra y vuelve a abrir Teixit.

- `port = 8750`: cámbialo si otro programa ya lo usa.
- `navegador = 1`: abre Teixit al arrancar.
- `finestra = 1`: abre Teixit en su ventana. Con `finestra = 0`, se abre en el navegador habitual y se muestra la ventana pequeña.

## Copias de seguridad

Teixit hace copias automáticas cada día y al cerrar el programa (si ha habido cambios), en la carpeta `copies` de los datos. **Configura una carpeta externa** (disco USB o carpeta en la nube) en **Copias de seguridad** para que cada copia se guarde también fuera del ordenador y, si quieres, cifrada con contraseña. Véase el manual de usuario.

## Solución de problemas

| Problema | Qué hacer |
|---|---|
| Windows dice que ha protegido el PC o que una directiva ha bloqueado el archivo | Véase el apartado «Si Windows no te deja abrirlo». |
| «El puerto 8750 ya está en uso» | Cambia el `port` en `teixit.ini`. |
| La ventana de Teixit no se abre | Haz clic en el icono de Teixit de la bandeja del sistema, o escribe `http://localhost:8750` en el navegador. |
| «El servidor se ha parado» | Mira `servidor.log` y `php-errors.log` en la carpeta de datos. |
| No arranca y dice que faltan archivos (Windows) | Vuelve a instalar o a descomprimir. El antivirus puede haber puesto en cuarentena `php.exe`. |
| Falta `VCRUNTIME140.dll` (Windows) | Instala el *Microsoft Visual C++ Redistributable* (x64) desde la web de Microsoft. |
| «No se ha encontrado PHP» o «faltan extensiones» (Linux) | Instala los paquetes que indica el mensaje. |

## Para desarrollar

Con PHP 8.1 o posterior (con `pdo_sqlite` y `mbstring`) instalado:

```
php -S 127.0.0.1:8750 -t app app/index.php
```

Los datos se guardan en `data/` (o en la carpeta que indique la variable de entorno `TEIXIT_DADES`).

Para publicar una versión, sube una etiqueta en minúscula (`v1.0.0`) y GitHub Actions construye el instalador y el zip para Windows y el paquete para Linux.
