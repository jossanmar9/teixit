# Proves de Teixit

Proves automàtiques sense PHPUnit ni Composer. No toquen `data/teixit.db`.

## Proves de funcionament

```bash
php tests/run.php
```

Comproven:

- la normalització, el format i la validació de l'IBAN, i la conversió del compte espanyol antic (CCC) a IBAN;
- la validació del BIC, els identificadors SEPA i el text amb el joc de caràcters SEPA;
- el xifrat i el desxifrat de les còpies de seguretat, el rebuig d'una contrasenya incorrecta i la detecció d'una còpia manipulada;
- la rotació de les còpies automàtiques (quines es guarden i quines s'esborren).

Algunes necessiten les extensions `mbstring` i `sodium` de PHP.

GitHub Actions (`.github/workflows/tests.yml`) les executa en cada `push` a `main`, en cada *pull request* i a mà, juntament amb les proves d'integració. També revisa la sintaxi de tots els fitxers PHP i comprova que el servidor local arranca.

## Proves d'integració

```bash
python3 tests/integracio.py              # totes, en valencià i castellà
python3 tests/integracio.py ca           # només en valencià
python3 tests/integracio.py tipus        # només una bateria
```

Arranquen Teixit de veres (el servidor de PHP amb dades d'exemple en una carpeta temporal) i fan el que faria una persona: crear, editar i eliminar, generar rebuts i remeses, importar fulls, imprimir documents, activar i desactivar mòduls… Comproven més de 2.800 coses en uns segons. Només cal PHP i Python 3, sense paquets addicionals, i no toquen la carpeta `data/`.

| Bateria | Fitxers (a `tests/integracio/`) | Què comprova |
|---|---|---|
| `economia` | `economia.py`, `iban.py`, `societat.py`, `repas.py`, `actualitzacio.py` | Tarifes, rebuts, remeses SEPA, domiciliacions i loteria; IBAN a les fitxes i en importar; junta, actes, documents, full d'alta, comunicats, RGPD i caixa; un repàs de totes les pantalles (llistats, CSV, fitxes i formularis) sense errors de PHP; i que la base de dades només s'actualitza una vegada per versió, amb una còpia abans. |
| `uniformes` | `uniformes_moduls.py` | Tipus de soci propis, roba per talla, mòduls i avisos desactivables. |
| `tipus` | `tipus.py` | Es repeteix amb els quatre tipus d'entitat: mòduls i tipus de soci inicials, vocabulari, totes les pantalles sense errors ni textos de Compàs, llicències federatives dels clubs i canvi de tipus a la configuració. |
| `gran` | `gran.py` | Entitats grans: el camp de persona amb cerca quan n'hi ha centenars i els llistats llargs. |
| `avis` | `avis.py` | La configuració inicial: el tipus d'entitat i l'avís de responsabilitat (també en les instal·lacions que encara no l'han acceptat). |
| `importacio` | `importacio.php` | Importació de socis i alumnat amb IBAN (crida les funcions de PHP directament). |

Cada fitxer rep de `tests/integracio.py` les funcions comunes: `G(consulta)` obri una pàgina, `P(consulta, dades)` envia un formulari, `n(sql)` consulta la base de dades, `ok(condició, missatge)` apunta el resultat, etc. Per a afegir proves d'una funció nova, crea un fitxer i posa'l en una bateria de `BATERIES`. Fora de la bateria `tipus`, la instal·lació és d'una associació cultural amb tots els mòduls actius.

GitHub Actions també les executa en cada `push`.

## Prova de rendiment

```bash
php tests/performance.php
```

- Crea una base de dades SQLite temporal amb l'esquema real de `src/schema.sql`.
- Comprova que hi ha els índexs addicionals.
- Genera 100.000 rebuts i 300.000 línies.
- Amb `EXPLAIN QUERY PLAN`, comprova que les consultes més habituals fan servir els índexs, i executa cada consulta 100 vegades per a mesurar el temps.
- En acabar, esborra la base de dades temporal.

No s'executa en cada `push`, perquè és lenta.

## Com afegir proves

Abans de canviar la lògica de rebuts, SEPA, còpies, migracions o permisos, afig una prova a `tests/run.php` que comprove el comportament actual. Així es veu de seguida si el canvi trenca alguna cosa.
