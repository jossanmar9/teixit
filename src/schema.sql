-- Teixit: Gestió lliure per a associacions
-- Esquema de la base de dades (SQLite)
-- Copyright (C) 2026 José Vicente Sanjuan
-- SPDX-License-Identifier: AGPL-3.0-or-later

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS config (
    clau  TEXT PRIMARY KEY,
    valor TEXT
);

-- Persones: registre central, cada persona una sola vegada
CREATE TABLE IF NOT EXISTS persones (
    id              INTEGER PRIMARY KEY,
    nom             TEXT NOT NULL,
    cognoms         TEXT,
    data_naixement  TEXT,
    document        TEXT,
    telefon         TEXT,
    email           TEXT,
    adreca          TEXT,
    codi_postal     TEXT,
    poblacio        TEXT,
    provincia       TEXT,
    observacions    TEXT,
    consentiment_imatge TEXT NOT NULL DEFAULT 'pendent',
    consentiment_data   TEXT,
    actiu           INTEGER NOT NULL DEFAULT 1,
    creat           TEXT DEFAULT (datetime('now','localtime')),
    modificat       TEXT
);

CREATE TABLE IF NOT EXISTS socis (
    id           INTEGER PRIMARY KEY,
    persona_id   INTEGER NOT NULL UNIQUE REFERENCES persones(id) ON DELETE RESTRICT,
    numero       TEXT,
    tipus        TEXT,
    data_alta    TEXT,
    data_baixa   TEXT,
    estat        TEXT NOT NULL DEFAULT 'actiu',
    observacions TEXT
);

CREATE TABLE IF NOT EXISTS alumnes (
    id            INTEGER PRIMARY KEY,
    persona_id    INTEGER NOT NULL UNIQUE REFERENCES persones(id) ON DELETE RESTRICT,
    data_alta     TEXT,
    data_baixa    TEXT,
    estat         TEXT NOT NULL DEFAULT 'actiu',
    pagador_id    INTEGER REFERENCES persones(id) ON DELETE SET NULL,
    descompte_pct REAL,
    observacions  TEXT
);

CREATE TABLE IF NOT EXISTS professors (
    id                  INTEGER PRIMARY KEY,
    persona_id          INTEGER NOT NULL UNIQUE REFERENCES persones(id) ON DELETE RESTRICT,
    data_alta           TEXT,
    data_baixa          TEXT,
    estat               TEXT NOT NULL DEFAULT 'actiu',
    certificat_delictes TEXT,
    observacions        TEXT
);

-- Relació alumne - tutor legal (molts a molts)
CREATE TABLE IF NOT EXISTS tutories (
    id                  INTEGER PRIMARY KEY,
    alumne_id           INTEGER NOT NULL REFERENCES alumnes(id) ON DELETE CASCADE,
    tutor_id            INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    tipus_relacio       TEXT,
    principal           INTEGER NOT NULL DEFAULT 0,
    rep_comunicacions   INTEGER NOT NULL DEFAULT 1,
    observacions        TEXT,
    UNIQUE (alumne_id, tutor_id)
);

CREATE TABLE IF NOT EXISTS agrupacions (
    id           INTEGER PRIMARY KEY,
    nom          TEXT NOT NULL,
    tipus        TEXT,
    descripcio   TEXT,
    estat        TEXT NOT NULL DEFAULT 'actiu',
    data_creacio TEXT
);

-- Membres de les seccions, equips o comissions (agrupacions). La persona responsable es marca amb la funció.
-- instrument_veu: la funció o especialitat de cadascú (la posició, en un equip). La llicència és per als clubs.
CREATE TABLE IF NOT EXISTS membres (
    id             INTEGER PRIMARY KEY,
    persona_id     INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    agrupacio_id   INTEGER NOT NULL REFERENCES agrupacions(id) ON DELETE CASCADE,
    instrument_veu TEXT,
    funcio         TEXT NOT NULL DEFAULT 'membre',
    llicencia      TEXT,
    llicencia_caducitat TEXT,
    data_alta      TEXT,
    data_baixa     TEXT,
    estat          TEXT NOT NULL DEFAULT 'actiu',
    observacions   TEXT
);

CREATE TABLE IF NOT EXISTS cursos (
    id         INTEGER PRIMARY KEY,
    nom        TEXT NOT NULL UNIQUE,
    data_inici TEXT,
    data_fi    TEXT,
    estat      TEXT NOT NULL DEFAULT 'actiu',
    actual     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS periodes (
    id      INTEGER PRIMARY KEY,
    curs_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    nom     TEXT NOT NULL,
    ordre   INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS assignatures (
    id         INTEGER PRIMARY KEY,
    nom        TEXT NOT NULL,
    descripcio TEXT,
    tipus      TEXT,
    estat      TEXT NOT NULL DEFAULT 'actiu'
);

CREATE TABLE IF NOT EXISTS aules (
    id           INTEGER PRIMARY KEY,
    nom          TEXT NOT NULL,
    capacitat    INTEGER,
    observacions TEXT
);

CREATE TABLE IF NOT EXISTS grups (
    id             INTEGER PRIMARY KEY,
    curs_id        INTEGER NOT NULL REFERENCES cursos(id) ON DELETE RESTRICT,
    assignatura_id INTEGER NOT NULL REFERENCES assignatures(id) ON DELETE RESTRICT,
    nom            TEXT NOT NULL,
    professor_id   INTEGER REFERENCES professors(id) ON DELETE SET NULL,
    aula_id        INTEGER REFERENCES aules(id) ON DELETE SET NULL,
    estat          TEXT NOT NULL DEFAULT 'actiu'
);

CREATE TABLE IF NOT EXISTS horaris (
    id          INTEGER PRIMARY KEY,
    grup_id     INTEGER NOT NULL REFERENCES grups(id) ON DELETE CASCADE,
    dia         INTEGER NOT NULL,
    hora_inici  TEXT NOT NULL,
    hora_fi     TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS matricules (
    id           INTEGER PRIMARY KEY,
    alumne_id    INTEGER NOT NULL REFERENCES alumnes(id) ON DELETE RESTRICT,
    curs_id      INTEGER NOT NULL REFERENCES cursos(id) ON DELETE RESTRICT,
    data         TEXT,
    estat        TEXT NOT NULL DEFAULT 'actiu',
    observacions TEXT,
    UNIQUE (alumne_id, curs_id)
);

CREATE TABLE IF NOT EXISTS matricula_grups (
    id           INTEGER PRIMARY KEY,
    matricula_id INTEGER NOT NULL REFERENCES matricules(id) ON DELETE CASCADE,
    grup_id      INTEGER NOT NULL REFERENCES grups(id) ON DELETE RESTRICT,
    UNIQUE (matricula_id, grup_id)
);

CREATE TABLE IF NOT EXISTS qualificacions (
    id           INTEGER PRIMARY KEY,
    grup_id      INTEGER NOT NULL REFERENCES grups(id) ON DELETE CASCADE,
    alumne_id    INTEGER NOT NULL REFERENCES alumnes(id) ON DELETE CASCADE,
    periode_id   INTEGER NOT NULL REFERENCES periodes(id) ON DELETE CASCADE,
    professor_id INTEGER REFERENCES professors(id) ON DELETE SET NULL,
    nota         TEXT,
    observacions TEXT,
    modificat    TEXT,
    UNIQUE (grup_id, alumne_id, periode_id)
);

CREATE TABLE IF NOT EXISTS instruments (
    id               INTEGER PRIMARY KEY,
    inventari        TEXT NOT NULL UNIQUE,
    tipus            TEXT NOT NULL,
    marca            TEXT,
    model            TEXT,
    num_serie        TEXT,
    estat            TEXT NOT NULL DEFAULT 'bo',
    ubicacio         TEXT,
    data_adquisicio  TEXT,
    observacions     TEXT
);

CREATE TABLE IF NOT EXISTS manteniments (
    id            INTEGER PRIMARY KEY,
    instrument_id INTEGER NOT NULL REFERENCES instruments(id) ON DELETE CASCADE,
    data          TEXT NOT NULL,
    descripcio    TEXT NOT NULL,
    cost          REAL,
    observacions  TEXT
);

CREATE TABLE IF NOT EXISTS cessions (
    id             INTEGER PRIMARY KEY,
    instrument_id  INTEGER NOT NULL REFERENCES instruments(id) ON DELETE RESTRICT,
    persona_id     INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    data_entrega   TEXT NOT NULL,
    data_prevista  TEXT,
    data_retorn    TEXT,
    estat_entrega  TEXT,
    estat_retorn   TEXT,
    fianca         REAL,
    observacions   TEXT
);

-- Economia: tarifes (quotes de soci, matrícula, classes), mandats SEPA, rebuts i remeses
CREATE TABLE IF NOT EXISTS tarifes (
    id             INTEGER PRIMARY KEY,
    nom            TEXT NOT NULL,
    tipus          TEXT NOT NULL,
    tipus_soci     TEXT,
    assignatura_id INTEGER REFERENCES assignatures(id) ON DELETE RESTRICT,
    periodicitat   TEXT NOT NULL,
    import         REAL NOT NULL,
    estat          TEXT NOT NULL DEFAULT 'actiu',
    observacions   TEXT
);

-- Ordre de domiciliació (mandat SEPA) signada pel titular del compte
CREATE TABLE IF NOT EXISTS mandats (
    id             INTEGER PRIMARY KEY,
    persona_id     INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    titular        TEXT,
    iban           TEXT NOT NULL,
    bic            TEXT,
    referencia     TEXT UNIQUE,
    data_signatura TEXT NOT NULL,
    ultim_us       TEXT,
    estat          TEXT NOT NULL DEFAULT 'actiu',
    observacions   TEXT
);

CREATE TABLE IF NOT EXISTS remeses (
    id              INTEGER PRIMARY KEY,
    referencia      TEXT NOT NULL UNIQUE,
    data_creacio    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    data_cobrament  TEXT NOT NULL,
    estat           TEXT NOT NULL DEFAULT 'preparada',
    observacions    TEXT
);

CREATE TABLE IF NOT EXISTS rebuts (
    id             INTEGER PRIMARY KEY,
    numero         TEXT UNIQUE,
    persona_id     INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    concepte       TEXT NOT NULL,
    tipus          TEXT NOT NULL DEFAULT 'altre',
    periode        TEXT,
    data_emissio   TEXT NOT NULL,
    import         REAL NOT NULL DEFAULT 0,
    forma_pagament TEXT NOT NULL DEFAULT 'efectiu',
    estat          TEXT NOT NULL DEFAULT 'pendent',
    data_pagament  TEXT,
    mandat_id      INTEGER REFERENCES mandats(id) ON DELETE SET NULL,
    remesa_id      INTEGER REFERENCES remeses(id) ON DELETE SET NULL,
    clau           TEXT UNIQUE,
    observacions   TEXT
);

CREATE TABLE IF NOT EXISTS rebut_linies (
    id        INTEGER PRIMARY KEY,
    rebut_id  INTEGER NOT NULL REFERENCES rebuts(id) ON DELETE CASCADE,
    concepte  TEXT NOT NULL,
    import    REAL NOT NULL,
    alumne_id INTEGER REFERENCES alumnes(id) ON DELETE SET NULL,
    ordre     INTEGER NOT NULL DEFAULT 0
);

-- Loteria: sorteigs i talonaris de participacions repartits
CREATE TABLE IF NOT EXISTS loteries (
    id                      INTEGER PRIMARY KEY,
    nom                     TEXT NOT NULL,
    data_sorteig            TEXT,
    numeros                 TEXT NOT NULL,
    preu                    REAL NOT NULL,
    juga                    REAL NOT NULL,
    participacions_talonari INTEGER NOT NULL DEFAULT 50,
    estat                   TEXT NOT NULL DEFAULT 'actiu',
    peu                     TEXT,
    observacions            TEXT
);

CREATE TABLE IF NOT EXISTS talonaris (
    id              INTEGER PRIMARY KEY,
    loteria_id      INTEGER NOT NULL REFERENCES loteries(id) ON DELETE CASCADE,
    numero          INTEGER NOT NULL,
    persona_id      INTEGER REFERENCES persones(id) ON DELETE RESTRICT,
    data_entrega    TEXT,
    venudes         INTEGER,
    tornades        INTEGER,
    import_liquidat REAL,
    data_liquidacio TEXT,
    observacions    TEXT,
    UNIQUE (loteria_id, numero)
);

-- Societat: junta directiva, reunions i actes, documents i comunicats
CREATE TABLE IF NOT EXISTS carrecs (
    id           INTEGER PRIMARY KEY,
    persona_id   INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    carrec       TEXT NOT NULL,
    descripcio   TEXT,
    data_inici   TEXT NOT NULL,
    data_fi      TEXT,
    observacions TEXT
);

CREATE TABLE IF NOT EXISTS reunions (
    id                INTEGER PRIMARY KEY,
    tipus             TEXT NOT NULL,
    data              TEXT NOT NULL,
    hora              TEXT,
    hora_segona       TEXT,
    lloc              TEXT,
    data_convocatoria TEXT,
    ordre_del_dia     TEXT,
    assistents        TEXT,
    desenvolupament   TEXT,
    acords            TEXT,
    hora_fi           TEXT,
    estat             TEXT NOT NULL DEFAULT 'convocada',
    observacions      TEXT
);

CREATE TABLE IF NOT EXISTS documents (
    id             INTEGER PRIMARY KEY,
    titol          TEXT NOT NULL,
    tipus          TEXT NOT NULL,
    persona_id     INTEGER REFERENCES persones(id) ON DELETE SET NULL,
    data_document  TEXT,
    data_caducitat TEXT,
    fitxer_nom     TEXT,
    fitxer_tipus   TEXT,
    fitxer_mida    INTEGER,
    observacions   TEXT
);

-- El contingut dels fitxers va dins de la base de dades: així entra a les còpies (i al xifrat)
CREATE TABLE IF NOT EXISTS document_fitxers (
    document_id INTEGER PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
    contingut   BLOB NOT NULL
);

CREATE TABLE IF NOT EXISTS comunicats (
    id             INTEGER PRIMARY KEY,
    data           TEXT NOT NULL,
    destinataris   TEXT NOT NULL,
    n_destinataris INTEGER NOT NULL DEFAULT 0,
    adreces        TEXT,
    assumpte       TEXT NOT NULL,
    cos            TEXT,
    usuari         TEXT
);

-- Llibre de caixa: moviments apuntats a mà (els rebuts, les remeses i la loteria s'hi sumen sols)
CREATE TABLE IF NOT EXISTS moviments (
    id           INTEGER PRIMARY KEY,
    data         TEXT NOT NULL,
    tipus        TEXT NOT NULL,
    compte       TEXT NOT NULL DEFAULT 'banc',
    categoria    TEXT NOT NULL,
    concepte     TEXT NOT NULL,
    import       REAL NOT NULL,
    justificant  TEXT,
    observacions TEXT
);

-- Accés: usuaris i dos rols fixos (administrador i administratiu)
CREATE TABLE IF NOT EXISTS usuaris (
    id          INTEGER PRIMARY KEY,
    usuari      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    contrasenya TEXT NOT NULL,
    actiu       INTEGER NOT NULL DEFAULT 1,
    idioma      TEXT,
    ultim_acces TEXT,
    creat       TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS rols (
    id   INTEGER PRIMARY KEY,
    clau TEXT NOT NULL UNIQUE,
    nom  TEXT
);

CREATE TABLE IF NOT EXISTS rol_capacitats (
    rol_id    INTEGER NOT NULL REFERENCES rols(id) ON DELETE CASCADE,
    capacitat TEXT NOT NULL,
    PRIMARY KEY (rol_id, capacitat)
);

CREATE TABLE IF NOT EXISTS usuari_rols (
    usuari_id INTEGER NOT NULL REFERENCES usuaris(id) ON DELETE CASCADE,
    rol_id    INTEGER NOT NULL REFERENCES rols(id) ON DELETE CASCADE,
    PRIMARY KEY (usuari_id, rol_id)
);

CREATE TABLE IF NOT EXISTS auditoria (
    id          INTEGER PRIMARY KEY,
    data        TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    usuari_id   INTEGER,
    usuari      TEXT,
    accio       TEXT NOT NULL,
    entitat     TEXT,
    element_id  INTEGER,
    detall      TEXT
);

-- Tipus de soci: els defineix cada entitat (socis.tipus i tarifes.tipus_soci guarden l'id)
CREATE TABLE IF NOT EXISTS tipus_socis (
    id          INTEGER PRIMARY KEY,
    nom         TEXT NOT NULL,
    descripcio  TEXT,
    ordre       INTEGER,
    full_alta   INTEGER NOT NULL DEFAULT 1,
    estat       TEXT NOT NULL DEFAULT 'actiu',
    codi        TEXT,
    observacions TEXT
);

-- Uniformes i marxandatge: articles, compres i lliuraments (venda, préstec o gratuït), per talla
CREATE TABLE IF NOT EXISTS uniformes (
    id           INTEGER PRIMARY KEY,
    nom          TEXT NOT NULL,
    tipus        TEXT NOT NULL DEFAULT 'samarreta',
    talles       TEXT,
    preu         REAL,
    estat        TEXT NOT NULL DEFAULT 'actiu',
    observacions TEXT
);

CREATE TABLE IF NOT EXISTS uniforme_compres (
    id           INTEGER PRIMARY KEY,
    uniforme_id  INTEGER NOT NULL REFERENCES uniformes(id) ON DELETE RESTRICT,
    data         TEXT NOT NULL,
    talla        TEXT,
    quantitat    INTEGER NOT NULL,
    preu_unitat  REAL,
    proveidor    TEXT,
    observacions TEXT
);

CREATE TABLE IF NOT EXISTS uniforme_lliuraments (
    id           INTEGER PRIMARY KEY,
    uniforme_id  INTEGER NOT NULL REFERENCES uniformes(id) ON DELETE RESTRICT,
    persona_id   INTEGER NOT NULL REFERENCES persones(id) ON DELETE RESTRICT,
    talla        TEXT,
    quantitat    INTEGER NOT NULL DEFAULT 1,
    data         TEXT NOT NULL,
    tipus        TEXT NOT NULL DEFAULT 'venda',
    import       REAL,
    data_retorn  TEXT,
    observacions TEXT
);

CREATE INDEX IF NOT EXISTS idx_persones_cognoms ON persones(cognoms, nom);
CREATE INDEX IF NOT EXISTS idx_membres_agrup ON membres(agrupacio_id);
CREATE INDEX IF NOT EXISTS idx_grups_curs ON grups(curs_id);
CREATE INDEX IF NOT EXISTS idx_mg_grup ON matricula_grups(grup_id);
CREATE INDEX IF NOT EXISTS idx_cessions_instr ON cessions(instrument_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_data ON auditoria(data);
CREATE INDEX IF NOT EXISTS idx_rebuts_persona ON rebuts(persona_id);
CREATE INDEX IF NOT EXISTS idx_rebuts_estat ON rebuts(estat, forma_pagament);
CREATE INDEX IF NOT EXISTS idx_linies_rebut ON rebut_linies(rebut_id);
DROP INDEX IF EXISTS idx_mandats_persona; -- el cobreix idx_mandats_persona_estat_data
CREATE INDEX IF NOT EXISTS idx_talonaris_loteria ON talonaris(loteria_id);
CREATE INDEX IF NOT EXISTS idx_carrecs_persona ON carrecs(persona_id);
CREATE INDEX IF NOT EXISTS idx_moviments_data ON moviments(data);
CREATE INDEX IF NOT EXISTS idx_ucompres_uniforme ON uniforme_compres(uniforme_id);
CREATE INDEX IF NOT EXISTS idx_ulliuraments_uniforme ON uniforme_lliuraments(uniforme_id);
CREATE INDEX IF NOT EXISTS idx_ulliuraments_persona ON uniforme_lliuraments(persona_id);

-- Índexs addicionals de rendiment per a Teixit.
-- Revisats per a evitar duplicar els UNIQUE i els índexs ja presents a schema.sql.

-- Relacions i consultes habituals
CREATE INDEX IF NOT EXISTS idx_tutories_tutor ON tutories(tutor_id);
CREATE INDEX IF NOT EXISTS idx_membres_persona_estat ON membres(persona_id, estat);
CREATE INDEX IF NOT EXISTS idx_periodes_curs_ordre ON periodes(curs_id, ordre);
CREATE INDEX IF NOT EXISTS idx_grups_assignatura ON grups(assignatura_id);
CREATE INDEX IF NOT EXISTS idx_grups_professor ON grups(professor_id);
CREATE INDEX IF NOT EXISTS idx_grups_aula ON grups(aula_id);
CREATE INDEX IF NOT EXISTS idx_horaris_grup_dia ON horaris(grup_id, dia, hora_inici);
CREATE INDEX IF NOT EXISTS idx_matricules_curs_estat ON matricules(curs_id, estat);
CREATE INDEX IF NOT EXISTS idx_qualificacions_alumne ON qualificacions(alumne_id);
CREATE INDEX IF NOT EXISTS idx_qualificacions_periode ON qualificacions(periode_id);
CREATE INDEX IF NOT EXISTS idx_qualificacions_professor ON qualificacions(professor_id);
CREATE INDEX IF NOT EXISTS idx_manteniments_instrument_data ON manteniments(instrument_id, data);
CREATE INDEX IF NOT EXISTS idx_cessions_persona ON cessions(persona_id);

-- Economia
CREATE INDEX IF NOT EXISTS idx_tarifes_tipus_estat_periodicitat
    ON tarifes(tipus, estat, periodicitat);
CREATE INDEX IF NOT EXISTS idx_tarifes_assignatura ON tarifes(assignatura_id);
CREATE INDEX IF NOT EXISTS idx_mandats_persona_estat_data
    ON mandats(persona_id, estat, data_signatura DESC);
CREATE INDEX IF NOT EXISTS idx_rebuts_remesa ON rebuts(remesa_id);
CREATE INDEX IF NOT EXISTS idx_rebuts_mandat ON rebuts(mandat_id);
CREATE INDEX IF NOT EXISTS idx_rebuts_data ON rebuts(data_emissio);
CREATE INDEX IF NOT EXISTS idx_linies_alumne ON rebut_linies(alumne_id);

-- Altres relacions que poden créixer amb l'històric
CREATE INDEX IF NOT EXISTS idx_talonaris_persona ON talonaris(persona_id);
CREATE INDEX IF NOT EXISTS idx_documents_persona ON documents(persona_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_usuari ON auditoria(usuari_id);

