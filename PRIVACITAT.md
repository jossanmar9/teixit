# Privacitat de Teixit

**En resum: Teixit no recull, no envia i no comparteix cap dada. Tot es queda a l'ordinador de l'entitat.**

## El programa

- Teixit funciona en un sol ordinador amb Windows o Linux. Només escolta en el mateix ordinador (`127.0.0.1`): no s'hi pot entrar des de la xarxa local ni des d'Internet.
- No es connecta a Internet per a res: no envia estadístiques d'ús ni informes d'errors, no comprova actualitzacions, no mostra publicitat i no carrega tipografies, imatges ni programes de fora.
- La finestra de Teixit mostra només les pàgines del mateix ordinador amb el component **WebView2** de Windows (a Linux, WebKitGTK); els enllaços de fora s'obrin al navegador habitual. Teixit hi desactiva SmartScreen i l'emplenament automàtic, i no hi envia res. WebView2 forma part de Windows: Microsoft l'actualitza i en pot rebre dades de diagnòstic segons la configuració de privacitat de Windows, igual que amb la resta del sistema.
- Les dades (persones, socis, junta i actes, seccions, activitats, inventari, rebuts i comptes bancaris de les domiciliacions…) es guarden en un fitxer de l'ordinador: `data\teixit.db`, dins de la carpeta del programa (a Linux, `~/.local/share/teixit/dades`).
- Les còpies de seguretat es guarden a l'ordinador i, si l'entitat ho configura, en la carpeta externa que trie: un llapis USB, un disc extern, una unitat de xarxa o una carpeta sincronitzada amb el núvol. En este últim cas, les còpies passen pel servei que l'entitat haja triat (OneDrive, Google Drive…), amb les seues condicions. Per això Teixit permet xifrar-les amb contrasenya.
- Les remeses bancàries són un fitxer que Teixit prepara i que la persona responsable puja a la banca electrònica de l'entitat. Teixit no es connecta al banc.
- Els documents de l'entitat que s'hi arxiven (estatuts, actes, contractes, pòlisses…) es guarden dins del mateix fitxer de dades i, per tant, també en les còpies de seguretat.
- Teixit no envia correus: els comunicats només preparen la llista d'adreces perquè la persona responsable les copie al correu de l'entitat.
- Els documents que s'imprimeixen o es guarden en PDF o CSV (certificats, actes, llistats, rebuts…) queden on els guarde la persona que els fa.

## Qui és responsable de les dades

- L'**entitat** que utilitza Teixit és la **responsable del tractament** de les dades personals que hi introdueix. Ha de complir el Reglament general de protecció de dades (RGPD) i la Llei orgànica 3/2018 (LOPDGDD): informar les persones, demanar els consentiments que calguen, atendre els drets d'accés, rectificació, supressió, etc.
- El **desenvolupador de Teixit** (José Vicente Sanjuan) **no té accés** a les dades de cap entitat: no les rep, no les guarda i no les tracta. Per tant, no és encarregat del tractament.
- Teixit es distribueix tal com és, sense cap garantia: l'autor no es fa responsable de la pèrdua de dades ni de cap altre dany derivat del seu ús. Cada entitat ha de fer còpies de seguretat periòdiques en un lloc extern.
- Teixit ajuda a complir la normativa: dos comptes amb permisos diferents, contrasenyes guardades xifrades, registre d'activitat, còpies externes xifrades i control del consentiment d'imatge.

## La web i les descàrregues

- La web de presentació ([jossanmar9.github.io/teixit](https://jossanmar9.github.io/teixit/)), el codi i les descàrregues estan allotjats a **GitHub**. Com qualsevol servidor, GitHub pot registrar l'adreça IP i dades tècniques de qui visita la web o baixa el programa. Vegeu la [declaració de privacitat de GitHub](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement).
- La web no utilitza galetes, ni analítica, ni tipografies o programes de tercers. Les tipografies, que són lliures (llicència SIL Open Font License), es serveixen des de la mateixa web.
- El que s'escriu en una incidència de GitHub és públic. No hi poseu dades de persones, ni en el text ni en les captures.

## Contacte

Teixit és un projecte de José Vicente Sanjuan. Si teniu dubtes sobre esta declaració, obriu una [incidència](https://github.com/jossanmar9/teixit/issues/new/choose).

*Darrera actualització: octubre de 2026.*

---

# Privacidad de Teixit

**En resumen: Teixit no recoge, no envía y no comparte ningún dato. Todo se queda en el ordenador de la entidad.**

## El programa

- Teixit funciona en un solo ordenador con Windows o Linux. Solo escucha en el mismo ordenador (`127.0.0.1`): no se puede entrar desde la red local ni desde Internet.
- No se conecta a Internet para nada: no envía estadísticas de uso ni informes de errores, no comprueba actualizaciones, no muestra publicidad y no carga tipografías, imágenes ni programas de fuera.
- La ventana de Teixit muestra solo las páginas del mismo ordenador con el componente **WebView2** de Windows (en Linux, WebKitGTK); los enlaces de fuera se abren en el navegador habitual. Teixit desactiva en ella SmartScreen y el autocompletado, y no envía nada. WebView2 forma parte de Windows: Microsoft lo actualiza y puede recibir datos de diagnóstico según la configuración de privacidad de Windows, igual que con el resto del sistema.
- Los datos (personas, socios, junta y actas, secciones, actividades, inventario, recibos y cuentas bancarias de las domiciliaciones…) se guardan en un archivo del ordenador: `data\teixit.db`, dentro de la carpeta del programa (en Linux, `~/.local/share/teixit/dades`).
- Las copias de seguridad se guardan en el ordenador y, si la entidad lo configura, en la carpeta externa que elija: un pendrive, un disco externo, una unidad de red o una carpeta sincronizada con la nube. En este último caso, las copias pasan por el servicio que la entidad haya elegido (OneDrive, Google Drive…), con sus condiciones. Por eso Teixit permite cifrarlas con contraseña.
- Las remesas bancarias son un archivo que Teixit prepara y que la persona responsable sube a la banca electrónica de la entidad. Teixit no se conecta al banco.
- Los documentos de la entidad que se archivan (estatutos, actas, contratos, pólizas…) se guardan dentro del mismo archivo de datos y, por tanto, también en las copias de seguridad.
- Teixit no envía correos: los comunicados solo preparan la lista de direcciones para que la persona responsable las copie en el correo de la entidad.
- Los documentos que se imprimen o se guardan en PDF o CSV (certificados, actas, listados, recibos…) quedan donde los guarde la persona que los hace.

## Quién es responsable de los datos

- La **entidad** que usa Teixit es la **responsable del tratamiento** de los datos personales que introduce. Debe cumplir el Reglamento general de protección de datos (RGPD) y la Ley Orgánica 3/2018 (LOPDGDD): informar a las personas, pedir los consentimientos necesarios, atender los derechos de acceso, rectificación, supresión, etc.
- El **desarrollador de Teixit** (José Vicente Sanjuan) **no tiene acceso** a los datos de ninguna entidad: no los recibe, no los guarda y no los trata. Por tanto, no es encargado del tratamiento.
- Teixit se distribuye tal cual, sin ninguna garantía: el autor no se hace responsable de la pérdida de datos ni de ningún otro daño derivado de su uso. Cada entidad debe hacer copias de seguridad periódicas en un lugar externo.
- Teixit ayuda a cumplir la normativa: dos cuentas con permisos diferentes, contraseñas guardadas cifradas, registro de actividad, copias externas cifradas y control del consentimiento de imagen.

## La web y las descargas

- La web de presentación ([jossanmar9.github.io/teixit](https://jossanmar9.github.io/teixit/)), el código y las descargas están alojados en **GitHub**. Como cualquier servidor, GitHub puede registrar la dirección IP y datos técnicos de quien visita la web o descarga el programa. Véase la [declaración de privacidad de GitHub](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement).
- La web no usa cookies, ni analítica, ni tipografías o programas de terceros. Las tipografías, que son libres (licencia SIL Open Font License), se sirven desde la propia web.
- Lo que se escribe en una incidencia de GitHub es público. No pongáis datos de personas, ni en el texto ni en las capturas.

## Contacto

Teixit es un proyecto de José Vicente Sanjuan. Si tenéis dudas sobre esta declaración, abrid una [incidencia](https://github.com/jossanmar9/teixit/issues/new/choose).

*Última actualización: octubre de 2026.*

---

# Privacy statement (English)

**This program will not transfer any information to other networked systems.** Teixit runs on a single Windows or Linux computer and only listens on `127.0.0.1`. It has no telemetry, crash reporting, update checks, advertising or remote resources. Its window uses the Windows WebView2 component (WebKitGTK on Linux) only to display local pages, with SmartScreen and autofill disabled; WebView2 is part of Windows and is updated by Microsoft under the Windows privacy settings. All data, including the bank accounts used for direct debits, is stored locally in `data\teixit.db`. Direct debit files are prepared locally and uploaded to the bank by the user; Teixit never connects to the bank. Documents archived in Teixit are stored inside the same database file and its backups. Teixit never sends e-mail; it only prepares address lists. Backups are written only to the local computer and, if the user configures it, to an external folder of their choice (optionally encrypted with a password). The association using Teixit is the data controller; the developer (José Vicente Sanjuan) has no access to any data. Teixit is provided as is, without any warranty; the author is not liable for any data loss or other damage arising from its use, and each association must keep regular external backups. The website and downloads are hosted on GitHub ([GitHub Privacy Statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)); the website uses no cookies, analytics or third-party resources.
