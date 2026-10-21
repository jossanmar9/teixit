<?php
// Inici, resum de les activitats d'un participant i horari general
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

function targeta(string $xifra, string $text, ?string $enllac = null): string
{
    $in = '<b>' . h($xifra) . '</b><span>' . h($text) . '</span>';
    return $enllac ? '<a class="targeta" href="' . h($enllac) . '">' . $in . '</a>' : '<div class="targeta">' . $in . '</div>';
}

function pagina_inici(): void
{
    $curs = curs_actual();
    $html = avis_responsabilitat_pendent() . avis_codi_recuperacio() . avis_copies() . '<div class="targetes">';
    $es = fn(string $cap, string $modul) => pot($cap) && modul_actiu($modul);
    if (pot('persones.view')) {
        $html .= targeta((string)valor('SELECT COUNT(*) FROM persones WHERE actiu = 1'), t('ent.persones'), url('llista', ['e' => 'persones']));
    }
    if (pot('socis.view')) {
        $html .= targeta((string)valor("SELECT COUNT(*) FROM socis WHERE estat = 'actiu'"), t('inici.socis_actius'), url('llista', ['e' => 'socis']));
    }
    if ($es('escola.view', 'escola') && $curs) {
        $html .= targeta((string)valor("SELECT COUNT(*) FROM matricules WHERE curs_id = ? AND estat = 'actiu'", [$curs['id']]), t('inici.matriculats', $curs['nom']),
            url('llista', ['e' => 'matricules', 'f[curs_id]' => $curs['id']]));
        $html .= targeta((string)valor("SELECT COUNT(*) FROM grups WHERE curs_id = ? AND estat = 'actiu'", [$curs['id']]), t('ent.grups'),
            url('llista', ['e' => 'grups', 'f[curs_id]' => $curs['id']]));
    }
    if ($es('agrupacions.view', 'agrupacions')) {
        $html .= targeta((string)valor("SELECT COUNT(*) FROM membres WHERE estat = 'actiu'"), t('inici.musics'), url('llista', ['e' => 'membres']));
    }
    if ($es('instruments.view', 'instruments')) {
        $html .= targeta((string)valor('SELECT COUNT(*) FROM cessions WHERE data_retorn IS NULL'), t('inici.cedits'), url('llista', ['e' => 'cessions', 'filtre' => 'actives']));
    }
    if ($es('economia.view', 'economia') && ($n = (int)valor("SELECT COUNT(*) FROM rebuts WHERE estat IN ('per_cobrar', 'retornat')"))) {
        $html .= targeta(euros((float)valor("SELECT SUM(import) FROM rebuts WHERE estat IN ('per_cobrar', 'retornat')")), t('economia.per_cobrar', $n), url('economia'));
    }
    $html .= '</div>';

    // Avisos de l'entitat: renovació de la junta, documents que caduquen, monitors sense certificat i llicències
    if ($es('societat.view', 'junta') && avis_actiu('junta_renovacio') && ($r = renovacio_junta()) && $r['dies'] <= 120) {
        $html .= '<div class="avis ' . ($r['dies'] < 0 ? 'error' : 'atencio') . '">⚠ ' . h(t($r['dies'] < 0 ? 'junta.renovacio_passada' : 'junta.renovacio', data_llegible($r['eleccio']), $r['anys'], data_llegible($r['limit'])))
            . ' <a href="' . h(url('junta')) . '">' . h(t('menu.junta')) . '</a></div>';
    }
    if ($es('societat.view', 'documents') && avis_actiu('documents_caducitat') && ($n = (int)valor("SELECT COUNT(*) FROM documents WHERE data_caducitat IS NOT NULL AND data_caducitat <= date('now','localtime','+60 days')"))) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('inici.documents_caduquen', $n)) . ' <a href="' . h(url('llista', ['e' => 'documents', 'filtre' => 'caduquen'])) . '">' . h(t('avis_llista.veure')) . '</a></div>';
    }
    if ($es('escola.view', 'escola') && avis_actiu('certificat_delictes') && ($n = (int)valor("SELECT COUNT(*) FROM professors WHERE estat = 'actiu' AND certificat_delictes IS NULL"))) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('inici.sense_certificat', $n)) . ' <a href="' . h(url('llista', ['e' => 'professors', 'filtre' => 'sense_certificat'])) . '">' . h(t('avis_llista.veure')) . '</a></div>';
    }

    if (amb_llicencies() && $es('agrupacions.view', 'agrupacions') && avis_actiu('llicencies')
        && ($n = (int)valor("SELECT COUNT(*) FROM membres WHERE estat = 'actiu' AND llicencia_caducitat IS NOT NULL AND llicencia_caducitat <= date('now','localtime','+30 days')"))) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('inici.llicencies_caduquen', $n)) . ' <a href="' . h(url('llista', ['e' => 'membres', 'filtre' => 'llicencia_caduca'])) . '">' . h(t('avis_llista.veure')) . '</a></div>';
    }

    if ($es('escola.view', 'escola') && avis_actiu('curs_actual') && !$curs) {
        $html .= '<div class="avis info">' . h(t('inici.sense_curs')) . ' <a href="' . h(url('edita', ['e' => 'cursos'])) . '">' . h(t('inici.crea_curs')) . '</a></div>';
    }

    if ($es('instruments.view', 'instruments') && avis_actiu('cessions_vencudes')) {
        $vencudes = q("SELECT c.id, c.data_prevista, i.inventari, i.tipus, " . NOM_PERSONA_SQL . " AS persona FROM cessions c JOIN instruments i ON i.id = c.instrument_id
            JOIN persones p ON p.id = c.persona_id WHERE c.data_retorn IS NULL AND c.data_prevista < date('now','localtime') ORDER BY c.data_prevista")->fetchAll();
        if ($vencudes) {
            $html .= '<section class="fill"><header><h2>⚠ ' . h(t('inici.vencudes')) . '</h2></header><ul class="llista-simple">';
            foreach ($vencudes as $v) {
                $html .= '<li><a href="' . h(url('fitxa', ['e' => 'cessions', 'id' => $v['id']])) . '">' . h($v['inventari'] . ' · ' . $v['tipus']) . '</a> — '
                    . h($v['persona']) . ' <span class="etiqueta">' . h(t('inici.prevista', data_llegible($v['data_prevista']))) . '</span></li>';
            }
            $html .= '</ul></section>';
        }
    }

    // Activitats de hui
    if ($curs && $es('escola.view', 'escola')) {
        $params = [$curs['id'], (int)date('N')];
        $filtre = '';
        $classes = q("SELECT g.id, a.nom AS assignatura, g.nom, h.hora_inici, h.hora_fi, au.nom AS aula FROM horaris h JOIN grups g ON g.id = h.grup_id
            JOIN assignatures a ON a.id = g.assignatura_id LEFT JOIN aules au ON au.id = g.aula_id
            WHERE g.curs_id = ? AND h.dia = ? AND g.estat = 'actiu' $filtre ORDER BY h.hora_inici", $params)->fetchAll();
        $html .= '<section class="fill"><header><h2>' . h(t('inici.classes_hui', t('dia.' . date('N')))) . '</h2></header>';
        if (!$classes) {
            $html .= '<p class="buit petit">' . h(t('inici.sense_classes')) . '</p>';
        } else {
            $html .= '<ul class="llista-simple">';
            foreach ($classes as $c) {
                $html .= '<li><span class="hora">' . h($c['hora_inici'] . '–' . $c['hora_fi']) . '</span> ' . h($c['assignatura'] . ' · ' . $c['nom'])
                    . ($c['aula'] ? ' <span class="etiqueta">' . h($c['aula']) . '</span>' : '');
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';
    }

    pagina(t('menu.inici'), $html, config('societat_nom'));
}

/** Resum acadèmic d'un alumne per a un curs: grups, horari i notes */
function resum_alumne(int $alumneId, array $curs): string
{
    $m = fila('SELECT * FROM matricules WHERE alumne_id = ? AND curs_id = ?', [$alumneId, $curs['id']]);
    if (!$m) {
        return '<p class="buit petit">' . h(t('resum.sense_matricula', $curs['nom'])) . '</p>';
    }
    $grups = q("SELECT g.id, a.nom AS assignatura, g.nom, " . NOM_PERSONA_SQL . " AS professor,
        (SELECT group_concat(h.dia || '|' || h.hora_inici || '|' || h.hora_fi, ';') FROM horaris h WHERE h.grup_id = g.id) AS horari
        FROM matricula_grups mg JOIN grups g ON g.id = mg.grup_id JOIN assignatures a ON a.id = g.assignatura_id
        LEFT JOIN professors pr ON pr.id = g.professor_id LEFT JOIN persones p ON p.id = pr.persona_id
        WHERE mg.matricula_id = ? ORDER BY a.nom", [$m['id']])->fetchAll();
    // Les qualificacions només es mostren si l'entitat les fa servir
    $periodes = modul_actiu('qualificacions') ? q('SELECT * FROM periodes WHERE curs_id = ? ORDER BY ordre', [$curs['id']])->fetchAll() : [];

    $html = '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.assignatura_id')) . '</th><th>' . h(t('camp.professor_id')) . '</th><th>'
        . h(t('menu.horari')) . '</th>';
    foreach ($periodes as $pe) {
        $html .= '<th>' . h($pe['nom']) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($grups as $g) {
        $hores = [];
        foreach (array_filter(explode(';', (string)$g['horari'])) as $hh) {
            [$d, $hi, $hf] = explode('|', $hh);
            $hores[] = t('dia_curt.' . $d) . ' ' . $hi . '–' . $hf;
        }
        $html .= '<tr><td>' . h($g['assignatura'] . ' · ' . $g['nom']) . '</td><td>' . h($g['professor']) . '</td><td>' . h(implode(', ', $hores)) . '</td>';
        foreach ($periodes as $pe) {
            $n = fila('SELECT nota, observacions FROM qualificacions WHERE grup_id = ? AND alumne_id = ? AND periode_id = ?', [$g['id'], $alumneId, $pe['id']]);
            $html .= '<td' . ($n && $n['observacions'] ? ' title="' . h($n['observacions']) . '"' : '') . '>' . h($n['nota'] ?? '') . ($n && $n['observacions'] ? ' 💬' : '') . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>' . ($periodes ? '<p class="peu-taula">' . h(t('resum.llegenda_obs')) . '</p>' : '');
}

function pagina_horari(): void
{
    requereix('escola.view');
    $cursos = opcions_ref('cursos');
    $cursId = (int)get('curs', (string)(curs_actual()['id'] ?? 0));
    $prof = (int)get('professor', '0');
    $aula = (int)get('aula', '0');

    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="horari">' . select_simple('curs', $cursos, $cursId, false)
        . select_simple('professor', opcions_ref('professors'), $prof, true, t('filtre.tot_professorat'))
        . select_simple('aula', opcions_ref('aules'), $aula, true, t('filtre.totes_aules')) . '<button>' . h(t('filtrar')) . '</button></form>';

    $params = [$cursId];
    $where = "g.curs_id = ? AND g.estat = 'actiu'";
    if ($prof) {
        $where .= ' AND g.professor_id = ?';
        $params[] = $prof;
    }
    if ($aula) {
        $where .= ' AND g.aula_id = ?';
        $params[] = $aula;
    }
    $files = q("SELECT h.*, g.id AS grup_id, a.nom AS assignatura, g.nom AS grup, au.nom AS aula, " . NOM_PERSONA_SQL . " AS professor
        FROM horaris h JOIN grups g ON g.id = h.grup_id JOIN assignatures a ON a.id = g.assignatura_id
        LEFT JOIN aules au ON au.id = g.aula_id LEFT JOIN professors pr ON pr.id = g.professor_id LEFT JOIN persones p ON p.id = pr.persona_id
        WHERE $where ORDER BY h.dia, h.hora_inici", $params)->fetchAll();
    $dies = [];
    foreach ($files as $f) {
        $dies[$f['dia']][] = $f;
    }
    $ultim = max(5, $dies ? max(array_keys($dies)) : 5);
    $html .= '<div class="horari">';
    for ($d = 1; $d <= $ultim; $d++) {
        $html .= '<div class="dia"><h3>' . h(t('dia.' . $d)) . '</h3>';
        foreach ($dies[$d] ?? [] as $f) {
            $html .= '<a class="sessio" href="' . h(url('fitxa', ['e' => 'grups', 'id' => $f['grup_id']])) . '"><b>' . h($f['hora_inici'] . '–' . $f['hora_fi']) . '</b>'
                . h($f['assignatura'] . ' · ' . $f['grup']) . '<small>' . h(trim(($f['professor'] ?? '') . ($f['aula'] ? ' · ' . $f['aula'] : ''), ' ·')) . '</small></a>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    if (!$files) {
        $html .= '<p class="buit petit">' . h(t('horari.buit')) . '</p>';
    }
    pagina(t('menu.horari'), $html);
}

function select_simple(string $nom, array $ops, $actual, bool $buit = true, string $textBuit = '—', bool $auto = true): string
{
    $s = '<select name="' . h($nom) . '"' . ($auto ? ' onchange="this.form.submit()"' : '') . '>';
    if ($buit) {
        $s .= '<option value="">' . h($textBuit) . '</option>';
    }
    foreach ($ops as $k => $v) {
        $s .= '<option value="' . h($k) . '"' . ((string)$k === (string)$actual ? ' selected' : '') . '>' . h($v) . '</option>';
    }
    return $s . '</select>';
}
