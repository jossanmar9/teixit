# Arquitectura de Teixit

Teixit és un programa de gestió per a una única associació o entitat sense ànim de lucre (associació cultural, falla, club esportiu o AMPA), en un sol ordinador amb Windows o Linux i amb dos comptes (administrador/a i administratiu/va). No té web pública ni accés per xarxa.

## Peces

```
Teixit.exe (llançador, C#)
   │  arranca
   ▼
php.exe -S 127.0.0.1:8750   (servidor integrat de PHP, portable)
   │
   ▼
app/index.php  ──►  src/  (lògica en PHP)
                     │
                     ▼
               data/teixit.db  (SQLite, un sol fitxer)
```

- **Llançador** (`launcher/Teixit.cs`): arranca PHP només a `127.0.0.1`, espera que responga i obri la finestra de Teixit (`launcher/Visor.cs`, un formulari amb el control WebView2 i les dades a `%LOCALAPPDATA%\Teixit\visor`). Les pàgines que s'obrin a part (impressions) són altres finestres del visor, i les adreces de fora van al navegador habitual. Quan es tanca la finestra principal, Teixit s'atura. Si WebView2 no hi és, obri Microsoft Edge o Google Chrome amb `--app` i un perfil propi a `%LOCALAPPDATA%\Teixit\finestra` (amb una icona a la safata); si no hi ha Edge ni Chrome, o amb `finestra = 0`, obri el navegador habitual i mostra la finestra menuda de sempre. En tancar, demana a PHP una còpia de seguretat (amb un testimoni secret que només coneixen el llançador i PHP) i l'atura. Llig `teixit.ini`.
- **Linux** (`launcher/linux/`): `teixit.sh` fa el mateix que el llançador de Windows amb el PHP del sistema (comprova la versió i les extensions, arranca a `127.0.0.1`, obri la finestra de Teixit i, amb `trap` en eixir, demana la còpia de tancament). La finestra és `teixit-visor.py` (Python amb GTK 3 i WebKitGTK, les dades a `~/.local/share/teixit/visor`); quan es tanca, `teixit.sh` acaba. Si no hi és, prova Chromium, Chrome, Edge o Brave amb `--app` i, si tampoc, `xdg-open` amb un terminal. `installa.sh` el copia a `~/.local/share/teixit/programa` i crea l'entrada del menú (`Terminal=false`) i l'ordre `teixit`. Les dades van a `~/.local/share/teixit/dades`. El paquet `.deb` (`eines/construeix-deb.sh`, amb `launcher/linux/deb/`) posa el programa a `/usr/share/teixit`, l'ordre a `/usr/bin/teixit` i l'entrada del menú, depén de `php-cli`, `php-sqlite3` i `php-mbstring` i recomana `php-xml` i el necessari per a la finestra (`python3-gi`, `gir1.2-gtk-3.0`, `gir1.2-webkit2-4.1`); la configuració de cada usuari va a `~/.config/teixit/teixit.ini`.
- **PHP portable**: es baixa de windows.php.net en construir la versió. Extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`, `opcache` i `sodium` (xifrat de les còpies; `libsodium.dll` va al costat de `php.exe`). Les integrades `zlib` i `SimpleXML` s'utilitzen per a llegir els .xlsx.
- **Aplicació**: PHP sense dependències ni frameworks, HTML i CSS propis i un poc de JavaScript. Funciona sense Internet.
- **Base de dades**: SQLite a través de PDO. Tot l'SQL usa consultes preparades. Es pot migrar a MariaDB/MySQL més endavant canviant la connexió i adaptant `schema.sql`.

## Codi

| Fitxer | Funció |
|---|---|
| `app/index.php` | Punt d'entrada únic i encaminador (`?r=…`). |
| `src/bootstrap.php` | Connexió, sessió, CSRF, usuari actual, permisos, auditoria. |
| `src/schema.sql` | Esquema de la base de dades (es crea sol la primera vegada). |
| `src/capacitats.php` | Capacitats i rols per defecte. |
| `src/tipus.php` | Tipus d'entitat (`cultural`, `falla`, `club`, `ampa`): mòduls desactivats i tipus de soci inicials de cadascun, i `tipus_entitat()`. |
| `src/entitats.php` | Definició declarativa de cada entitat: camps, relacions i validacions. |
| `src/crud.php` | Llistats, fitxes, formularis i exportació CSV genèrics a partir de `entitats.php`. |
| `src/pages/*.php` | Pantalles específiques: inici, horari, qualificacions, butlletins i logotip, documents (consentiments i certificats), llistats i informes, importació, passar de curs, economia (generació de rebuts, remeses, rebuts i mandats per a imprimir, configuració), loteria, caixa (llibre de caixa), societat (junta, actes, documents, full d'alta, comunicats), rgpd, uniformes, repertori, usuaris, configuració, auditoria, còpies, recuperació, ajuda. |
| `src/fulls.php` | Lectura de CSV i Excel (.xlsx) per a la importació, sense l'extensió zip: llig el directori central del zip i descomprimeix amb `gzinflate`. |
| `src/rotacio.php` | Quines còpies automàtiques es conserven: totes les dels últims 7 dies, una per setmana de les últimes 5 i una per mes fins al límit configurat (`copia_mesos`, 12 per defecte). |
| `src/xifrat.php` | Xifrat de les còpies externes amb libsodium. |
| `src/sepa.php` | IBAN (mòdul 97), identificador de creditor SEPA, text amb el joc de caràcters SEPA i XML de la remesa (pain.008.001.02 o .08, CORE; un bloc FRST per als mandats que no s'han usat mai i un RCUR per a la resta). |
| `app/assets/document.css` | Documents A4: consentiments, certificats, llistats i etiquetes (sobre `butlleti.css`). |
| `src/lang/ca.php`, `es.php` | Textos en valencià i castellà. Una clau amb `@tipus` (per exemple `ent.agrupacions@club` → «Equips») substituïx la general en eixe tipus d'entitat: `t()` la busca primer. |
| `app/assets/` | Estil, JavaScript, logotip i icona. |
| `src/markdown.php` | Conversor de Markdown a HTML (manual dins del programa i PDF). |
| `eines/document-html.php`, `eines/pdf.sh` | Preparen cada manual com a pàgina A4 amb portada i l'imprimeixen en PDF amb Chrome (`docs/pdf/`). Els PDF no es guarden al repositori: els fa GitHub Actions en publicar una versió (s'adjunten a la versió) i en publicar la web. |
| `eines/captures.py` | Genera les captures dels manuals (`docs/imatges/ca` i `es`) fent cada procés amb dades d'exemple. Requereix Python, Pillow i Playwright: `python3 eines/captures.py`. |
| `eines/web.php`, `eines/web.css` | Generen la web de presentació (valencià i castellà) amb captures i PDF. El workflow `web.yml` la publica a GitHub Pages. |

Per a afegir una entitat senzilla: crea la taula a `schema.sql`, defineix-la a `entitats.php`, afig-la al menú (`src/disseny.php`) i posa els textos a `lang/`. Una entitat pot afegir botons a la seua fitxa amb `accions_fitxa` (així es fan els botons de certificat, full de consentiment i passar de curs).

Si una versió nova afig columnes a una taula que ja existeix, cal posar-les a `schema.sql` (instal·lacions noves) i a `migra_esquema()` de `src/bootstrap.php` (bases de dades d'abans), que les afig amb `ALTER TABLE` si falten.

## Noms interns

Teixit naix de Compàs, i les taules i els mòduls conserven els noms interns d'allà, encara que a la pantalla es diguen d'una altra manera:

| Nom intern | A la pantalla |
|---|---|
| `escola`, `alumnes`, `professors`, `assignatures` | Activitats i cursos, alumnat, monitors, activitats |
| `agrupacions`, `membres`, `instrument_veu` | Seccions (equips, comissions), membres, funció o posició |
| `instruments`, `cessions` | Inventari, préstecs de material |
| `uniformes` | Roba i articles |
| `societat_*` (configuració), `menu.societat` | Dades de l'entitat, secció del menú amb el nom del tipus |

## Model de dades

- **Persona** és l'entitat central. Cada persona existeix una sola vegada.
- Les funcions són taules separades vinculades a la persona: `socis`, `alumnes`, `professors`, `membres` (de les seccions, equips o comissions; amb `llicencia` i `llicencia_caducitat` per als clubs), `tutories` (alumne ↔ tutor legal, molts a molts), `cessions`.
- Escola: `cursos` → `periodes`, `grups` (assignatura + curs + professor + aula) → `horaris`; `matricules` (alumne + curs) → `matricula_grups`; `qualificacions` (grup + alumne + període). Teixit no registra l'assistència: és un programa de gestió.
- Inventari: `instruments` → `cessions`, `manteniments`.
- Drets d'imatge: `persones.consentiment_imatge` (`pendent`, `autoritza`, `no_autoritza`) i `consentiment_data`.
- Accés: `usuaris`, `rols` (dos fixos: `admin` i `administratiu`), `rol_capacitats`, `usuari_rols`. Es comproven **capacitats**, no rols.
- Economia: `tarifes`, `mandats`, `remeses`, `rebuts` → `rebut_linies`, `loteries` → `talonaris`, `moviments` (llibre de caixa: només els apunts manuals; rebuts, remeses i loteria s'hi sumen en llegir-lo).
- Entitat: `carrecs` (persona + càrrec + dates; vigent si no té `data_fi`), `reunions` (convocatòria, acta i acords en text, un per línia), `comunicats`.
- Tipus de soci: `tipus_socis`, definits per cada entitat. `socis.tipus` i `tarifes.tipus_soci` guarden l'id (en text). En la configuració inicial, `sembra_tipus_socis()` crea els del tipus d'entitat (`TIPUS_SOCI_TIPUS`, a `src/tipus.php`).
- Uniformes: `uniformes` (talles en text, separades per comes) → `uniforme_compres` i `uniforme_lliuraments` (venda, préstec o gratuït). Les existències es calculen: comprades − lliurades + préstecs tornats.
- Mòduls: `MODULS` (a `entitats.php`) diu quines pantalles i entitats té cada mòdul; `config.moduls_desactivats` i `config.avisos_desactivats` guarden el que ha desactivat l'entitat. `MODULS_DEPENEN` diu els mòduls que en necessiten un altre (les qualificacions, les activitats). `disponible()` amaga el menú, les fitxes filles i els informes, i `app/index.php` no obri les pantalles d'un mòdul desactivat.
- Documents: `documents` (metadades i caducitat) i `document_fitxers` (contingut en BLOB, `ON DELETE CASCADE`). Guardar-los a la base de dades fa que `VACUUM INTO` els incloga en cada còpia, xifrada o no, sense una carpeta a banda. Es serveixen amb `?r=document_fitxer` (en línia per a PDF, imatges i text; descarregats la resta).
- `auditoria` registra les operacions sensibles.
- Les claus foranes no deixen esborrar res amb historial: les baixes es marquen amb estat o data de baixa.

## Seguretat

- Només escolta a `127.0.0.1`: no és accessible des de la xarxa.
- Contrasenyes amb `password_hash`. Bloqueig temporal després de 5 intents fallits.
- Recuperació de l'accés (`src/pages/recuperacio.php`): codi de 16 caràcters (80 bits, alfabet sense 0/O/1/I) guardat només com a `password_hash` a `config`; d'un sol ús (en usar-lo se'n genera un altre) i no torna arrere en restaurar una còpia. Alternativa sense codi: un fitxer `recupera-acces` a la carpeta de dades, vàlid 60 minuts i esborrat en usar-lo (qui pot escriure-hi ja té accés a `teixit.db`). Només per a comptes d'administrador; límit d'intents guardat a la base de dades, no a la sessió.
- Token CSRF en tots els formularis. Cookies `HttpOnly` i `SameSite=Strict`.
- Tota l'eixida s'escapa amb `htmlspecialchars`.
- La carpeta de dades queda fora de la carpeta pública (`app/`); el servidor només serveix `app/assets/`.
- L'administratiu/va no pot gestionar usuaris, configuració, registre d'activitat ni restaurar còpies.
- Còpies amb `VACUUM INTO` (consistents encara que el programa estiga obert): diària, en tancar si hi ha canvis, manual, abans de restaurar i abans d'importar; duplicades a una carpeta externa opcional.
- Còpies externes xifrades (opcional): clau derivada de la contrasenya amb Argon2id (`crypto_pwhash`) i xifrat en blocs d'1 MB amb XChaCha20-Poly1305 (`crypto_secretstream`), que detecta qualsevol modificació o tall del fitxer. A l'ordinador només es guarda la clau derivada, per a poder fer les còpies automàtiques; la contrasenya fa falta per a restaurar.
- Importació: els .xlsx es llegeixen sense DTD (`LIBXML_NONET`, sense entitats externes); les dades entre passos es guarden en un fitxer temporal de la carpeta de dades amb un nom aleatori lligat a la sessió, i s'esborren en acabar o al cap d'un dia.

## Pendent (pròximes versions)

- Calendari d'actes i activitats (amb els espais municipals).
- Mòduls propis de cada tipus d'entitat: actes fallers i representants, calendari de partits, menjador escolar.
- Subvencions.

## Notes

- Economia (`src/pages/economia.php`): els rebuts generats porten una `clau` única (soci i període, o pagador, curs i període) perquè generar dues vegades el mateix període no duplique res. L'import d'un rebut amb línies és la suma de les línies (`recalcula_rebut`). Numeració correlativa per any (`2026/0001`). Una remesa preparada fixa el mandat de cada rebut; marcar-la com a cobrada posa els rebuts pagats i l'`ultim_us` dels mandats (el següent cobrament ja és RCUR). Teixit fa rebuts, no factures, per a no entrar en el reglament dels sistemes de facturació (Verifactu).
