# Guia d'instal·lació de Teixit

> ⚠ **Avís de responsabilitat.** Teixit és programari lliure i es distribueix tal com és, sense cap garantia (llicència AGPL-3.0). L'autor no es fa responsable de la pèrdua de dades ni de cap altre dany, directe o indirecte, que puga derivar-se de l'ús del programa, d'una errada, d'una actualització o d'una configuració incorrecta. Cada entitat és responsable de les seues dades i de fer-ne còpies de seguretat periòdiques en un lloc extern (USB o núvol).

## Requisits

- **Windows** 10 o 11 de 64 bits, o **Linux** amb PHP 8.1 o posterior.
- Uns 100 MB de disc.
- No cal Internet ni servidor. A Windows tampoc no cal instal·lar PHP ni bases de dades: tot va dins.
- Es pot tindre instal·lat al mateix ordinador que Compàs (el programa per a societats musicals): cadascun té la seua carpeta, les seues dades i el seu port.

Totes les versions es baixen de la pàgina de [descàrregues](https://github.com/jossanmar9/teixit/releases/latest).

## Windows

### Opció A: instal·lador (recomanada)

1. Baixa `Teixit-X.Y.Z-installador.exe`.
2. Executa'l. No demana permisos d'administrador: s'instal·la per a l'usuari actual a `%LOCALAPPDATA%\Programs\Teixit`.
3. En acabar, obri **Teixit** des del menú Inici o l'escriptori.

Per a **actualitzar**, executa l'instal·lador de la versió nova amb Teixit tancat. Les dades i el fitxer `teixit.ini` es conserven. Si el **desinstal·les**, la carpeta `data` no s'esborra.

### Opció B: portable (sense instal·lar)

1. Baixa `Teixit-X.Y.Z-portable.zip` i descomprimeix-lo on vulgues (per exemple, `Documents\Teixit` o un llapis USB).
2. Obri `Teixit.exe`.

Per a actualitzar, descomprimeix la versió nova i copia-hi la carpeta `data` de l'antiga (amb Teixit tancat).

### Si Windows no et deixa obrir-lo

Teixit és programari lliure i encara **no està signat digitalment** (un certificat de signatura costa diners cada any). Per això Windows pot mostrar un d'estos avisos:

**«Windows ha protegit l'ordinador» (SmartScreen).** És només un avís. Prem **Més informació** i després **Executa igualment**. Només cal fer-ho la primera vegada.

**«Una directiva de Control d'aplicacions ha bloquejat aquest fitxer» (codi 4551).** És el **Control intel·ligent d'aplicacions** (Smart App Control), que porten activat alguns ordinadors nous amb Windows 11. Bloqueja qualsevol programa no signat, també la versió portable, i Windows no permet fer una excepció per a un sol programa. Les opcions són:

- **Desactivar-lo:** *Seguretat de Windows → Control d'aplicacions i del navegador → Configuració del Control intel·ligent d'aplicacions → Desactivat*. Amb Windows actualitzat (des de l'abril de 2026) es pot tornar a activar després des del mateix lloc. Decideix-ho tu o la persona que porta els ordinadors de l'entitat.
- **Utilitzar un altre ordinador** que no el tinga activat, o la **versió per a Linux**.

> Comprova que has baixat Teixit de la pàgina oficial de descàrregues ([github.com/jossanmar9/teixit/releases](https://github.com/jossanmar9/teixit/releases)). No l'obrigues si te l'han enviat per correu o des d'una altra web.

## Linux

### Debian, Ubuntu, Linux Mint i semblants: paquet .deb (recomanat)

1. Baixa `teixit_X.Y.Z_all.deb`.
2. Obri un terminal a la carpeta on l'has baixat i escriu:
   ```
   sudo apt install ./teixit_X.Y.Z_all.deb
   ```
   Instal·la Teixit i, si falten, PHP i les extensions que necessita. També es pot obrir amb doble clic des del gestor de fitxers (Centre de programari o GDebi).
3. Obri **Teixit** des del menú d'aplicacions o escrivint `teixit` en un terminal.

Per a **actualitzar**, instal·la el `.deb` de la versió nova de la mateixa manera. Per a **desinstal·lar**: `sudo apt remove teixit`. Les dades no es toquen mai. El port i les altres opcions es canvien a `~/.config/teixit/teixit.ini` (crea'l si no hi és), amb el mateix format que el `teixit.ini` de Windows.

### Altres distribucions: paquet .tar.gz

Cal tindre PHP 8.1 o posterior amb les extensions `pdo_sqlite` i `mbstring` (i `xml` per a importar fitxers d'Excel). En Debian, Ubuntu, Linux Mint i semblants:

```
sudo apt install php-cli php-sqlite3 php-mbstring php-xml
```

En Fedora: `sudo dnf install php-cli php-pdo php-mbstring php-xml php-sodium`. En Arch: `sudo pacman -S php php-sqlite` (i activa `pdo_sqlite` a `/etc/php/php.ini`).

#### Instal·lar

1. Baixa `Teixit-X.Y.Z-linux.tar.gz` i descomprimeix-lo.
2. Dins de la carpeta `Teixit`, executa:
   ```
   ./installa.sh
   ```
   S'instal·la per a l'usuari actual, sense `sudo`, i crea l'accés al menú d'aplicacions i l'ordre `teixit`.
3. Obri **Teixit** des del menú d'aplicacions o escrivint `teixit` en un terminal.

També es pot utilitzar sense instal·lar: executa `./teixit.sh` des de la carpeta descomprimida.

Per a **actualitzar**, descomprimeix la versió nova i torna a executar `./installa.sh`. Per a **desinstal·lar**, `~/.local/share/teixit/programa/desinstalla.sh`. En cap dels dos casos es toquen les dades.

### Com funciona a Linux

Teixit s'obri en la seua finestra, feta amb WebKitGTK (el motor web de GNOME). El paquet .deb l'instal·la si cal; amb el .tar.gz, instal·la'l amb `sudo apt install python3-gi gir1.2-webkit2-4.1`. En tancar la finestra, fa una còpia de seguretat si hi ha hagut canvis i s'atura.

Si WebKitGTK no hi és, Teixit prova Chromium, Google Chrome, Microsoft Edge o Brave en mode aplicació.

Si tampoc no n'hi ha cap, s'obri el navegador habitual i una finestra de terminal. **Mentre la finestra del terminal estiga oberta, Teixit funciona.** Per a tancar-lo, prem **Ctrl+C** o tanca la finestra.

Les dades es guarden a `~/.local/share/teixit/dades` (o a la carpeta que indique la variable `TEIXIT_DADES`).

## Com funciona

En obrir Teixit, s'obri **la seua finestra**, sense cap navegador. A Windows es fa amb el component **WebView2**, que porten Windows 10 i 11 (és el que fan servir molts programes per a mostrar pantalles); a Linux, amb WebKitGTK. En tancar la finestra, Teixit fa una còpia de seguretat (si hi ha hagut canvis) i s'atura. Les pàgines de fora de Teixit (per exemple, la web o GitHub) s'obrin al navegador habitual.

A Windows, si falta WebView2, Teixit s'obri amb Microsoft Edge o Google Chrome en mode aplicació, amb un perfil separat; mentrestant, la icona de Teixit és a la safata del sistema, al costat del rellotge.

Si no hi ha res d'això (o si es posa `finestra = 0` a `teixit.ini`), s'obri el navegador habitual a `http://localhost:8750` i una finestra menuda de Teixit. **Mentre la finestra menuda estiga oberta, Teixit funciona.**

Teixit només es pot utilitzar des d'este ordinador: no és accessible des de la xarxa ni des d'Internet, i no envia cap dada fora (vegeu la [declaració de privacitat](https://github.com/jossanmar9/teixit/blob/main/PRIVACITAT.md)).

La primera vegada demana el tipus i el nom de l'entitat i crea el compte d'administrador i, opcionalment, el d'usuari/ària. Després mostra un **codi de recuperació**: imprimeix-lo o apunta'l, perquè és el que permet posar una contrasenya nova si l'administrador/a l'oblida.

## Carpeta del programa (Windows)

```
Teixit\
├── Teixit.exe        llançador
├── teixit.ini        configuració del llançador
├── app\  src\        el programa (PHP, HTML, CSS, JavaScript)
├── php\              PHP portable
├── docs\             manuals i guies
└── data\             LES DADES: teixit.db, còpies automàtiques i registres
```

## Configuració (`teixit.ini`)

Obri'l amb el Bloc de notes (o qualsevol editor de text). Després de canviar-lo, tanca i torna a obrir Teixit.

- `port = 8750`: canvia'l si un altre programa ja l'utilitza.
- `navegador = 1`: obri Teixit en arrancar.
- `finestra = 1`: obri Teixit en la seua finestra. Amb `finestra = 0`, s'obri al navegador habitual i es mostra la finestra menuda.

## Còpies de seguretat

Teixit fa còpies automàtiques cada dia i en tancar el programa (si hi ha hagut canvis), a la carpeta `copies` de les dades. **Configura una carpeta externa** (disc USB o carpeta del núvol) a **Còpies de seguretat** perquè cada còpia es guarde també fora de l'ordinador, i si vols, xifrada amb contrasenya. Vegeu el manual d'usuari.

## Solució de problemes

| Problema | Què fer |
|---|---|
| Windows diu que ha protegit l'ordinador o que una directiva ha bloquejat el fitxer | Vegeu l'apartat «Si Windows no et deixa obrir-lo». |
| «El port 8750 ja està en ús» | Canvia el `port` a `teixit.ini`. |
| La finestra de Teixit no s'obri | Fes clic a la icona de Teixit de la safata del sistema, o escriu `http://localhost:8750` al navegador. |
| «El servidor s'ha aturat» | Mira `servidor.log` i `php-errors.log` a la carpeta de dades. |
| No arranca i diu que falten fitxers (Windows) | Torna a instal·lar o a descomprimir. L'antivirus pot haver posat en quarantena `php.exe`. |
| Falta `VCRUNTIME140.dll` (Windows) | Instal·la el *Microsoft Visual C++ Redistributable* (x64) des de la web de Microsoft. |
| «No s'ha trobat PHP» o «falten extensions» (Linux) | Instal·la els paquets que indica el missatge. |

## Per a desenvolupar

Amb PHP 8.1 o posterior (amb `pdo_sqlite` i `mbstring`) instal·lat:

```
php -S 127.0.0.1:8750 -t app app/index.php
```

Les dades es guarden a `data/` (o a la carpeta que indique la variable d'entorn `TEIXIT_DADES`).

Per a publicar una versió, puja una etiqueta en minúscula (`v1.0.0`) i GitHub Actions construeix l'instal·lador i el zip per a Windows i el paquet per a Linux.
