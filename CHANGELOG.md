# Registre de canvis

Tots els canvis destacats de Teixit s'apunten ací. Les versions segueixen el format `MAJOR.MENOR.CORRECCIÓ`.

## Projecte tancat - 2026-10-23

Teixit s'ha integrat en [Compàs 2.0](https://github.com/jossanmar9/compas), la gestió lliure per a associacions. Teixit ja no tindrà versions noves; la 1.1.1 és l'última.

## [1.1.1] - 2026-10-22

### Seguretat
- **PHP actualitzada per les vulnerabilitats «PHP2Shell»** (CVE-2026-109618 i CVE-2026-109619). La versió per a Windows porta PHP 8.3.36 o posterior, que les corregix, i la publicació falla si la PHP que es baixa és més antiga. A Linux, Teixit usa la PHP del sistema: n'hi ha prou d'actualitzar el sistema com sempre.
- Es desactiva una opció de PHP que Teixit no fa servir (`session.upload_progress`), una de les mesures recomanades per a estes vulnerabilitats.
- El risc per a Teixit era baix: el seu servidor només escolta el mateix ordinador (127.0.0.1) i no s'hi pot accedir des de la xarxa ni des d'Internet.

## [1.1.0] - 2026-10-22

### Afegit
- **Penya d'un club**, un tipus d'entitat nou per a les penyes d'aficionats d'un club de futbol o d'un altre esport: socis (ordinari, jove, infantil, d'honor), **comissions** (viatges, festes, animació…), **roba i marxandatge** amb bufandes, loteria i dos categories noves al llibre de caixa: **desplaçaments i entrades** i **dinars i sopars**. També té les seues dades d'exemple.
- **Manual d'administració**, separat del manual d'usuari: configuració inicial i tipus d'entitat, dades de l'entitat i logotip, mòduls i avisos, banc i descomptes, còpies de seguretat, comptes i contrasenyes, com actualitzar Teixit i preguntes freqüents. A **Ajuda** hi ha una pestanya per a cada manual (la d'administració només la veu l'administrador/a), i els dos tenen el seu PDF i accés directe al menú d'inici de Windows.
- **Taula de permisos**: què pot fer cada compte, al començament dels dos manuals, al README i a la web.
- **Preguntes freqüents a la web**.
- **Hi ha versió nova?** a l'ajuda: obri al navegador la pàgina de l'última versió de Teixit, i davall es veu la versió que tens. Teixit continua sense connectar-se a Internet.
- Entitats grans: quan hi ha més de 400 persones (o d'altres opcions), el camp de persona dels formularis es busca escrivint, sense tindre en compte els accents, en lloc d'un desplegable amb tota la llista. Els llistats llargs ja no mostren totes les pàgines.

### Canviat
- El compte «administratiu/va» ara es diu **usuari/ària** («usuario/a» en castellà), per a no confondre'l amb l'administrador/a. Només canvia el nom: els permisos són els mateixos.
- El manual d'usuari ja només explica la feina del dia a dia; el que és de l'administrador/a remet al manual d'administració.
- **Actualitzacions més segures**: la base de dades només es prepara una vegada per versió (abans es repassava en cada clic), en una sola transacció i amb una còpia «abans d'actualitzar». Les pantalles carreguen més ràpid.
- **Cap versió no es publica si alguna prova falla**: el workflow de publicació passa abans totes les proves, també les d'integració.
- L'instal·lador de Windows ja no porta les parts de PHP que Teixit no fa servir (intl, curl, openssl…).
- Les captures dels manuals i de la web són WebP: ocupen un 40 % menys.

Totes estes millores, excepte la penya, venen de [Compàs](https://github.com/jossanmar9/compas) 1.13.4 i 1.14.0.

## [1.0.0] - 2026-10-22

Primera versió de Teixit, la gestió lliure per a associacions. Naix de [Compàs](https://github.com/jossanmar9/compas) 1.13.3, el programa per a societats musicals del mateix autor, i en conserva tot el que serveix a qualsevol entitat.

### Afegit
- **Tipus d'entitat**: associació cultural, falla (gaiata o foguera), club esportiu o AMPA/AFA. Es tria en la configuració inicial i es pot canviar a Configuració. Decideix:
  - el **vocabulari**: seccions, equips o comissions; cens i categories en les falles; famílies associades en les AMPA; extraescolars o escola esportiva…;
  - els **mòduls** que s'activen en instal·lar;
  - els **tipus de soci** inicials;
  - les **categories del llibre de caixa** pròpies (monument i casal, arbitratges i llicències, activitats per a l'alumnat);
  - les **dades d'exemple**.
- **Llicències federatives** en els clubs: número i caducitat de cada membre d'un equip, avís a l'inici 30 dies abans i filtres al llistat.
- **Qualificacions i butlletins** és un mòdul propi, separat d'Activitats i cursos, perquè només el fan servir les entitats que avaluen.
- Peces de roba noves: xandall, equipació, brusó i mocador.
- Logotip i colors de Teixit.

### Canviat respecte de Compàs
- L'escola de música és ara **Activitats i cursos** (tallers, cursos, extraescolars o escola esportiva), amb **monitors** i **activitats**.
- Les agrupacions són **seccions** (equips en els clubs, comissions en les AMPA), amb un tipus o categoria lliure i els papers de membre, responsable o ajudant.
- Els instruments són l'**inventari** de material de l'entitat, i les cessions, **préstecs de material**.
- Els uniformes són **roba i articles** (equipacions en els clubs).
- Tots els textos parlen de l'**entitat**, no de la societat musical: certificats, actes, full d'alta, RGPD, rebuts…
- Teixit pot conviure amb Compàs en el mateix ordinador: té la seua carpeta, les seues dades i el seu port (8750).

### Llevat
- El repertori (catàleg de l'arxiu musical) i la seua importació.
