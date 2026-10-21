<?php
// Dades d'exemple (opcionals) per a provar el programa. Noms genèrics i dades inventades.
// El contingut (activitats, seccions, inventari, roba…) canvia segons el tipus d'entitat.
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Contingut d'exemple propi de cada tipus d'entitat: [valencià, castellà] en cada text */
function dades_exemple_tipus(string $tipus): array
{
    $d = [
        'cultural' => [
            'activitats' => [['Teatre', 'Teatro', 'Taller'], ['Ball tradicional', 'Baile tradicional', 'Taller'], ['Dibuix i pintura', 'Dibujo y pintura', 'Curs'], ['Ioga', 'Yoga', 'Curs']],
            'aules' => [['Sala 1', 'Sala 1', 15], ['Sala 2', 'Sala 2', 15], ["Saló d'actes", 'Salón de actos', 80]],
            'seccions' => [['Grup de teatre', 'Grupo de teatro', 'Teatre', 'Teatro'], ['Grup de ball', 'Grupo de baile', 'Ball', 'Baile'], ['Joves', 'Jóvenes', 'Joves', 'Jóvenes']],
            'funcions' => [['Interpretació', 'Interpretación'], ['Vestuari', 'Vestuario'], ['Escenografia', 'Escenografía'], ['So i llums', 'Sonido y luces'], ['Interpretació', 'Interpretación']],
            'inventari' => [['Equip de so', 'Equipo de sonido', 'Behringer', 'PK115A'], ['Projector', 'Proyector', 'Epson', 'EB-W06'], ['Taules plegables (10)', 'Mesas plegables (10)', null, null], ['Carpa 6×3', 'Carpa 6×3', null, null]],
            'manteniment' => ["Revisió de l'equip de so", 'Revisión del equipo de sonido'],
            'roba' => [["Samarreta de l'associació", 'Camiseta de la asociación', 'samarreta', 'S, M, L, XL, XXL', 10], ['Dessuadora', 'Sudadera', 'dessuadora', '8, 10, 12, 14, S, M, L, XL', 22], ['Gorra', 'Gorra', 'gorra', null, 8]],
            'proveidor' => ['Estampats La Plana', 'Estampados La Plana'],
            'vocal' => ['Vocal de cultura', 'Vocal de cultura'],
            'junta_odd' => ["Preparació de la mostra de teatre\nCalendari d'activitats del trimestre\nPrecs i preguntes", "Preparación de la muestra de teatro\nCalendario de actividades del trimestre\nRuegos y preguntas"],
            'comunicat' => ['Inscripcions als tallers del nou curs', 'Inscripciones a los talleres del nuevo curso'],
            'despesa' => ['material', 'Material per a la mostra de teatre', 'Material para la muestra de teatro', 85.5],
            'tipus_tutors' => 2,
        ],
        'falla' => [
            'activitats' => [['Teatre infantil', 'Teatro infantil', 'Taller'], ['Playback', 'Playback', 'Taller'], ['Indumentària', 'Indumentaria', 'Curs']],
            'aules' => [['Casal', 'Casal', 120], ['Sala de reunions', 'Sala de reuniones', 20]],
            'seccions' => [['Teatre', 'Teatro', 'Cultura', 'Cultura'], ['Playback', 'Playback', 'Festes', 'Fiestas'], ['Comissió infantil', 'Comisión infantil', 'Infantil', 'Infantil']],
            'funcions' => [['Actor', 'Actor'], ['Actriu', 'Actriz'], ['Vestuari', 'Vestuario'], ['Apuntadora', 'Apuntadora'], ['Escenografia', 'Escenografía']],
            'inventari' => [['Carpa 8×4', 'Carpa 8×4', null, null], ['Taules plegables (20)', 'Mesas plegables (20)', null, null], ['Equip de so', 'Equipo de sonido', 'Behringer', 'PK115A'], ['Paelles grans (3)', 'Paellas grandes (3)', null, null]],
            'manteniment' => ['Reparació de la lona de la carpa', 'Reparación de la lona de la carpa'],
            'roba' => [['Brusó', 'Blusón', 'bruso', '8, 10, 12, 14, S, M, L, XL, XXL', 25], ['Mocador', 'Pañuelo', 'mocador', null, 6], ['Samarreta de la comissió', 'Camiseta de la comisión', 'samarreta', 'S, M, L, XL, XXL', 10]],
            'proveidor' => ['Indumentària La Plana', 'Indumentaria La Plana'],
            'vocal' => ['Vocal de festes', 'Vocal de fiestas'],
            'junta_odd' => ["Contracte amb l'artista del monument\nProgramació de la setmana fallera\nPrecs i preguntes", "Contrato con el artista del monumento\nProgramación de la semana fallera\nRuegos y preguntas"],
            'comunicat' => ['Quota del cens i calendari de la setmana fallera', 'Cuota del censo y calendario de la semana fallera'],
            'despesa' => ['monument', "Primer pagament a l'artista del monument", 'Primer pago al artista del monumento', 3000],
            'tipus_tutors' => 0,
        ],
        'club' => [
            'activitats' => [['Iniciació', 'Iniciación', 'Esport'], ['Tecnificació', 'Tecnificación', 'Esport'], ['Psicomotricitat', 'Psicomotricidad', 'Esport']],
            'aules' => [['Pista 1', 'Pista 1', 30], ['Pista 2', 'Pista 2', 30], ['Gimnàs', 'Gimnasio', 25]],
            'seccions' => [['Sènior', 'Sénior', 'Sènior', 'Sénior'], ['Juvenil', 'Juvenil', 'Juvenil', 'Juvenil'], ['Infantil', 'Infantil', 'Infantil', 'Infantil']],
            'funcions' => [['Porter', 'Portero'], ['Defensa', 'Defensa'], ['Migcampista', 'Centrocampista'], ['Davanter', 'Delantero'], ['Defensa', 'Defensa']],
            'inventari' => [['Porteries portàtils (2)', 'Porterías portátiles (2)', null, null], ['Joc de pilotes (20)', 'Juego de balones (20)', 'Mikasa', null], ['Farmaciola', 'Botiquín', null, null], ['Marcador electrònic', 'Marcador electrónico', 'Bodet', 'BT6000']],
            'manteniment' => ['Canvi de les xarxes de les porteries', 'Cambio de las redes de las porterías'],
            'roba' => [['Equipació de joc', 'Equipación de juego', 'equipacio', '8, 10, 12, 14, S, M, L, XL', 30], ['Xandall', 'Chándal', 'xandall', '8, 10, 12, 14, S, M, L, XL', 35], ['Motxilla del club', 'Mochila del club', 'motxilla', null, 15]],
            'proveidor' => ['Esports La Plana', 'Deportes La Plana'],
            'vocal' => ['Vocal de la base', 'Vocal de la base'],
            'junta_odd' => ["Calendari de la temporada\nInscripcions a la federació\nPrecs i preguntes", "Calendario de la temporada\nInscripciones en la federación\nRuegos y preguntas"],
            'comunicat' => ['Calendari de partits de la temporada', 'Calendario de partidos de la temporada'],
            'despesa' => ['arbitratges', 'Arbitratges del mes', 'Arbitrajes del mes', 240],
            'tipus_tutors' => 2,
        ],
        'ampa' => [
            'activitats' => [['Robòtica', 'Robótica', 'Taller'], ['Anglés', 'Inglés', 'Idiomes'], ['Escacs', 'Ajedrez', 'Taller'], ['Multiesport', 'Multideporte', 'Esport']],
            'aules' => [["Aula d'informàtica", 'Aula de informática', 20], ['Biblioteca', 'Biblioteca', 25], ['Gimnàs', 'Gimnasio', 40]],
            'seccions' => [['Menjador', 'Comedor', 'Menjador', 'Comedor'], ['Festes', 'Fiestas', 'Festes', 'Fiestas'], ['Extraescolars', 'Extraescolares', 'Activitats', 'Actividades']],
            'funcions' => [['Coordinació', 'Coordinación'], ['Compres', 'Compras'], ['Comunicació', 'Comunicación'], ['Voluntariat', 'Voluntariado'], ['Voluntariat', 'Voluntariado']],
            'inventari' => [['Equip de so', 'Equipo de sonido', 'Behringer', 'PK115A'], ['Jocs de taula', 'Juegos de mesa', null, null], ['Taules plegables (10)', 'Mesas plegables (10)', null, null], ['Projector', 'Proyector', 'Epson', 'EB-W06']],
            'manteniment' => ['Revisió del projector', 'Revisión del proyector'],
            'roba' => [["Samarreta de l'AMPA", 'Camiseta del AMPA', 'samarreta', '4, 6, 8, 10, 12, S, M, L', 8], ['Gorra', 'Gorra', 'gorra', null, 6]],
            'proveidor' => ['Estampats La Plana', 'Estampados La Plana'],
            'vocal' => ["Vocal d'extraescolars", 'Vocal de extraescolares'],
            'junta_odd' => ["Organització de la festa de final de curs\nExtraescolars del trimestre\nPrecs i preguntes", "Organización de la fiesta de final de curso\nExtraescolares del trimestre\nRuegos y preguntas"],
            'comunicat' => ['Inscripcions a les extraescolars', 'Inscripciones en las extraescolares'],
            'despesa' => ['activitats_alumnat', "Material per a la festa de l'alumnat", 'Material para la fiesta del alumnado', 145],
            'tipus_tutors' => 0,
        ],
    ];
    return $d[$tipus] ?? $d['cultural'];
}

function carrega_dades_exemple(): void
{
    $tipus = tipus_entitat();
    $D = dades_exemple_tipus($tipus);
    $any = (int)date('n') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
    // Dades inventades: els adults tenen DNI i telèfon d'exemple, excepte un (per a veure l'avís de dades que falten)
    $comptador = 0;
    $persona = function (string $nom, string $cognoms, ?string $naix = null, bool $complet = true) use (&$comptador) {
        $comptador++;
        $adult = es_major_edat($naix);
        q('INSERT INTO persones(nom, cognoms, data_naixement, document, telefon, adreca, codi_postal, poblacio) VALUES(?, ?, ?, ?, ?, ?, ?, ?)', [
            $nom, $cognoms, $naix,
            $adult && $complet ? sprintf('%08dX', $comptador) : null,
            $adult && $complet ? sprintf('600 000 %03d', $comptador) : null,
            (idioma() === 'es' ? 'Calle Mayor, ' : 'Carrer Major, ') . $comptador,
            '12000',
            idioma() === 'es' ? 'Pueblo de Ejemplo' : 'Poble d\'Exemple',
        ]);
        return (int)db()->lastInsertId();
    };
    $id = fn() => (int)db()->lastInsertId();
    // Textos de les dades d'exemple en l'idioma triat en instal·lar
    $es = idioma() === 'es';
    $tr = fn(string $ca, string $esp) => $es ? $esp : $ca;
    // Els tipus de soci que s'han creat per a este tipus d'entitat, en ordre
    $tipusSocis = array_column(q('SELECT id FROM tipus_socis ORDER BY ordre')->fetchAll(), 'id');
    $tipusSoci = fn(int $i) => $tipusSocis[min($i, count($tipusSocis) - 1)];

    q('INSERT INTO cursos(nom, data_inici, data_fi, actual) VALUES(?, ?, ?, 1)', ["$any/" . ($any + 1), "$any-09-15", ($any + 1) . '-06-20']);
    $curs = $id();
    foreach ($es ? ['1.er trimestre', '2.º trimestre', '3.er trimestre'] : ['1r trimestre', '2n trimestre', '3r trimestre'] as $i => $p) {
        q('INSERT INTO periodes(curs_id, nom, ordre) VALUES(?, ?, ?)', [$curs, $p, $i + 1]);
    }

    // Activitats i cursos: activitats, aules, monitors i grups amb horari
    $assig = [];
    foreach ($D['activitats'] as $i => [$n, $nes, $tp]) {
        q('INSERT INTO assignatures(nom, tipus) VALUES(?, ?)', [$tr($n, $nes), $tp]);
        $assig[$i] = $id();
    }
    $aules = [];
    foreach ($D['aules'] as [$n, $nes, $cap]) {
        q('INSERT INTO aules(nom, capacitat) VALUES(?, ?)', [$tr($n, $nes), $cap]);
        $aules[] = $id();
    }
    $profs = [];
    foreach ([1, 2] as $n) {
        $p = $persona('Monitora' . $n, 'Exemple', (1975 + $n * 4) . '-05-1' . $n);
        q('INSERT INTO professors(persona_id, data_alta) VALUES(?, ?)', [$p, "$any-09-01"]);
        $profs[] = $id();
    }
    $grups = [];
    $horaris = [[[1, '17:00', '18:00'], [3, '17:00', '18:00']], [[2, '18:00', '19:00']], [[4, '18:00', '19:30']], [[5, '19:00', '20:30']]];
    foreach (array_keys($assig) as $i) {
        q('INSERT INTO grups(curs_id, assignatura_id, nom, professor_id, aula_id) VALUES(?,?,?,?,?)',
            [$curs, $assig[$i], $tr('Grup A', 'Grupo A'), $profs[$i % 2], $aules[$i % count($aules)]]);
        $grups[$i] = $id();
        foreach ($horaris[$i] as [$dia, $hi, $hf]) {
            q('INSERT INTO horaris(grup_id, dia, hora_inici, hora_fi) VALUES(?,?,?,?)', [$grups[$i], $dia, $hi, $hf]);
        }
    }

    $tutors = [];
    foreach ([1, 2, 3] as $n) {
        $tutors[] = $persona('Tutor' . $n, 'Exemple', (1978 + $n * 3) . '-0' . ($n + 2) . '-2' . $n);
    }
    $alumnesPersona = [];
    foreach (range(1, 6) as $n) {
        $p = $persona('Alumne' . $n, 'Exemple', ($any - 8 - $n) . '-0' . (($n % 9) + 1) . '-1' . $n);
        $alumnesPersona[] = $p;
        q('INSERT INTO alumnes(persona_id, data_alta) VALUES(?, ?)', [$p, "$any-09-10"]);
        $al = $id();
        q('INSERT INTO tutories(alumne_id, tutor_id, tipus_relacio, principal) VALUES(?, ?, ?, 1)', [$al, $tutors[($n - 1) % 3], ['mare', 'pare', 'tutor_legal'][($n - 1) % 3]]);
        q('INSERT INTO matricules(alumne_id, curs_id, data) VALUES(?, ?, ?)', [$al, $curs, "$any-09-10"]);
        $m = $id();
        q('INSERT INTO matricula_grups(matricula_id, grup_id) VALUES(?, ?)', [$m, $grups[0]]);
        q('INSERT INTO matricula_grups(matricula_id, grup_id) VALUES(?, ?)', [$m, $grups[$n % 2 ? 1 : 2]]);
        if ($n > 3 && isset($grups[3])) {
            q('INSERT INTO matricula_grups(matricula_id, grup_id) VALUES(?, ?)', [$m, $grups[3]]);
        }
        // Drets d'imatge: la majoria autoritzen, un no i un encara no ha tornat el full signat
        $consent = [1 => 'autoritza', 2 => 'autoritza', 3 => 'no_autoritza', 4 => 'autoritza', 5 => 'autoritza', 6 => 'pendent'][$n];
        q('UPDATE persones SET consentiment_imatge = ?, consentiment_data = ? WHERE id = ?', [$consent, $consent === 'pendent' ? null : "$any-09-12", $p]);
    }

    // Seccions, equips o comissions
    $agr = [];
    foreach ($D['seccions'] as $i => [$n, $nes, $tp, $tpes]) {
        q('INSERT INTO agrupacions(nom, tipus) VALUES(?, ?)', [$tr($n, $nes), $tr($tp, $tpes)]);
        $agr[$i] = $id();
    }
    $membres = [];
    // Anys d'antiguitat de cada membre (n'hi ha un que en fa 25 i un altre 50 este any, per a la llista d'insígnies)
    $anys = [25, 12, 8, 50, 3];
    foreach (range(1, 5) as $n) {
        $p = $persona('Membre' . $n, 'Exemple', [1980, 1989, 1991, 1960, 1995][$n - 1] . '-1' . ($n % 3) . '-0' . $n, $n !== 5);
        $membres[] = $p;
        $alta = ((int)date('Y') - $anys[$n - 1]) . '-0' . $n . '-15';
        q('INSERT INTO socis(persona_id, numero, tipus, data_alta) VALUES(?, ?, ?, ?)', [$p, (string)(100 + $n), $tipusSoci(0), $alta]);
        [$f, $fes] = $D['funcions'][$n - 1];
        q('INSERT INTO membres(persona_id, agrupacio_id, instrument_veu, data_alta) VALUES(?, ?, ?, ?)', [$p, $agr[0], $tr($f, $fes), $alta]);
        q("UPDATE persones SET consentiment_imatge = 'autoritza', consentiment_data = ? WHERE id = ?", [$alta, $p]);
    }
    q("INSERT INTO membres(persona_id, agrupacio_id, funcio, data_alta) VALUES(?, ?, 'responsable', ?)", [$tutors[0], $agr[0], "$any-01-01"]);
    q('INSERT INTO membres(persona_id, agrupacio_id, data_alta) VALUES(?, ?, ?)', [$tutors[1], $agr[1], "$any-01-01"]);
    foreach (array_slice($alumnesPersona, 3) as $p) {
        q('INSERT INTO membres(persona_id, agrupacio_id, data_alta) VALUES(?, ?, ?)', [$p, $agr[2], "$any-09-20"]);
    }
    foreach ($tutors as $i => $p) {
        q('INSERT INTO socis(persona_id, numero, tipus, data_alta) VALUES(?, ?, ?, ?)', [$p, (string)(200 + $i), $tipusSoci($D['tipus_tutors']), "$any-02-01"]);
    }
    if ($tipus === 'falla') {
        // En una falla, els xiquets i xiquetes també són del cens (categoria infantil)
        foreach (array_slice($alumnesPersona, 0, 3) as $i => $p) {
            q('INSERT INTO socis(persona_id, numero, tipus, data_alta) VALUES(?, ?, ?, ?)', [$p, (string)(300 + $i), $tipusSoci(2), "$any-03-20"]);
        }
    }
    if ($tipus === 'club') {
        // Llicències federatives: la majoria vigents i una que caduca d'ací a poc (per a veure l'avís)
        $fiTemporada = ($any + 1) . '-06-30';
        $n = 0;
        foreach (q("SELECT id FROM membres WHERE funcio = 'membre' ORDER BY id")->fetchAll() as $m) {
            $n++;
            q('UPDATE membres SET llicencia = ?, llicencia_caducitat = ? WHERE id = ?',
                [sprintf('CV-%06d', 104200 + $n), $n === 2 ? date('Y-m-d', strtotime('+12 days')) : $fiTemporada, $m['id']]);
        }
    }

    // Inventari: material, préstecs i un manteniment
    $instr = [];
    foreach ($D['inventari'] as $i => [$tp, $tpes, $ma, $mo]) {
        q('INSERT INTO instruments(inventari, tipus, marca, model, ubicacio) VALUES(?,?,?,?,?)', [sprintf('INV-%03d', $i + 1), $tr($tp, $tpes), $ma, $mo, $tr('Magatzem', 'Almacén')]);
        $instr[] = $id();
    }
    q('INSERT INTO cessions(instrument_id, persona_id, data_entrega, data_prevista, estat_entrega, fianca) VALUES(?,?,?,?,?,?)',
        [$instr[0], $membres[0], "$any-09-20", ($any + 1) . '-06-30', $tr('Bo', 'Bueno'), 50]);
    q('INSERT INTO cessions(instrument_id, persona_id, data_entrega, data_prevista, estat_entrega) VALUES(?,?,?,?,?)',
        [$instr[1], $tutors[1], "$any-09-20", date('Y-m-d', strtotime('-7 days')), $tr('Bo', 'Bueno')]);
    q('INSERT INTO manteniments(instrument_id, data, descripcio, cost) VALUES(?,?,?,?)', [$instr[0], "$any-09-05", $tr(...$D['manteniment']), 35]);

    // Economia: tarifes, domiciliacions (IBAN d'exemple), rebuts d'este trimestre i d'este mes, i la loteria de Nadal
    $nomTipus = fn(int $i) => (string)valor('SELECT nom FROM tipus_socis WHERE id = ?', [$tipusSoci($i)]);
    $tarifes = [[$tr('Quota', 'Cuota') . ' ' . mb_strtolower($nomTipus(0)), 'quota_soci', $tipusSoci(0), null, 'trimestral', 15]];
    if ($tipusSoci($D['tipus_tutors']) !== $tipusSoci(0)) {
        $tarifes[] = [$tr('Quota', 'Cuota') . ' ' . mb_strtolower($nomTipus($D['tipus_tutors'])), 'quota_soci', $tipusSoci($D['tipus_tutors']), null, 'trimestral', 10];
    }
    if ($tipus === 'falla') {
        $tarifes[] = [$tr('Quota infantil', 'Cuota infantil'), 'quota_soci', $tipusSoci(2), null, 'trimestral', 5];
    }
    $tarifes[] = [$tr('Matrícula', 'Matrícula'), 'matricula', null, null, 'unica', 20];
    foreach ($D['activitats'] as $i => [$n, $nes]) {
        $tarifes[] = [$tr($n, $nes), 'classe', null, $assig[$i], 'mensual', [18, 15, 12, 10][$i] ?? 10];
    }
    foreach ($tarifes as [$n, $tp, $ts, $as, $per, $imp]) {
        q('INSERT INTO tarifes(nom, tipus, tipus_soci, assignatura_id, periodicitat, import) VALUES(?,?,?,?,?,?)', [$n, $tp, $ts, $as, $per, $imp]);
    }
    desa_config('descompte_soci', '10');
    desa_config('descompte_germans', '5');
    // IBAN d'exemple vàlids (no són comptes reals)
    $iban = function (int $n): string {
        $ccc = '2100' . '0418' . '45' . sprintf('%010d', 2000000 + $n);
        $control = 98 - mod97($ccc . '142800');
        return 'ES' . sprintf('%02d', $control) . $ccc;
    };
    foreach ([$tutors[0], $tutors[1], $membres[0], $membres[1], $membres[2]] as $i => $p) {
        q('INSERT INTO mandats(persona_id, iban, data_signatura) VALUES(?, ?, ?)', [$p, $iban($i + 1), "$any-09-01"]);
        q('UPDATE mandats SET referencia = ? WHERE id = ?', [referencia_mandat($id()), $id()]);
    }
    $resum = [];
    $trimestre = 'T' . (int)ceil((int)date('n') / 3);
    crea_rebuts(calcula_quotes((int)date('Y'), $trimestre, $resum), date('Y-m-d'));
    $cursFila = fila('SELECT * FROM cursos WHERE id = ?', [$curs]);
    crea_rebuts(calcula_escola($cursFila, 'MAT', $resum), "$any-09-15");
    $mes = 'M' . date('Y-m');
    if (isset(periodes_escola($cursFila)[$mes])) {
        crea_rebuts(calcula_escola($cursFila, $mes, $resum), date('Y-m-d'));
    }
    // Les matrícules ja estan pagades; un soci ha pagat en efectiu i un rebut l'ha retornat el banc
    q("UPDATE rebuts SET estat = 'pagat', data_pagament = data_emissio WHERE tipus = 'matricula'");
    q("UPDATE rebuts SET estat = 'pagat', data_pagament = date('now','localtime') WHERE id = (SELECT MIN(id) FROM rebuts WHERE tipus = 'quota_soci' AND forma_pagament = 'efectiu')");
    q("UPDATE rebuts SET estat = 'retornat' WHERE id = (SELECT MAX(id) FROM rebuts WHERE tipus = 'quota_soci' AND forma_pagament = 'domiciliacio')");

    $nadal = (int)date('n') >= 2 ? (int)date('Y') : (int)date('Y') - 1;
    q('INSERT INTO loteries(nom, data_sorteig, numeros, preu, juga, participacions_talonari) VALUES(?, ?, ?, ?, ?, ?)',
        [$tr('Loteria de Nadal', 'Lotería de Navidad') . " $nadal", "$nadal-12-22", '12345, 67890', 5, 4, 50]);
    $lo = $id();
    foreach ([[$membres[0], 50, 0, 250], [$membres[1], 38, 12, 180], [$tutors[0], null, null, null], [$tutors[1], null, null, null]] as $i => [$p, $v, $tor, $liq]) {
        q('INSERT INTO talonaris(loteria_id, numero, persona_id, data_entrega, venudes, tornades, import_liquidat, data_liquidacio) VALUES(?,?,?,?,?,?,?,?)',
            [$lo, $i + 1, $p, "$nadal-10-15", $v, $tor, $liq, $liq !== null ? "$nadal-11-30" : null]);
    }

    // Junta actual i l'anterior, reunions, documents, certificat dels monitors, caixa i un comunicat
    $elegida = ((int)date('Y') - 2) . '-06-15';
    $anterior = ((int)date('Y') - 6) . '-06-10';
    foreach ([[$membres[0], 'presidencia'], [$tutors[1], 'vicepresidencia'], [$tutors[0], 'secretaria'], [$membres[1], 'tresoreria'], [$membres[2], 'vocal']] as [$p, $c]) {
        q('INSERT INTO carrecs(persona_id, carrec, descripcio, data_inici) VALUES(?, ?, ?, ?)', [$p, $c, $c === 'vocal' ? $tr(...$D['vocal']) : null, $elegida]);
    }
    foreach ([[$membres[3], 'presidencia'], [$membres[0], 'secretaria'], [$membres[1], 'tresoreria']] as [$p, $c]) {
        q('INSERT INTO carrecs(persona_id, carrec, data_inici, data_fi) VALUES(?, ?, ?, ?)', [$p, $c, $anterior, $elegida]);
    }
    desa_config('junta_anys_mandat', '4');
    // Correus d'exemple per a les llistes de comunicats (un adult sense correu, per a veure-ho)
    q("UPDATE persones SET email = lower(nom) || '@exemple.org' WHERE data_naixement <= date('now','localtime','-18 years') AND nom <> 'Membre5'");
    $lloc = $tipus === 'falla' ? 'Casal' : $tr('Local social', 'Local social');
    $quota = $tr('una quota de 60 € l\'any', 'una cuota de 60 € al año');
    q('INSERT INTO reunions(tipus, data, hora, hora_segona, lloc, data_convocatoria, ordre_del_dia, assistents, desenvolupament, acords, hora_fi, estat) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
        'assemblea_ordinaria', ((int)date('Y')) . '-03-20', '19:30', '20:00', $lloc, ((int)date('Y')) . '-03-05',
        $tr("Lectura i aprovació, si escau, de l'acta anterior\nEstat de comptes de l'exercici anterior\nPressupost de l'exercici\nPrecs i preguntes", "Lectura y aprobación, si procede, del acta anterior\nEstado de cuentas del ejercicio anterior\nPresupuesto del ejercicio\nRuegos y preguntas"),
        $tr('42 socis i sòcies presents i 8 representats.', '42 socios y socias presentes y 8 representados.'),
        $tr("La tresoreria presenta els comptes, que s'aproven per unanimitat. Es presenta el pressupost i s'obri un torn de preguntes.", 'La tesorería presenta las cuentas, que se aprueban por unanimidad. Se presenta el presupuesto y se abre un turno de preguntas.'),
        $tr("Aprovar l'acta de l'assemblea anterior.\nAprovar els comptes de l'exercici anterior.\nAprovar el pressupost de l'exercici, amb $quota.", "Aprobar el acta de la asamblea anterior.\nAprobar las cuentas del ejercicio anterior.\nAprobar el presupuesto del ejercicio, con $quota."),
        '21:15', 'celebrada',
    ]);
    q('INSERT INTO reunions(tipus, data, hora, lloc, data_convocatoria, ordre_del_dia, estat) VALUES(?,?,?,?,?,?,?)', [
        'junta', date('Y-m-d', strtotime('+10 days')), '20:30', $lloc, date('Y-m-d'), $tr(...$D['junta_odd']), 'convocada',
    ]);
    $doc = function (string $titol, string $tipus, ?string $data, ?string $caduca, ?int $persona = null, ?string $text = null) {
        q('INSERT INTO documents(titol, tipus, persona_id, data_document, data_caducitat) VALUES(?, ?, ?, ?, ?)', [$titol, $tipus, $persona, $data, $caduca]);
        $id = (int)db()->lastInsertId();
        if ($text !== null) {
            q('INSERT INTO document_fitxers(document_id, contingut) VALUES(?, ?)', [$id, $text]);
            q("UPDATE documents SET fitxer_nom = ?, fitxer_tipus = 'text/plain', fitxer_mida = ? WHERE id = ?", [mb_strtolower(preg_replace('/\W+/u', '-', $titol)) . '.txt', strlen($text), $id]);
        }
    };
    $doc($tr('Estatuts de l\'entitat', 'Estatutos de la entidad'), 'estatuts', '2010-05-20', null, null, $tr("Estatuts d'exemple.\n\nArticle 1. Denominació…", "Estatutos de ejemplo.\n\nArtículo 1. Denominación…"));
    $doc($tr('Reglament de règim intern', 'Reglamento de régimen interno'), 'reglament', '2018-03-15', null);
    $doc($tr('Assegurança de responsabilitat civil', 'Seguro de responsabilidad civil'), 'asseguranca', date('Y-m-d', strtotime('-11 months')), date('Y-m-d', strtotime('+30 days')));
    $doc($tr('Contracte de la monitora', 'Contrato de la monitora'), 'contracte', "$any-09-01", ($any + 1) . '-06-30', (int)valor('SELECT persona_id FROM professors WHERE id = ?', [$profs[0]]));
    q('UPDATE professors SET certificat_delictes = ? WHERE id = ?', ["$any-08-20", $profs[0]]);
    [$catDespesa, $despCa, $despEs, $despImport] = $D['despesa'];
    foreach ([
        [date('Y-m-d', strtotime('-40 days')), 'despesa', 'banc', 'federacio', $tr('Quota anual de la federació', 'Cuota anual de la federación'), 180],
        [date('Y-m-d', strtotime('-30 days')), 'despesa', 'banc', 'assegurances', $tr('Assegurança de responsabilitat civil', 'Seguro de responsabilidad civil'), 320],
        [date('Y-m-d', strtotime('-12 days')), 'despesa', 'caixa', $catDespesa, $tr($despCa, $despEs), $despImport],
        [date('Y-m-d', strtotime('-5 days')), 'ingres', 'banc', 'subvencions', $tr("Subvenció de l'ajuntament", 'Subvención del ayuntamiento'), 1500],
    ] as [$d, $tp, $cmp, $cat, $c, $imp]) {
        q('INSERT INTO moviments(data, tipus, compte, categoria, concepte, import) VALUES(?,?,?,?,?,?)', [$d, $tp, $cmp, $cat, $c, $imp]);
    }
    desa_config('caixa_saldo_' . date('Y') . '_banc', $tipus === 'falla' ? '6400' : '2350');
    desa_config('caixa_saldo_' . date('Y') . '_caixa', '180');
    q('INSERT INTO comunicats(data, destinataris, n_destinataris, adreces, assumpte, cos) VALUES(?,?,?,?,?,?)', [date('Y-m-d', strtotime('-3 days')), t('com.socis'), 8, null,
        $tr(...$D['comunicat']), null]);

    // Roba i articles: per talla i sense talla; compres, vendes i un préstec
    $unif = [];
    foreach ($D['roba'] as [$n, $nes, $tp, $talles, $preu]) {
        q('INSERT INTO uniformes(nom, tipus, talles, preu) VALUES(?, ?, ?, ?)', [$tr($n, $nes), $tp, $talles, $preu]);
        $unif[] = $id();
    }
    $tallesDe = fn(int $u) => talles_text(valor('SELECT talles FROM uniformes WHERE id = ?', [$unif[$u]]));
    foreach ($unif as $u => $uid) {
        $talles = $tallesDe($u) ?: [null];
        foreach ($talles as $i => $talla) {
            q('INSERT INTO uniforme_compres(uniforme_id, data, talla, quantitat, preu_unitat, proveidor) VALUES(?, ?, ?, ?, ?, ?)',
                [$uid, "$any-09-10", $talla, $talla === null ? 15 : 4 + ($i % 3) * 2, round((float)$D['roba'][$u][4] * 0.55, 2), $tr(...$D['proveidor'])]);
        }
    }
    $talles0 = $tallesDe(0) ?: [null];
    foreach ($membres as $i => $p) {
        q("INSERT INTO uniforme_lliuraments(uniforme_id, persona_id, talla, quantitat, data, tipus, import) VALUES(?, ?, ?, 1, ?, 'venda', ?)",
            [$unif[0], $p, $talles0[count($talles0) - 1 - ($i % min(3, count($talles0)))], date('Y-m-d', strtotime('-' . (20 + $i) . ' days')), $D['roba'][0][4]]);
    }
    $ultim = count($unif) - 1;
    foreach (array_slice($alumnesPersona, 0, 3) as $p) {
        q("INSERT INTO uniforme_lliuraments(uniforme_id, persona_id, talla, quantitat, data, tipus, import) VALUES(?, ?, ?, 1, ?, 'venda', ?)",
            [$unif[$ultim], $p, ($tallesDe($ultim) ?: [null])[0], date('Y-m-d', strtotime('-15 days')), $D['roba'][$ultim][4]]);
    }
    q("INSERT INTO uniforme_lliuraments(uniforme_id, persona_id, talla, quantitat, data, tipus) VALUES(?, ?, ?, 1, ?, 'prestec')",
        [$unif[1], $alumnesPersona[0], ($tallesDe(1) ?: [null])[0], date('Y-m-d', strtotime('-10 days'))]);
}
