# Registre de canvis

Tots els canvis destacats de Teixit s'apunten ací. Les versions segueixen el format `MAJOR.MENOR.CORRECCIÓ`.

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
