<?php
// Economia: quotes de soci, escola (matrícula i classes), rebuts, remeses SEPA i documents
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Teixit fa rebuts, no factures: els rebuts no entren en el sistema de facturació (Verifactu).

/* ---------------- Utilitats ---------------- */

function euros($x): string
{
    return number_format(round((float)$x, 2), 2, ',', '.') . ' €';
}

/** Referència única d'un mandat: «CMP-000012» */
function referencia_mandat(int $id): string
{
    return 'CMP-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

/** Número correlatiu de rebut per any: «2026/0001» */
function numero_rebut_nou(?string $data = null): string
{
    $any = substr($data ?: date('Y-m-d'), 0, 4);
    $ultim = (int)valor('SELECT MAX(CAST(substr(numero, 6) AS INTEGER)) FROM rebuts WHERE numero LIKE ?', [$any . '/%']);
    return sprintf('%s/%04d', $any, $ultim + 1);
}

/** Si el rebut té línies, l'import és la suma */
function recalcula_rebut(int $id): void
{
    if (valor('SELECT COUNT(*) FROM rebut_linies WHERE rebut_id = ?', [$id])) {
        q('UPDATE rebuts SET import = ROUND((SELECT SUM(import) FROM rebut_linies WHERE rebut_id = ?), 2) WHERE id = ?', [$id, $id]);
    }
}

function mandat_actiu(int $personaId): ?array
{
    return fila("SELECT * FROM mandats WHERE persona_id = ? AND estat = 'actiu' ORDER BY data_signatura DESC LIMIT 1", [$personaId]);
}

/**
 * Posa l'IBAN de la domiciliació d'una persona (des de la seua fitxa o des d'una importació).
 * Si ja en té una amb un altre IBAN, es dona de baixa i se'n crea una de nova (cal signar l'ordre nova).
 * Un IBAN buit dona de baixa la domiciliació activa. $substitueix = false: no toca una domiciliació que ja hi és.
 * Retorna què ha passat: 'nova', 'canviada', 'igual', 'baixa', 'diferent' o null (res).
 */
function desa_iban_persona(int $persona, ?string $iban, ?string $data = null, bool $substitueix = true): ?string
{
    $actiu = mandat_actiu($persona);
    $nota = fn() => t('mandat.nota_substituida', date('d/m/Y'));
    if ($iban === null || $iban === '') {
        if ($actiu && $substitueix) {
            q("UPDATE mandats SET estat = 'baixa', observacions = trim(COALESCE(observacions, '') || char(10) || ?) WHERE id = ?", [t('mandat.nota_baixa', date('d/m/Y')), $actiu['id']]);
            audita('modifica', 'mandats', $actiu['id']);
            return 'baixa';
        }
        return null;
    }
    $iban = iban_entrada($iban);
    if ($actiu && iban_net($actiu['iban']) === $iban) {
        return 'igual';
    }
    if ($actiu) {
        if (!$substitueix) {
            return 'diferent';
        }
        q("UPDATE mandats SET estat = 'baixa', observacions = trim(COALESCE(observacions, '') || char(10) || ?) WHERE id = ?", [$nota(), $actiu['id']]);
    }
    q('INSERT INTO mandats(persona_id, iban, data_signatura, estat) VALUES(?, ?, ?, ?)', [$persona, $iban, $data ?: date('Y-m-d'), 'actiu']);
    $id = (int)db()->lastInsertId();
    q('UPDATE mandats SET referencia = ? WHERE id = ?', [referencia_mandat($id), $id]);
    audita('crea', 'mandats', $id);
    return $actiu ? 'canviada' : 'nova';
}

/** Validació comuna de l'IBAN de la fitxa: correcte i d'una persona major d'edat */
function valida_iban_persona(?string $iban, ?int $persona = null, ?string $naixement = null): ?string
{
    if ($iban === null || $iban === '') {
        return null;
    }
    if (!iban_valid($iban)) {
        return t('err.iban');
    }
    if ($persona !== null) {
        $naixement = valor('SELECT data_naixement FROM persones WHERE id = ?', [$persona]);
    }
    return es_major_edat($naixement) ? null : t('err.iban_menor');
}

/** Camp «IBAN» de les fitxes de persona i de soci: es desa com a domiciliació */
function camp_iban(callable $persona): array
{
    return [
        'tipus' => 'iban',
        'virtual' => 1,
        'ajuda' => t('ajuda.iban_persona'),
        'carrega' => fn(array $f) => ($p = $persona($f)) ? (mandat_actiu((int)$p)['iban'] ?? null) : null,
        'desa' => function (int $id, ?string $v, array $d) use ($persona): void {
            $r = desa_iban_persona((int)$persona(['id' => $id] + $d), $v);
            if (in_array($r, ['nova', 'canviada'], true)) {
                avis(t('mandat.avis_creada'), 'info');
            } elseif ($r === 'baixa') {
                avis(t('mandat.avis_baixa'), 'info');
            }
        },
    ];
}

/** «Nom Cognoms» (per als rebuts i les línies) */
function nom_natural(array $p): string
{
    return trim($p['nom'] . ' ' . ($p['cognoms'] ?? ''));
}

function es_soci_actiu(?int $personaId): bool
{
    return $personaId !== null && (bool)valor("SELECT COUNT(*) FROM socis WHERE persona_id = ? AND estat = 'actiu'", [$personaId]);
}

/** Qui paga les classes d'un alumne: el pagador indicat, el tutor principal, un altre tutor o ell mateix si és major d'edat */
function pagador_alumne(array $alumne): ?int
{
    if (!empty($alumne['pagador_id'])) {
        return (int)$alumne['pagador_id'];
    }
    $tutor = valor('SELECT tutor_id FROM tutories WHERE alumne_id = ? ORDER BY principal DESC, id LIMIT 1', [$alumne['id']]);
    if ($tutor) {
        return (int)$tutor;
    }
    return es_major_edat($alumne['data_naixement'] ?? null) ? (int)$alumne['persona_id'] : null;
}

/* ---------------- Períodes ---------------- */

/** Períodes per a les quotes de soci d'un any: codi => [etiqueta, periodicitat, inici, fi] */
function periodes_quotes(int $any): array
{
    $p = ['A' => [t('per.anual', $any), 'anual', "$any-01-01", "$any-12-31"]];
    for ($i = 1; $i <= 2; $i++) {
        $p["S$i"] = [t('per.semestre' . $i, $any), 'semestral', sprintf('%d-%02d-01', $any, $i * 6 - 5), date('Y-m-t', strtotime(sprintf('%d-%02d-01', $any, $i * 6)))];
    }
    for ($i = 1; $i <= 4; $i++) {
        $p["T$i"] = [t('per.trimestre' . $i, $any), 'trimestral', sprintf('%d-%02d-01', $any, $i * 3 - 2), date('Y-m-t', strtotime(sprintf('%d-%02d-01', $any, $i * 3)))];
    }
    for ($i = 1; $i <= 12; $i++) {
        $ini = sprintf('%d-%02d-01', $any, $i);
        $p[sprintf('M%02d', $i)] = [t('mes.' . $i) . ' ' . $any, 'mensual', $ini, date('Y-m-t', strtotime($ini))];
    }
    return $p;
}

/** Períodes de cobrament de l'escola d'un curs: codi => [etiqueta, què es cobra] */
function periodes_escola(array $curs): array
{
    $p = ['MAT' => [t('per.matricula', $curs['nom']), 'matricula']];
    if ($curs['data_inici'] && $curs['data_fi']) {
        $m = strtotime(date('Y-m-01', strtotime($curs['data_inici'])));
        $fi = strtotime($curs['data_fi']);
        for ($n = 0; $m <= $fi && $n < 24; $n++, $m = strtotime('+1 month', $m)) {
            $p['M' . date('Y-m', $m)] = [ucfirst(t('mes.' . date('n', $m))) . ' ' . date('Y', $m), 'mensual'];
        }
    }
    foreach (q('SELECT id, nom FROM periodes WHERE curs_id = ? ORDER BY ordre', [$curs['id']])->fetchAll() as $pe) {
        $p['P' . $pe['id']] = [$pe['nom'] . ' · ' . $curs['nom'], 'trimestral'];
    }
    $p['CURS'] = [t('per.curs', $curs['nom']), 'anual'];
    return $p;
}

/* ---------------- Càlcul dels rebuts ---------------- */

/**
 * Rebuts de les quotes de soci d'un període. Cada element: persona_id, nom, linies, total, clau, concepte, avisos.
 * $resum recull els socis que no tenen tarifa.
 */
function calcula_quotes(int $any, string $codi, array &$resum): array
{
    $periodes = periodes_quotes($any);
    [$etiqueta, $periodicitat, $inici, $fi] = $periodes[$codi];
    $tarifes = [];
    foreach (q("SELECT * FROM tarifes WHERE tipus = 'quota_soci' AND estat = 'actiu' AND periodicitat = ? ORDER BY id", [$periodicitat])->fetchAll() as $t) {
        $tarifes[$t['tipus_soci'] ?? ''] ??= $t;
    }
    $resum = ['sense_tarifa' => []];
    $rebuts = [];
    $socis = q("SELECT s.*, p.nom, p.cognoms FROM socis s JOIN persones p ON p.id = s.persona_id
        WHERE s.estat = 'actiu' AND (s.data_alta IS NULL OR s.data_alta <= ?) AND (s.data_baixa IS NULL OR s.data_baixa >= ?)
        ORDER BY CAST(s.numero AS INTEGER), s.numero, p.cognoms, p.nom", [$fi, $inici])->fetchAll();
    foreach ($socis as $s) {
        $tarifa = $tarifes[$s['tipus'] ?? ''] ?? $tarifes[''] ?? null;
        if (!$tarifa) {
            $resum['sense_tarifa'][] = nom_persona($s);
            continue;
        }
        if ((float)$tarifa['import'] <= 0) {
            continue;
        }
        $rebuts[] = [
            'persona_id' => (int)$s['persona_id'],
            'nom' => nom_persona($s),
            'concepte' => t('rebut.concepte_quota', $etiqueta),
            'tipus' => 'quota_soci',
            'periode' => $etiqueta,
            'clau' => "soci:{$s['id']}:$any-$codi",
            'linies' => [[$tarifa['nom'] . ($s['numero'] ? ' · ' . t('rebut.num_soci', $s['numero']) : ''), round((float)$tarifa['import'], 2), null]],
        ];
    }
    return completa_rebuts($rebuts);
}

/** Rebuts de l'escola d'un curs i període, un per pagador amb una línia per assignatura i alumne */
function calcula_escola(array $curs, string $codi, array &$resum): array
{
    $periodes = periodes_escola($curs);
    [$etiqueta, $que] = $periodes[$codi];
    $resum = ['sense_pagador' => [], 'sense_tarifa' => []];
    $pctSoci = (float)(config('descompte_soci') ?: 0);
    $pctGermans = (float)(config('descompte_germans') ?: 0);

    if ($que === 'matricula') {
        $tarifes = q("SELECT * FROM tarifes WHERE tipus = 'matricula' AND estat = 'actiu' ORDER BY id")->fetchAll();
    } else {
        $tarifes = q("SELECT * FROM tarifes WHERE tipus = 'classe' AND estat = 'actiu' AND periodicitat = ? ORDER BY id", [$que])->fetchAll();
    }
    $perAssignatura = [];
    $generals = [];
    foreach ($tarifes as $t) {
        if ($t['assignatura_id']) {
            $perAssignatura[(int)$t['assignatura_id']] ??= $t;
        } else {
            $generals[] = $t;
        }
    }

    $alumnes = q("SELECT al.*, p.nom, p.cognoms, p.data_naixement, m.id AS matricula_id FROM matricules m JOIN alumnes al ON al.id = m.alumne_id
        JOIN persones p ON p.id = al.persona_id WHERE m.curs_id = ? AND m.estat = 'actiu' AND al.estat = 'actiu' ORDER BY p.cognoms, p.nom", [$curs['id']])->fetchAll();

    // Carrega en una sola consulta els grups de totes les matrícules actives del curs.
    // Abans es feia una consulta per alumne (patró N+1).
    $grupsPerMatricula = [];
    $grupsCurs = q("SELECT mg.matricula_id, g.assignatura_id, a.nom
        FROM matricules m
        JOIN matricula_grups mg ON mg.matricula_id = m.id
        JOIN grups g ON g.id = mg.grup_id
        JOIN assignatures a ON a.id = g.assignatura_id
        WHERE m.curs_id = ? AND m.estat = 'actiu' AND g.estat = 'actiu'
        ORDER BY mg.matricula_id, a.nom", [$curs['id']])->fetchAll();
    foreach ($grupsCurs as $g) {
        $grupsPerMatricula[(int)$g['matricula_id']][] = $g;
    }

    $perPagador = [];
    foreach ($alumnes as $al) {
        $nom = nom_natural($al);
        $linies = [];
        foreach ($generals as $t) {
            $linies[] = [$t['nom'] . ' — ' . $nom, round((float)$t['import'], 2), (int)$al['id']];
        }
        $grups = $grupsPerMatricula[(int)$al['matricula_id']] ?? [];
        $vistes = [];
        foreach ($grups as $g) {
            $t = $perAssignatura[(int)$g['assignatura_id']] ?? null;
            if ($t && !isset($vistes[$t['id']])) {
                $vistes[$t['id']] = 1;
                $linies[] = [$t['nom'] . ' — ' . $nom, round((float)$t['import'], 2), (int)$al['id']];
            }
        }
        if (!$linies) {
            if ($tarifes) {
                $resum['sense_tarifa'][] = $nom;
            }
            continue;
        }
        $pagador = pagador_alumne($al);
        if (!$pagador) {
            $resum['sense_pagador'][] = $nom;
            continue;
        }
        $perPagador[$pagador][] = ['alumne' => $al, 'nom' => $nom, 'linies' => $linies, 'subtotal' => array_sum(array_column($linies, 1))];
    }

    $rebuts = [];
    foreach ($perPagador as $pagador => $fills) {
        // El germà amb més import paga sencer; els altres tenen el descompte de germans
        usort($fills, fn($a, $b) => $b['subtotal'] <=> $a['subtotal']);
        $linies = [];
        $pagadorSoci = es_soci_actiu($pagador);
        foreach ($fills as $i => $f) {
            array_push($linies, ...$f['linies']);
            if ($que === 'matricula') {
                continue; // els descomptes són de les classes
            }
            $parts = [];
            $pct = 0.0;
            if ($pctSoci > 0 && ($pagadorSoci || es_soci_actiu((int)$f['alumne']['persona_id']))) {
                $pct += $pctSoci;
                $parts[] = t('desc.soci', fmt_pct($pctSoci));
            }
            if ($pctGermans > 0 && $i > 0) {
                $pct += $pctGermans;
                $parts[] = t('desc.germans', fmt_pct($pctGermans));
            }
            if ((float)$f['alumne']['descompte_pct'] > 0) {
                $pct += (float)$f['alumne']['descompte_pct'];
                $parts[] = t('desc.alumne', fmt_pct((float)$f['alumne']['descompte_pct']));
            }
            $pct = min(100, $pct);
            if ($pct > 0) {
                $linies[] = [t('desc.linia', implode(', ', $parts)) . ' — ' . $f['nom'], -round($f['subtotal'] * $pct / 100, 2), (int)$f['alumne']['id']];
            }
        }
        $p = fila('SELECT nom, cognoms FROM persones WHERE id = ?', [$pagador]);
        $rebuts[] = [
            'persona_id' => $pagador,
            'nom' => nom_persona($p),
            'concepte' => $que === 'matricula' ? t('rebut.concepte_matricula', $curs['nom']) : t('rebut.concepte_escola', $etiqueta),
            'tipus' => $que === 'matricula' ? 'matricula' : 'classe',
            'periode' => $etiqueta,
            'clau' => "escola:$pagador:{$curs['id']}:$codi",
            'linies' => $linies,
        ];
    }
    usort($rebuts, fn($a, $b) => strcoll($a['nom'], $b['nom']));
    return completa_rebuts($rebuts);
}

function fmt_pct(float $x): string
{
    return rtrim(rtrim(number_format($x, 2, ',', ''), '0'), ',') . ' %';
}

/** Afig a cada rebut el total, la forma de pagament i si ja s'havia generat */
function completa_rebuts(array $rebuts): array
{
    if (!$rebuts) {
        return $rebuts;
    }

    // Carrega en bloc els mandats actius de les persones implicades.
    // Es processen per lots per no dependre del límit de paràmetres de SQLite.
    $persones = array_values(array_unique(array_map(fn($r) => (int)$r['persona_id'], $rebuts)));
    $mandats = [];
    foreach (array_chunk($persones, 500) as $lot) {
        $marcadors = implode(',', array_fill(0, count($lot), '?'));
        foreach (q("SELECT id, persona_id FROM mandats
            WHERE estat = 'actiu' AND persona_id IN ($marcadors)
            ORDER BY persona_id, data_signatura DESC", $lot)->fetchAll() as $m) {
            $mandats[(int)$m['persona_id']] ??= (int)$m['id'];
        }
    }

    // Carrega en bloc els rebuts que ja existeixen. «clau» és UNIQUE a l'esquema.
    $claus = array_values(array_unique(array_column($rebuts, 'clau')));
    $existents = [];
    foreach (array_chunk($claus, 500) as $lot) {
        $marcadors = implode(',', array_fill(0, count($lot), '?'));
        foreach (q("SELECT clau, numero FROM rebuts WHERE clau IN ($marcadors)", $lot)->fetchAll() as $e) {
            $existents[$e['clau']] = $e['numero'];
        }
    }

    foreach ($rebuts as &$r) {
        $r['total'] = round(array_sum(array_column($r['linies'], 1)), 2);
        $r['mandat_id'] = $mandats[(int)$r['persona_id']] ?? null;
        $r['forma'] = $r['mandat_id'] !== null ? 'domiciliacio' : 'efectiu';
        $r['existeix'] = $existents[$r['clau']] ?? null;
    }
    unset($r);
    return $rebuts;
}

/** Crea els rebuts que encara no existeixen; retorna quants n'ha creat i l'import */
function crea_rebuts(array $rebuts, string $data): array
{
    $n = 0;
    $total = 0.0;
    $propia = !db()->inTransaction();
    if ($propia) {
        db()->beginTransaction();
    }
    foreach ($rebuts as $r) {
        if ($r['existeix'] || $r['total'] <= 0) {
            continue;
        }
        q('INSERT INTO rebuts(numero, persona_id, concepte, tipus, periode, data_emissio, import, forma_pagament, estat, mandat_id, clau) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
            numero_rebut_nou($data), $r['persona_id'], $r['concepte'], $r['tipus'], $r['periode'], $data, $r['total'], $r['forma'], 'per_cobrar', $r['mandat_id'], $r['clau'],
        ]);
        $id = (int)db()->lastInsertId();
        foreach ($r['linies'] as $i => [$concepte, $import, $alumne]) {
            q('INSERT INTO rebut_linies(rebut_id, concepte, import, alumne_id, ordre) VALUES(?,?,?,?,?)', [$id, $concepte, $import, $alumne, $i + 1]);
        }
        $n++;
        $total += $r['total'];
    }
    if ($propia) {
        db()->commit();
    }
    return [$n, $total];
}

/* ---------------- Pantalla: generar rebuts ---------------- */

function pagina_genera_rebuts(): void
{
    requereix('economia.edit');
    $tipus = get('tipus');
    $cursos = q('SELECT * FROM cursos ORDER BY data_inici DESC, nom DESC')->fetchAll();
    $any = (int)(get('any') ?: date('Y'));
    $html = '';

    // Formularis per a triar què es genera
    $periodicitats = array_column(q("SELECT DISTINCT periodicitat FROM tarifes WHERE tipus = 'quota_soci' AND estat = 'actiu'")->fetchAll(), 'periodicitat');
    $opQuotes = '';
    foreach (periodes_quotes($any) as $codi => [$etq, $per]) {
        if (in_array($per, $periodicitats, true)) {
            $opQuotes .= '<option value="' . h($codi) . '"' . (get('periode') === $codi && $tipus === 'socis' ? ' selected' : '') . '>' . h($etq) . '</option>';
        }
    }
    $anys = '';
    for ($a = (int)date('Y') - 1; $a <= (int)date('Y') + 1; $a++) {
        $anys .= '<option' . ($a === $any ? ' selected' : '') . '>' . $a . '</option>';
    }
    $html .= '<div class="graella-informes graella-genera">';
    $html .= '<form class="informe" method="get"><input type="hidden" name="r" value="genera_rebuts"><input type="hidden" name="tipus" value="socis">'
        . '<h2>★ ' . h(t('genera.quotes')) . '</h2><p>' . h(t('genera.quotes_ajuda')) . '</p>';
    if ($opQuotes === '') {
        $html .= '<p class="avis atencio">' . h(t('genera.sense_tarifes_quota')) . ' <a href="' . h(url('llista', ['e' => 'tarifes'])) . '">' . h(t('ent.tarifes')) . '</a></p>';
    } else {
        $html .= '<label>' . h(t('genera.any')) . ' <select name="any" onchange="this.form.periode.value=\'\';this.form.submit()">' . $anys . '</select></label> '
            . '<label>' . h(t('genera.periode')) . ' <select name="periode">' . $opQuotes . '</select></label> <button>' . h(t('genera.previsualitza')) . '</button>';
    }
    $html .= '</form>';

    $cursSel = get('curs') !== '' ? (int)get('curs') : (int)(curs_actual()['id'] ?? 0);
    $opCursos = '';
    foreach ($cursos as $c) {
        $opCursos .= '<option value="' . $c['id'] . '"' . ((int)$c['id'] === $cursSel ? ' selected' : '') . '>' . h($c['nom']) . '</option>';
    }
    $cursFila = null;
    foreach ($cursos as $c) {
        if ((int)$c['id'] === $cursSel) {
            $cursFila = $c;
        }
    }
    $html .= '<form class="informe" method="get"><input type="hidden" name="r" value="genera_rebuts"><input type="hidden" name="tipus" value="escola">'
        . '<h2>🎓 ' . h(t('genera.escola')) . '</h2><p>' . h(t('genera.escola_ajuda')) . '</p>';
    if (!$cursFila) {
        $html .= '<p class="avis atencio">' . h(t('inici.sense_curs')) . '</p>';
    } else {
        $opEscola = '';
        foreach (periodes_escola($cursFila) as $codi => [$etq]) {
            $opEscola .= '<option value="' . h($codi) . '"' . (get('periode') === $codi && $tipus === 'escola' ? ' selected' : '') . '>' . h($etq) . '</option>';
        }
        $html .= '<label>' . h(t('ent1.cursos')) . ' <select name="curs" onchange="this.form.periode.value=\'\';this.form.submit()">' . $opCursos . '</select></label> '
            . '<label>' . h(t('genera.periode')) . ' <select name="periode">' . $opEscola . '</select></label> <button>' . h(t('genera.previsualitza')) . '</button>';
    }
    $html .= '</form></div>';

    // Previsualització i creació
    $codi = get('periode');
    $rebuts = null;
    $resum = [];
    $titolPeriode = '';
    if ($tipus === 'socis' && isset(periodes_quotes($any)[$codi])) {
        $rebuts = calcula_quotes($any, $codi, $resum);
        $titolPeriode = periodes_quotes($any)[$codi][0];
    } elseif ($tipus === 'escola' && $cursFila && isset(periodes_escola($cursFila)[$codi])) {
        $rebuts = calcula_escola($cursFila, $codi, $resum);
        $titolPeriode = periodes_escola($cursFila)[$codi][0];
    }
    if ($rebuts !== null) {
        $data = normalitza(['tipus' => 'date'], post('data', date('Y-m-d'))) ?? date('Y-m-d');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$n, $total] = crea_rebuts($rebuts, $data);
            audita('genera_rebuts', 'rebuts', null, "$tipus $titolPeriode: $n");
            avis(t('genera.fet', $n, euros($total)));
            redirigeix('llista', ['e' => 'rebuts', 'q' => $titolPeriode]);
        }
        $nous = array_filter($rebuts, fn($r) => !$r['existeix'] && $r['total'] > 0);
        $html .= '<section class="fill"><header><h2>' . h($titolPeriode) . '</h2></header>';
        foreach (['sense_tarifa' => 'genera.sense_tarifa', 'sense_pagador' => 'genera.sense_pagador'] as $k => $text) {
            if (!empty($resum[$k])) {
                $html .= '<div class="avis atencio">⚠ ' . h(t($text, count($resum[$k]))) . ' ' . h(implode(', ', array_slice($resum[$k], 0, 15))) . (count($resum[$k]) > 15 ? '…' : '') . '</div>';
            }
        }
        if (!$rebuts) {
            $html .= '<p class="buit">' . h(t('genera.cap')) . '</p>';
        } else {
            $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('genera.pagador')) . '</th><th>' . h(t('genera.detall')) . '</th><th class="num">'
                . h(t('camp.import')) . '</th><th>' . h(t('camp.forma_pagament')) . '</th><th></th></tr></thead><tbody>';
            foreach ($rebuts as $r) {
                $detall = implode('<br>', array_map(fn($l) => h($l[0]) . ' <span class="etiqueta">' . h(euros($l[1])) . '</span>', $r['linies']));
                $estat = $r['existeix'] ? '<span class="etiqueta">' . h(t('genera.ja_existeix', $r['existeix'])) . '</span>'
                    : ($r['total'] <= 0 ? '<span class="etiqueta">' . h(t('genera.zero')) . '</span>' : '<span class="estat-imp nova">' . h(t('genera.nou')) . '</span>');
                $html .= '<tr' . ($r['existeix'] ? ' class="apagat"' : '') . '><td><a href="' . h(url('fitxa', ['e' => 'persones', 'id' => $r['persona_id']])) . '">' . h($r['nom']) . '</a></td><td>' . $detall
                    . '</td><td class="num"><b>' . h(euros($r['total'])) . '</b></td><td>' . h(t('opt.' . $r['forma'])) . '</td><td>' . $estat . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
            $totalNous = array_sum(array_column($nous, 'total'));
            $sense = count(array_filter($nous, fn($r) => $r['forma'] === 'efectiu'));
            $html .= '<p>' . h(t('genera.resum', count($nous), euros($totalNous), count($rebuts) - count($nous))) . '</p>';
            if ($sense) {
                $html .= '<p class="nota">' . h(t('genera.sense_mandat', $sense)) . '</p>';
            }
            if ($nous) {
                $html .= '<form method="post" class="formulari"' . ' onsubmit="return confirm(\'' . h(t('genera.confirma', count($nous))) . '\')">' . csrf_camp()
                    . '<div class="camps"><div class="camp"><label>' . h(t('camp.data_emissio')) . '</label><input type="date" name="data" value="' . h(date('Y-m-d')) . '" required></div></div>'
                    . '<div class="accions"><button>🧾 ' . h(t('genera.crea', count($nous))) . '</button></div></form>';
            }
        }
        $html .= '</section>';
    }
    $html .= '<p class="nota">' . h(t('genera.nota')) . '</p>';
    pagina(t('genera.titol'), $html);
}

/* ---------------- Accions sobre un rebut ---------------- */

function accions_rebut(array $f): string
{
    $html = boto_document(url('rebut_doc', ['id' => $f['id']]), t('rebut.imprimeix'));
    if (!pot('economia.edit')) {
        return $html;
    }
    $form = fn(string $accio, string $contingut, string $classe = 'secundari', string $confirma = '') => '<form method="post" class="en-linia" action="'
        . h(url('rebut_accio', ['id' => $f['id'], 'accio' => $accio])) . '"' . ($confirma ? ' onsubmit="return confirm(\'' . h($confirma) . '\')"' : '') . '>' . csrf_camp() . $contingut . '</form>';
    if (in_array($f['estat'], ['per_cobrar', 'retornat'], true)) {
        $ops = '';
        foreach (['efectiu', 'transferencia', 'domiciliacio'] as $o) {
            $ops .= '<option value="' . $o . '"' . ($o === ($f['forma_pagament'] === 'domiciliacio' ? 'efectiu' : $f['forma_pagament']) ? ' selected' : '') . '>' . h(t('opt.' . $o)) . '</option>';
        }
        $html .= $form('pagat', '<select name="forma">' . $ops . '</select><input type="date" name="data" value="' . h(date('Y-m-d')) . '" required><button>✔ ' . h(t('rebut.marca_pagat')) . '</button>', '');
        $html .= $form('anulla', '<button class="secundari">' . h(t('rebut.anulla')) . '</button>', '', t('rebut.confirma_anulla'));
    }
    if ($f['estat'] === 'pagat' && $f['forma_pagament'] === 'domiciliacio') {
        $html .= $form('retornat', '<button class="secundari">↩ ' . h(t('rebut.retornat')) . '</button>', '', t('rebut.confirma_retornat'));
    }
    if ($f['estat'] === 'pagat' && $f['forma_pagament'] !== 'domiciliacio') {
        $html .= $form('desfes', '<button class="secundari">' . h(t('rebut.desfes_pagament')) . '</button>', '', t('rebut.confirma_desfes'));
    }
    return $html;
}

function accio_rebut(): void
{
    requereix('economia.edit');
    $id = (int)get('id');
    $r = fila('SELECT * FROM rebuts WHERE id = ?', [$id]);
    if (!$r || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigeix('llista', ['e' => 'rebuts']);
    }
    switch (get('accio')) {
        case 'pagat':
            $forma = in_array(post('forma'), ['efectiu', 'transferencia', 'domiciliacio'], true) ? post('forma') : 'efectiu';
            $data = normalitza(['tipus' => 'date'], post('data')) ?? date('Y-m-d');
            q("UPDATE rebuts SET estat = 'pagat', forma_pagament = ?, data_pagament = ? WHERE id = ?", [$forma, $data, $id]);
            avis(t('rebut.fet_pagat'));
            break;
        case 'anulla':
            q("UPDATE rebuts SET estat = 'anullat' WHERE id = ? AND estat IN ('per_cobrar', 'retornat')", [$id]);
            avis(t('rebut.fet_anullat'));
            break;
        case 'retornat':
            $nota = t('rebut.nota_retornat', date('d/m/Y'), (string)valor('SELECT referencia FROM remeses WHERE id = ?', [$r['remesa_id']]));
            q("UPDATE rebuts SET estat = 'retornat', data_pagament = NULL, observacions = trim(COALESCE(observacions, '') || char(10) || ?) WHERE id = ?", [$nota, $id]);
            avis(t('rebut.fet_retornat'), 'atencio');
            break;
        case 'desfes':
            q("UPDATE rebuts SET estat = 'per_cobrar', data_pagament = NULL WHERE id = ?", [$id]);
            break;
    }
    audita('rebut_' . get('accio'), 'rebuts', $id);
    redirigeix('fitxa', ['e' => 'rebuts', 'id' => $id]);
}

/* ---------------- Remeses SEPA ---------------- */

/** Rebuts que es poden posar en una remesa, amb el mandat que s'utilitzarà */
function rebuts_per_remesa(): array
{
    $files = q("SELECT r.*, " . NOM_PERSONA_SQL . " AS pagador FROM rebuts r JOIN persones p ON p.id = r.persona_id
        WHERE r.forma_pagament = 'domiciliacio' AND (r.estat = 'retornat' OR (r.estat = 'per_cobrar' AND r.remesa_id IS NULL))
        ORDER BY r.data_emissio, r.numero")->fetchAll();
    $amb = [];
    $sense = [];
    foreach ($files as $r) {
        $m = $r['mandat_id'] ? fila("SELECT * FROM mandats WHERE id = ? AND estat = 'actiu'", [$r['mandat_id']]) : null;
        $m = $m ?: mandat_actiu((int)$r['persona_id']);
        if ($m) {
            $r['mandat'] = $m;
            $amb[] = $r;
        } else {
            $sense[] = $r;
        }
    }
    return [$amb, $sense];
}

function pagina_remeses(): void
{
    requereix('economia.view');
    $creditor = creditor_sepa();
    [$amb, $sense] = rebuts_per_remesa();
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('economia.edit');
        $data = normalitza(['tipus' => 'date'], post('data_cobrament'));
        $triats = array_map('intval', (array)($_POST['rebut'] ?? []));
        $seleccio = array_values(array_filter($amb, fn($r) => in_array((int)$r['id'], $triats, true)));
        if (!$creditor) {
            $error = t('remesa.err_creditor');
        } elseif (!$data || $data <= date('Y-m-d')) {
            $error = t('remesa.err_data');
        } elseif (!$seleccio) {
            $error = t('remesa.err_buida');
        } else {
            db()->beginTransaction();
            q('INSERT INTO remeses(referencia, data_cobrament, observacions) VALUES(?, ?, ?)', ['tmp-' . bin2hex(random_bytes(6)), $data, post('observacions') ?: null]);
            $id = (int)db()->lastInsertId();
            q('UPDATE remeses SET referencia = ? WHERE id = ?', [sprintf('REM-%s-%03d', date('Ymd'), $id), $id]);
            foreach ($seleccio as $r) {
                q('UPDATE rebuts SET remesa_id = ?, mandat_id = ? WHERE id = ?', [$id, $r['mandat']['id'], $r['id']]);
            }
            audita('crea_remesa', 'remeses', $id, (string)count($seleccio));
            db()->commit();
            avis(t('remesa.creada', count($seleccio)));
            redirigeix('remesa', ['id' => $id]);
        }
    }

    $html = $error ? '<div class="avis error">' . h($error) . '</div>' : '';
    if (!$creditor) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('remesa.falta_creditor')) . (pot('config.manage') ? ' <a href="' . h(url('economia_config')) . '">' . h(t('economia.config')) . '</a>' : '') . '</div>';
    }

    // Remeses fetes
    $remeses = q("SELECT re.*, COUNT(r.id) AS n, COALESCE(SUM(r.import), 0) AS total FROM remeses re LEFT JOIN rebuts r ON r.remesa_id = re.id AND r.estat <> 'anullat'
        GROUP BY re.id ORDER BY re.id DESC LIMIT 30")->fetchAll();
    if ($remeses) {
        $html .= '<section class="fill"><header><h2>' . h(t('remesa.fetes')) . '</h2></header><div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.referencia'))
            . '</th><th>' . h(t('camp.data_cobrament')) . '</th><th class="num">' . h(t('ent.rebuts')) . '</th><th class="num">' . h(t('camp.import')) . '</th><th>' . h(t('camp.estat')) . '</th></tr></thead><tbody>';
        foreach ($remeses as $re) {
            $html .= '<tr data-href="' . h(url('remesa', ['id' => $re['id']])) . '"><td><a href="' . h(url('remesa', ['id' => $re['id']])) . '">' . h($re['referencia']) . '</a></td><td>'
                . h(data_llegible($re['data_cobrament'])) . '</td><td class="num">' . (int)$re['n'] . '</td><td class="num">' . h(euros($re['total'])) . '</td><td>' . h(t('opt.' . $re['estat'])) . '</td></tr>';
        }
        $html .= '</tbody></table></div></section>';
    }

    // Nova remesa
    $html .= '<section class="fill"><header><h2>' . h(t('remesa.nova')) . '</h2></header>';
    if (!$amb) {
        $html .= '<p class="buit petit">' . h(t('remesa.cap_pendent')) . '</p>';
    } else {
        $html .= '<form method="post" class="formulari">' . csrf_camp()
            . '<div class="taula-wrap"><table class="taula"><thead><tr><th><input type="checkbox" checked onclick="for (const c of this.closest(\'table\').querySelectorAll(\'tbody input\')) c.checked = this.checked"></th><th>'
            . h(t('camp.numero')) . '</th><th>' . h(t('genera.pagador')) . '</th><th>' . h(t('camp.concepte')) . '</th><th class="num">' . h(t('camp.import')) . '</th><th>' . h(t('ent1.mandats')) . '</th></tr></thead><tbody>';
        foreach ($amb as $r) {
            $html .= '<tr><td><input type="checkbox" name="rebut[]" value="' . (int)$r['id'] . '" checked></td><td><a href="' . h(url('fitxa', ['e' => 'rebuts', 'id' => $r['id']])) . '">' . h($r['numero']) . '</a>'
                . ($r['estat'] === 'retornat' ? ' <span class="etiqueta">' . h(t('opt.retornat')) . '</span>' : '') . '</td><td>' . h($r['pagador']) . '</td><td>' . h($r['concepte'])
                . '</td><td class="num">' . h(euros($r['import'])) . '</td><td>' . h(iban_amagat($r['mandat']['iban'])) . (empty($r['mandat']['ultim_us']) ? ' <span class="etiqueta">' . h(t('remesa.primer')) . '</span>' : '') . '</td></tr>';
        }
        $html .= '</tbody></table></div><div class="camps">'
            . '<div class="camp"><label>' . h(t('camp.data_cobrament')) . ' <span class="obl">*</span></label><input type="date" name="data_cobrament" required min="' . h(date('Y-m-d', strtotime('+1 day'))) . '" value="'
            . h(post('data_cobrament', date('Y-m-d', strtotime('+7 days')))) . '"><small>' . h(t('remesa.data_ajuda')) . '</small></div>'
            . '<div class="camp"><label>' . h(t('camp.observacions')) . '</label><input name="observacions" value="' . h(post('observacions')) . '"></div></div>'
            . '<div class="accions"><button' . ($creditor && pot('economia.edit') ? '' : ' disabled') . '>🏦 ' . h(t('remesa.crea')) . '</button></div></form>';
    }
    if ($sense) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('remesa.sense_mandat', count($sense))) . '<ul>';
        foreach (array_slice($sense, 0, 20) as $r) {
            $html .= '<li><a href="' . h(url('fitxa', ['e' => 'rebuts', 'id' => $r['id']])) . '">' . h($r['numero']) . '</a> · ' . h($r['pagador'])
                . ' · <a href="' . h(url('edita', ['e' => 'mandats', 'f[persona_id]' => $r['persona_id']])) . '">' . h(t('remesa.crea_mandat')) . '</a></li>';
        }
        $html .= '</ul></div>';
    }
    $html .= '</section><p class="nota">' . h(t('remesa.nota')) . '</p>';
    pagina(t('menu.remeses'), $html);
}

function cobraments_remesa(int $id): array
{
    $files = q("SELECT r.*, " . NOM_PERSONA_SQL . " AS pagador FROM rebuts r JOIN persones p ON p.id = r.persona_id
        WHERE r.remesa_id = ? AND r.estat <> 'anullat' ORDER BY r.numero", [$id])->fetchAll();
    $c = [];
    foreach ($files as $r) {
        $c[] = ['rebut' => $r, 'mandat' => fila('SELECT * FROM mandats WHERE id = ?', [$r['mandat_id']]) ?? [], 'deutor' => $r['pagador']];
    }
    return $c;
}

function pagina_remesa(): void
{
    requereix('economia.view');
    $id = (int)get('id');
    $re = fila('SELECT * FROM remeses WHERE id = ?', [$id]);
    if (!$re) {
        redirigeix('remeses');
    }
    $cobraments = cobraments_remesa($id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('economia.edit');
        $accio = get('accio');
        db()->beginTransaction();
        if ($accio === 'cobrada' && $re['estat'] === 'preparada') {
            q("UPDATE rebuts SET estat = 'pagat', data_pagament = ? WHERE remesa_id = ? AND estat IN ('per_cobrar', 'retornat')", [$re['data_cobrament'], $id]);
            q("UPDATE mandats SET ultim_us = ? WHERE id IN (SELECT mandat_id FROM rebuts WHERE remesa_id = ?)", [$re['data_cobrament'], $id]);
            q("UPDATE remeses SET estat = 'cobrada' WHERE id = ?", [$id]);
            avis(t('remesa.fet_cobrada'));
        } elseif ($accio === 'desfes' && $re['estat'] === 'preparada') {
            q('UPDATE rebuts SET remesa_id = NULL WHERE remesa_id = ?', [$id]);
            q('DELETE FROM remeses WHERE id = ?', [$id]);
            audita('desfes_remesa', 'remeses', $id);
            db()->commit();
            avis(t('remesa.fet_desfeta'));
            redirigeix('remeses');
        } elseif ($accio === 'treu' && $re['estat'] === 'preparada') {
            q('UPDATE rebuts SET remesa_id = NULL WHERE id = ? AND remesa_id = ?', [(int)post('rebut'), $id]);
        }
        audita('remesa_' . $accio, 'remeses', $id);
        db()->commit();
        redirigeix('remesa', ['id' => $id]);
    }

    $total = array_sum(array_map(fn($c) => (float)$c['rebut']['import'], $cobraments));
    $html = '<div class="accions">';
    $html .= '<a class="boto" href="' . h(url('remesa_xml', ['id' => $id])) . '">⬇ ' . h(t('remesa.descarrega')) . '</a>';
    if (pot('economia.edit') && $re['estat'] === 'preparada') {
        $html .= '<form method="post" action="' . h(url('remesa', ['id' => $id, 'accio' => 'cobrada'])) . '" onsubmit="return confirm(\'' . h(t('remesa.confirma_cobrada')) . '\')">' . csrf_camp()
            . '<button class="secundari">✔ ' . h(t('remesa.marca_cobrada')) . '</button></form>';
        $html .= '<form method="post" action="' . h(url('remesa', ['id' => $id, 'accio' => 'desfes'])) . '" onsubmit="return confirm(\'' . h(t('remesa.confirma_desfes')) . '\')">' . csrf_camp()
            . '<button class="perill">' . h(t('remesa.desfes')) . '</button></form>';
    }
    $html .= '<a class="boto secundari" href="' . h(url('remeses')) . '">' . h(t('torna_llista')) . '</a></div>';
    $html .= '<dl class="fitxa"><dt>' . h(t('camp.data_cobrament')) . '</dt><dd>' . h(data_llegible($re['data_cobrament'])) . '</dd><dt>' . h(t('camp.estat')) . '</dt><dd>' . h(t('opt.' . $re['estat']))
        . '</dd><dt>' . h(t('ent.rebuts')) . '</dt><dd>' . count($cobraments) . '</dd><dt>' . h(t('camp.import')) . '</dt><dd><b>' . h(euros($total)) . '</b></dd>'
        . ($re['observacions'] ? '<dt>' . h(t('camp.observacions')) . '</dt><dd>' . h($re['observacions']) . '</dd>' : '') . '</dl>';
    $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.numero')) . '</th><th>' . h(t('genera.pagador')) . '</th><th>' . h(t('camp.concepte')) . '</th><th class="num">'
        . h(t('camp.import')) . '</th><th>' . h(t('ent1.mandats')) . '</th><th>' . h(t('camp.estat')) . '</th><th></th></tr></thead><tbody>';
    foreach ($cobraments as $c) {
        $r = $c['rebut'];
        $treu = pot('economia.edit') && $re['estat'] === 'preparada'
            ? '<form method="post" action="' . h(url('remesa', ['id' => $id, 'accio' => 'treu'])) . '">' . csrf_camp() . '<input type="hidden" name="rebut" value="' . (int)$r['id'] . '"><button class="petit secundari">' . h(t('remesa.treu')) . '</button></form>' : '';
        $html .= '<tr><td><a href="' . h(url('fitxa', ['e' => 'rebuts', 'id' => $r['id']])) . '">' . h($r['numero']) . '</a></td><td>' . h($c['deutor']) . '</td><td>' . h($r['concepte']) . '</td><td class="num">'
            . h(euros($r['import'])) . '</td><td>' . h(iban_amagat($c['mandat']['iban'] ?? '')) . ' <small>' . h($c['mandat']['referencia'] ?? '') . '</small></td><td>' . h(t('opt.' . $r['estat'])) . '</td><td>' . $treu . '</td></tr>';
    }
    $html .= '</tbody></table></div><p class="nota">' . h(t($re['estat'] === 'preparada' ? 'remesa.nota_preparada' : 'remesa.nota_cobrada')) . '</p>';
    pagina(t('ent1.remeses') . ' ' . $re['referencia'], $html);
}

function descarrega_remesa_xml(): never
{
    requereix('economia.view');
    $re = fila('SELECT * FROM remeses WHERE id = ?', [(int)get('id')]);
    $creditor = creditor_sepa();
    if (!$re || !$creditor) {
        avis(t('remesa.err_creditor'), 'error');
        redirigeix('remeses');
    }
    $xml = xml_remesa($re, cobraments_remesa((int)$re['id']), $creditor);
    audita('descarrega_remesa', 'remeses', $re['id']);
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $re['referencia'] . '.xml"');
    echo $xml;
    exit;
}

/* ---------------- Documents: rebuts i mandat SEPA ---------------- */

function pagina_rebut_doc(): void
{
    requereix('economia.view');
    if (get('id') !== '') {
        $rebuts = q('SELECT * FROM rebuts WHERE id = ?', [(int)get('id')])->fetchAll();
    } else {
        // Rebuts per cobrar en mà (efectiu o transferència), si es vol d'un període
        $params = [];
        $where = "estat IN ('per_cobrar', 'retornat') AND forma_pagament <> 'domiciliacio'";
        if (get('periode') !== '') {
            $where .= ' AND periode = ?';
            $params[] = get('periode');
        }
        $rebuts = q("SELECT * FROM rebuts WHERE $where ORDER BY numero", $params)->fetchAll();
    }
    document_inici(t('rebut.titol_doc'), t('rebut.n_rebuts', count($rebuts)), t('rebut.consell'));
    if (!$rebuts) {
        echo '<p class="buit">' . h(t('cap_resultat')) . '</p>';
    }
    $peu = config('rebut_peu');
    foreach (array_chunk($rebuts, 2) as $parell) {
        echo '<section class="full document full-rebuts">';
        foreach ($parell as $r) {
            $p = fila('SELECT * FROM persones WHERE id = ?', [$r['persona_id']]);
            $linies = q('SELECT * FROM rebut_linies WHERE rebut_id = ? ORDER BY ordre, id', [$r['id']])->fetchAll();
            echo '<article class="rebut">' . capcalera_societat();
            echo '<div class="rebut-cap"><h2>' . h(t('rebut.titol')) . '</h2><p><b>' . h(t('rebut.num', $r['numero'])) . '</b><br>' . h(data_llarga($r['data_emissio'])) . '</p></div>';
            echo '<table class="formulari-paper"><tr><th>' . h(t('rebut.pagador')) . '</th><td><b>' . h(nom_natural($p)) . '</b>' . ($p['document'] ? ' · ' . h($p['document']) : '') . '</td></tr>'
                . '<tr><th>' . h(t('camp.concepte')) . '</th><td>' . h($r['concepte']) . '</td></tr></table>';
            if ($linies) {
                echo '<table class="taula-llistat"><tbody>';
                foreach ($linies as $l) {
                    echo '<tr><td>' . h($l['concepte']) . '</td><td class="num">' . h(euros($l['import'])) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            echo '<div class="rebut-total"><span>' . h(t('rebut.total')) . '</span><b>' . h(euros($r['import'])) . '</b></div>';
            if ($r['estat'] === 'pagat') {
                echo '<p class="segell-pagat">' . h(t('rebut.pagat_el', data_llegible($r['data_pagament']), t('opt.' . $r['forma_pagament']))) . '</p>';
            } elseif ($r['estat'] === 'anullat') {
                echo '<p class="segell-pagat anullat">' . h(t('opt.anullat')) . '</p>';
            } else {
                echo '<p class="rebut-forma">' . h(t('camp.forma_pagament')) . ': ' . h(t('opt.' . $r['forma_pagament'])) . '</p><div class="rebut-signa">' . h(t('rebut.segell')) . '</div>';
            }
            if ($peu) {
                echo '<p class="text-legal">' . h($peu) . '</p>';
            }
            echo '</article>';
        }
        echo '</section>';
    }
    audita('imprimeix_rebuts', 'rebuts', null, (string)count($rebuts));
    document_fi();
}

function pagina_mandat_doc(): void
{
    requereix('economia.view');
    $m = fila('SELECT * FROM mandats WHERE id = ?', [(int)get('id')]);
    if (!$m) {
        redirigeix('llista', ['e' => 'mandats']);
    }
    $p = fila('SELECT * FROM persones WHERE id = ?', [$m['persona_id']]);
    $nif = config('sepa_nif') ?: config('societat_cif');
    $creditor = [
        'nom' => config('sepa_nom') ?: config('societat_nom'),
        'id' => $nif ? identificador_creditor($nif, config('sepa_sufix') ?: '000') : null,
        'adreca' => implode(', ', array_filter([config('societat_adreca'), config('societat_poblacio')])),
    ];
    $adreca = implode(', ', array_filter([$p['adreca'], trim($p['codi_postal'] . ' ' . $p['poblacio']), $p['provincia']]));
    document_inici(t('mandat.titol'), t('mandat.titol'), t('mandat.consell'));
    echo '<section class="full document">' . capcalera_societat();
    echo '<h2 class="centrat">' . h(t('mandat.titol')) . '<br><small>SEPA Direct Debit Mandate</small></h2>';
    echo '<table class="formulari-paper"><tr><th>' . h(t('mandat.referencia')) . '</th><td colspan="3"><b>' . h($m['referencia']) . '</b></td></tr></table>';
    echo '<h3>' . h(t('mandat.creditor')) . '</h3><table class="formulari-paper">'
        . '<tr><th>' . h(t('camp.nom')) . '</th><td colspan="3">' . o_linia($creditor['nom']) . '</td></tr>'
        . '<tr><th>' . h(t('mandat.id_creditor')) . '</th><td>' . o_linia($creditor['id']) . '</td><th>' . h(t('camp.adreca')) . '</th><td>' . o_linia($creditor['adreca']) . '</td></tr></table>';
    echo '<h3>' . h(t('mandat.deutor')) . '</h3><table class="formulari-paper">'
        . '<tr><th>' . h(t('mandat.titular')) . '</th><td colspan="3"><b>' . h($m['titular'] ?: nom_natural($p)) . '</b></td></tr>'
        . '<tr><th>' . h(t('camp.document')) . '</th><td>' . o_linia($m['titular'] ? null : $p['document']) . '</td><th>' . h(t('camp.adreca')) . '</th><td>' . o_linia($m['titular'] ? null : $adreca) . '</td></tr>'
        . '<tr><th>IBAN</th><td><b>' . h(iban_format($m['iban'])) . '</b></td><th>BIC</th><td>' . o_linia($m['bic']) . '</td></tr>'
        . '<tr><th>' . h(t('mandat.tipus_pagament')) . '</th><td colspan="3">☒ ' . h(t('mandat.recurrent')) . ' &nbsp; ☐ ' . h(t('mandat.unic')) . '</td></tr></table>';
    echo '<p class="justificat">' . h(t('mandat.text_legal', $creditor['nom'] ?: '…')) . '</p>';
    echo '<p class="text-legal">' . h(t('mandat.text_proteccio', $creditor['nom'] ?: '…')) . '</p>';
    echo '<p class="lloc-data">' . h(config('societat_poblacio') ?: '') . ', ' . h(data_llarga($m['data_signatura'])) . '</p>';
    echo '<footer><div class="signatures"><div>' . h(t('mandat.signatura')) . '</div></div></footer>';
    echo '<p class="text-legal">' . h(t('mandat.nota_retorn')) . '</p></section>';
    document_fi();
}

/* ---------------- Configuració econòmica ---------------- */

function pagina_economia_config(): void
{
    requereix('config.manage');
    $camps = ['sepa_nom', 'sepa_nif', 'sepa_iban', 'sepa_bic', 'sepa_sufix', 'sepa_format', 'descompte_soci', 'descompte_germans', 'rebut_peu'];
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $v = [];
        foreach ($camps as $c) {
            $v[$c] = trim((string)post($c));
        }
        $v['sepa_iban'] = $v['sepa_iban'] !== '' ? iban_net($v['sepa_iban']) : '';
        $v['sepa_bic'] = strtoupper($v['sepa_bic']);
        foreach (['descompte_soci', 'descompte_germans'] as $c) {
            $v[$c] = $v[$c] === '' ? '' : (string)(float)str_replace(',', '.', $v[$c]);
        }
        if ($v['sepa_iban'] !== '' && !iban_valid($v['sepa_iban'])) {
            $error = t('err.iban');
        } elseif ($v['sepa_bic'] !== '' && !bic_valid($v['sepa_bic'])) {
            $error = t('err.bic');
        } elseif ((float)$v['descompte_soci'] < 0 || (float)$v['descompte_soci'] > 100 || (float)$v['descompte_germans'] < 0 || (float)$v['descompte_germans'] > 100) {
            $error = t('err.percentatge');
        } else {
            foreach ($v as $c => $x) {
                desa_config($c, $x === '' ? null : $x);
            }
            audita('config_economia');
            avis(t('desat'));
            redirigeix('economia_config');
        }
    }
    $val = fn(string $c) => $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)post($c) : (string)config($c);
    $camp = fn(string $c, string $ajuda = '', string $extra = '') => '<div class="camp"><label for="c_' . $c . '">' . h(t('camp.' . $c)) . '</label><input id="c_' . $c . '" name="' . $c . '" value="'
        . h($c === 'sepa_iban' && $val($c) !== '' ? iban_format($val($c)) : $val($c)) . '"' . $extra . '>' . ($ajuda ? '<small>' . h($ajuda) . '</small>' : '') . '</div>';
    $creditor = creditor_sepa();
    $nif = config('sepa_nif') ?: config('societat_cif');

    $html = $error ? '<div class="avis error">' . h($error) . '</div>' : '';
    $html .= '<form method="post" class="formulari">' . csrf_camp();
    $html .= '<fieldset class="camp ample"><legend>' . h(t('economia.config_banc')) . '</legend><small>' . h(t('economia.config_banc_ajuda')) . '</small><div class="camps">'
        . $camp('sepa_nom', t('ajuda.sepa_nom', (string)config('societat_nom'))) . $camp('sepa_nif', t('ajuda.sepa_nif', (string)config('societat_cif')))
        . $camp('sepa_iban', '', ' placeholder="ES00 0000 0000 0000 0000 0000"') . $camp('sepa_bic', t('ajuda.bic')) . $camp('sepa_sufix', t('ajuda.sepa_sufix'), ' maxlength="3" placeholder="000"')
        . '<div class="camp"><label for="c_sepa_format">' . h(t('camp.sepa_format')) . '</label><select id="c_sepa_format" name="sepa_format">';
    foreach (FORMATS_SEPA as $f) {
        $html .= '<option' . ($val('sepa_format') === $f ? ' selected' : '') . '>' . $f . '</option>';
    }
    $html .= '</select><small>' . h(t('ajuda.sepa_format')) . '</small></div>'
        . '<div class="camp"><label>' . h(t('mandat.id_creditor')) . '</label><p class="valor-calculat">' . h($nif ? identificador_creditor($nif, config('sepa_sufix') ?: '000') : '—') . '</p><small>' . h(t('ajuda.id_creditor')) . '</small></div>'
        . '</div></fieldset>';
    $html .= '<fieldset class="camp ample"><legend>' . h(t('economia.config_descomptes')) . '</legend><small>' . h(t('economia.config_descomptes_ajuda')) . '</small><div class="camps">'
        . $camp('descompte_soci', '', ' inputmode="decimal"') . $camp('descompte_germans', '', ' inputmode="decimal"') . '</div></fieldset>';
    $html .= '<fieldset class="camp ample"><legend>' . h(t('economia.config_rebuts')) . '</legend><div class="camps"><div class="camp ample"><label for="c_rebut_peu">' . h(t('camp.rebut_peu'))
        . '</label><textarea id="c_rebut_peu" name="rebut_peu" rows="2">' . h($val('rebut_peu')) . '</textarea><small>' . h(t('ajuda.rebut_peu')) . '</small></div></div></fieldset>';
    $html .= '<div class="accions"><button>' . h(t('desa')) . '</button></div></form>';
    $html .= '<p class="avis ' . ($creditor ? 'ok' : 'atencio') . '">' . h(t($creditor ? 'economia.creditor_ok' : 'remesa.falta_creditor')) . '</p>';
    pagina(t('economia.config'), $html);
}

/* ---------------- Resum ---------------- */

function pagina_economia(): void
{
    requereix('economia.view');
    $any = date('Y');
    $perCobrar = fila("SELECT COUNT(*) AS n, COALESCE(SUM(import), 0) AS s FROM rebuts WHERE estat IN ('per_cobrar', 'retornat')");
    $cobrat = fila("SELECT COUNT(*) AS n, COALESCE(SUM(import), 0) AS s FROM rebuts WHERE estat = 'pagat' AND substr(data_pagament, 1, 4) = ?", [$any]);
    $retornats = fila("SELECT COUNT(*) AS n, COALESCE(SUM(import), 0) AS s FROM rebuts WHERE estat = 'retornat'");
    [$perRemesa] = rebuts_per_remesa();
    $html = '';
    if (!creditor_sepa()) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('remesa.falta_creditor')) . (pot('config.manage') ? ' <a href="' . h(url('economia_config')) . '">' . h(t('economia.config')) . '</a>' : '') . '</div>';
    }
    if (!valor("SELECT COUNT(*) FROM tarifes WHERE estat = 'actiu'")) {
        $html .= '<div class="avis info">' . h(t('economia.sense_tarifes')) . ' <a href="' . h(url('edita', ['e' => 'tarifes'])) . '">' . h(t('economia.crea_tarifa')) . '</a></div>';
    }
    $html .= '<div class="targetes">'
        . targeta(euros($perCobrar['s']), t('economia.per_cobrar', $perCobrar['n']), url('llista', ['e' => 'rebuts', 'filtre' => 'per_cobrar']))
        . targeta(euros($cobrat['s']), t('economia.cobrat', $any), url('llista', ['e' => 'rebuts', 'f[estat]' => 'pagat']))
        . targeta((string)count($perRemesa), t('economia.per_remesa'), url('remeses'))
        . targeta((string)$retornats['n'], t('economia.retornats'), url('llista', ['e' => 'rebuts', 'filtre' => 'retornats']))
        . '</div>';
    if (pot('economia.edit')) {
        $html .= '<div class="accions"><a class="boto" href="' . h(url('genera_rebuts')) . '">🧾 ' . h(t('genera.titol')) . '</a><a class="boto secundari" href="' . h(url('remeses')) . '">🏦 ' . h(t('menu.remeses')) . '</a>'
            . '<a class="boto secundari" target="_blank" href="' . h(url('rebut_doc')) . '">🖨 ' . h(t('economia.imprimeix_efectiu')) . '</a>'
            . (pot('config.manage') ? '<a class="boto secundari" href="' . h(url('economia_config')) . '">⚙ ' . h(t('economia.config')) . '</a>' : '') . '</div>';
    }

    // Per període
    $periodes = q("SELECT periode, COUNT(*) AS n, SUM(CASE WHEN estat <> 'anullat' THEN import ELSE 0 END) AS emes, SUM(CASE WHEN estat = 'pagat' THEN import ELSE 0 END) AS cobrat,
        SUM(CASE WHEN estat IN ('per_cobrar', 'retornat') THEN import ELSE 0 END) AS pendent, MAX(data_emissio) AS d
        FROM rebuts WHERE periode IS NOT NULL GROUP BY periode ORDER BY d DESC LIMIT 12")->fetchAll();
    if ($periodes) {
        $html .= '<section class="fill"><header><h2>' . h(t('economia.per_periode')) . '</h2></header><div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.periode'))
            . '</th><th class="num">' . h(t('ent.rebuts')) . '</th><th class="num">' . h(t('economia.emes')) . '</th><th class="num">' . h(t('economia.cobrat_col')) . '</th><th class="num">' . h(t('economia.pendent')) . '</th></tr></thead><tbody>';
        foreach ($periodes as $p) {
            $html .= '<tr data-href="' . h(url('llista', ['e' => 'rebuts', 'q' => $p['periode']])) . '"><td><a href="' . h(url('llista', ['e' => 'rebuts', 'q' => $p['periode']])) . '">' . h($p['periode']) . '</a></td><td class="num">' . (int)$p['n']
                . '</td><td class="num">' . h(euros($p['emes'])) . '</td><td class="num">' . h(euros($p['cobrat'])) . '</td><td class="num">' . h(euros($p['pendent'])) . '</td></tr>';
        }
        $html .= '</tbody></table></div></section>';
    }

    // Qui deu més
    $deutes = q("SELECT r.persona_id, " . NOM_PERSONA_SQL . " AS nom, COUNT(*) AS n, SUM(r.import) AS s, SUM(r.estat = 'retornat') AS ret FROM rebuts r JOIN persones p ON p.id = r.persona_id
        WHERE r.estat IN ('per_cobrar', 'retornat') AND (r.forma_pagament <> 'domiciliacio' OR r.estat = 'retornat') GROUP BY r.persona_id ORDER BY s DESC LIMIT 25")->fetchAll();
    $html .= '<section class="fill"><header><h2>' . h(t('economia.deutes')) . '</h2></header>';
    if (!$deutes) {
        $html .= '<p class="buit petit">' . h(t('economia.sense_deutes')) . '</p>';
    } else {
        $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('genera.pagador')) . '</th><th class="num">' . h(t('ent.rebuts')) . '</th><th class="num">' . h(t('camp.import')) . '</th></tr></thead><tbody>';
        foreach ($deutes as $d) {
            $u = url('fitxa', ['e' => 'persones', 'id' => $d['persona_id']]);
            $html .= '<tr data-href="' . h($u) . '"><td><a href="' . h($u) . '">' . h($d['nom']) . '</a>' . ($d['ret'] ? ' <span class="etiqueta">' . h(t('economia.n_retornats', $d['ret'])) . '</span>' : '') . '</td><td class="num">' . (int)$d['n'] . '</td><td class="num">' . h(euros($d['s'])) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '<p class="nota">' . h(t('economia.nota_deutes')) . '</p></section>';
    $html .= '<p class="nota">' . h(t('economia.nota_factures')) . '</p>';
    pagina(t('menu.economia'), $html);
}
