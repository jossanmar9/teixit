<?php
// Butlletins de qualificacions per a imprimir i logotip de la societat
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/* ---------- Logotip de la societat ---------- */

const LOGO_TIPUS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];

function logo_societat(): ?string
{
    foreach (LOGO_TIPUS as $ext) {
        $f = DADES_DIR . DIRECTORY_SEPARATOR . 'logo.' . $ext;
        if (is_file($f)) {
            return $f;
        }
    }
    return null;
}

function url_logo(): ?string
{
    $f = logo_societat();
    return $f ? url('logo', ['v' => filemtime($f)]) : null;
}

/** Desa el logotip pujat. Retorna un missatge d'error o null */
function desa_logo(array $fitxer): ?string
{
    if ($fitxer['error'] !== UPLOAD_ERR_OK || $fitxer['size'] > 3 * 1024 * 1024) {
        return t('logo.err_mida');
    }
    $info = @getimagesize($fitxer['tmp_name']);
    if (!$info || !isset(LOGO_TIPUS[$info['mime']])) {
        return t('logo.err_format');
    }
    esborra_logo();
    move_uploaded_file($fitxer['tmp_name'], DADES_DIR . DIRECTORY_SEPARATOR . 'logo.' . LOGO_TIPUS[$info['mime']]);
    return null;
}

function esborra_logo(): void
{
    while ($f = logo_societat()) {
        unlink($f);
    }
}

function serveix_logo(): never
{
    $f = logo_societat();
    if (!$f) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . array_search(pathinfo($f, PATHINFO_EXTENSION), LOGO_TIPUS, true));
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: private, max-age=86400');
    readfile($f);
    exit;
}

/* ---------- Butlletins ---------- */

function pagina_butlleti(): void
{
    requereix('escola.view');
    if (get('imprimir') === '1') {
        imprimeix_butlletins();
        return;
    }
    $curs = (int)get('curs', (string)(curs_actual()['id'] ?? 0));
    $periodes = array_column(q('SELECT id, nom FROM periodes WHERE curs_id = ? ORDER BY ordre', [$curs])->fetchAll(), 'nom', 'id');
    $grups = array_column(q("SELECT g.id, a.nom || ' · ' || g.nom AS e FROM grups g JOIN assignatures a ON a.id = g.assignatura_id WHERE g.curs_id = ? ORDER BY e", [$curs])->fetchAll(), 'e', 'id');
    $alumnes = array_column(q("SELECT al.id, " . NOM_PERSONA_SQL . " AS e FROM matricules m JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
        WHERE m.curs_id = ? AND m.estat = 'actiu' ORDER BY e", [$curs])->fetchAll(), 'e', 'id');

    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="butlleti">' . select_simple('curs', opcions_ref('cursos'), $curs, false) . '</form>';
    if (!$alumnes) {
        pagina(t('menu.butlletins'), $html . '<div class="buit">' . h(t('butlleti.sense_alumnes')) . '</div>');
        return;
    }
    $periodeDefecte = array_key_last($periodes);
    $ambit = get('alumne') !== '' ? 'alumne' : (get('grup') !== '' ? 'grup' : 'tots');
    $html .= '<form method="get" target="_blank" class="formulari"><input type="hidden" name="r" value="butlleti"><input type="hidden" name="imprimir" value="1">'
        . '<input type="hidden" name="curs" value="' . $curs . '"><div class="camps">'
        . '<div class="camp"><label>' . h(t('butlleti.periode')) . '</label>' . select_simple('periode', $periodes, $periodeDefecte, false, '', false)
        . '<small>' . h(t('butlleti.periode_ajuda')) . '</small></div>'
        . '<fieldset class="camp ample"><legend>' . h(t('butlleti.qui')) . '</legend><div class="opcions">'
        . '<label><input type="radio" name="ambit" value="tots"' . ($ambit === 'tots' ? ' checked' : '') . '> ' . h(t('butlleti.tots', count($alumnes))) . '</label>'
        . '<label><input type="radio" name="ambit" value="grup"' . ($ambit === 'grup' ? ' checked' : '') . '> ' . h(t('butlleti.un_grup')) . '</label>'
        . '<label><input type="radio" name="ambit" value="alumne"' . ($ambit === 'alumne' ? ' checked' : '') . '> ' . h(t('butlleti.un_alumne')) . '</label></div></fieldset>'
        . '<div class="camp"><label>' . h(t('camp.grup_id')) . '</label>' . select_simple('grup', $grups, get('grup'), true, '—', false) . '</div>'
        . '<div class="camp"><label>' . h(t('camp.alumne_id')) . '</label>' . str_replace('<select', '<select data-cerca="1"', select_simple('alumne', $alumnes, get('alumne'), true, '—', false)) . '</div>'
        . '<div class="camp ample"><label>' . h(t('butlleti.text_peu')) . '</label><textarea name="peu" rows="2" placeholder="' . h(t('butlleti.text_peu_ajuda')) . '">' . h(config('butlleti_peu')) . '</textarea></div>'
        . '</div><div class="accions"><button>🖨 ' . h(t('butlleti.prepara')) . '</button></div></form>';
    if (!$periodes) {
        $html .= '<div class="avis info">' . h(t('notes.sense_periodes')) . '</div>';
    }
    if (!logo_societat() && pot('config.manage')) {
        $html .= '<p class="nota">' . h(t('butlleti.sense_logo')) . ' <a href="' . h(url('configuracio')) . '">' . h(t('menu.configuracio')) . '</a></p>';
    }
    pagina(t('menu.butlletins'), $html);
}

function imprimeix_butlletins(): void
{
    $curs = fila('SELECT * FROM cursos WHERE id = ?', [(int)get('curs')]);
    if (!$curs) {
        redirigeix('butlleti');
    }
    $periodes = q('SELECT * FROM periodes WHERE curs_id = ? ORDER BY ordre', [$curs['id']])->fetchAll();
    $triat = (int)get('periode');
    $ordreTriat = PHP_INT_MAX;
    $nomPeriode = '';
    foreach ($periodes as $pe) {
        if ((int)$pe['id'] === $triat) {
            $ordreTriat = (int)$pe['ordre'];
            $nomPeriode = $pe['nom'];
        }
    }
    // Es mostren el període triat i els anteriors
    $periodes = array_values(array_filter($periodes, fn($pe) => (int)$pe['ordre'] <= $ordreTriat));
    $periodeObs = $triat ?: (int)(end($periodes)['id'] ?? 0);

    $peu = mb_substr(trim((string)get('peu')), 0, 500);
    if (pot('escola.edit')) {
        desa_config('butlleti_peu', $peu ?: null);
    }

    $sql = "SELECT al.id, p.nom, p.cognoms, p.data_naixement, m.id AS matricula FROM matricules m JOIN alumnes al ON al.id = m.alumne_id
        JOIN persones p ON p.id = al.persona_id WHERE m.curs_id = ? AND m.estat = 'actiu'";
    $params = [$curs['id']];
    if (get('ambit') === 'grup' && get('grup') !== '') {
        $sql .= ' AND m.id IN (SELECT matricula_id FROM matricula_grups WHERE grup_id = ?)';
        $params[] = (int)get('grup');
    } elseif (get('ambit') === 'alumne' && get('alumne') !== '') {
        $sql .= ' AND al.id = ?';
        $params[] = (int)get('alumne');
    }
    $alumnes = q($sql . ' ORDER BY p.cognoms, p.nom', $params)->fetchAll();
    audita('butlletins', 'cursos', $curs['id'], count($alumnes) . ' · ' . $nomPeriode);

    $societat = config('societat_nom') ?: 'Teixit';
    $dades = array_filter([config('societat_adreca'), config('societat_telefon'), config('societat_email'), config('societat_web')]);
    $logo = url_logo();

    echo '<!doctype html><html lang="' . (idioma() === 'ca' ? 'ca-valencia' : 'es') . '"><head><meta charset="utf-8"><title>' . h(t('butlleti.titol') . ' · ' . $curs['nom']) . '</title>'
        . '<link rel="stylesheet" href="assets/butlleti.css?v=' . TEIXIT_VERSIO . '"></head><body>';
    echo '<div class="barra-impressio"><button onclick="window.print()">🖨 ' . h(t('butlleti.imprimeix')) . '</button> <span>' . h(t('butlleti.n_butlletins', count($alumnes)))
        . '</span><small>' . h(t('butlleti.consell_pdf')) . '</small></div>';
    if (!$alumnes) {
        echo '<p class="buit">' . h(t('butlleti.sense_alumnes')) . '</p>';
    }
    foreach ($alumnes as $al) {
        $grups = q("SELECT g.id, a.nom AS assignatura, g.nom AS grup, " . NOM_PERSONA_SQL . " AS professor FROM matricula_grups mg
            JOIN grups g ON g.id = mg.grup_id JOIN assignatures a ON a.id = g.assignatura_id
            LEFT JOIN professors pr ON pr.id = g.professor_id LEFT JOIN persones p ON p.id = pr.persona_id
            WHERE mg.matricula_id = ? ORDER BY a.nom", [$al['matricula']])->fetchAll();

        echo '<section class="full"><header class="capcalera">';
        if ($logo) {
            echo '<img src="' . h($logo) . '" alt="">';
        }
        echo '<div><h1>' . h($societat) . '</h1>' . ($dades ? '<p>' . h(implode(' · ', $dades)) . '</p>' : '') . '</div></header>';
        echo '<h2>' . h(t('butlleti.titol')) . '</h2><p class="curs">' . h(t('camp.curs_id') . ' ' . $curs['nom'] . ($nomPeriode ? ' · ' . $nomPeriode : '')) . '</p>';
        echo '<table class="dades"><tr><th>' . h(t('ent1.alumnes')) . '</th><td><b>' . h(trim($al['nom'] . ' ' . $al['cognoms'])) . '</b></td>'
            . ($al['data_naixement'] ? '<th>' . h(t('camp.data_naixement')) . '</th><td>' . h(data_llegible($al['data_naixement'])) . '</td>' : '') . '</tr></table>';

        echo '<table class="notes"><thead><tr><th>' . h(t('camp.assignatura_id')) . '</th><th>' . h(t('camp.professor_id')) . '</th>';
        foreach ($periodes as $pe) {
            echo '<th class="nota">' . h($pe['nom']) . '</th>';
        }
        echo '</tr></thead><tbody>';
        $observacions = [];
        foreach ($grups as $g) {
            echo '<tr><td>' . h($g['assignatura']) . ' <small>' . h($g['grup']) . '</small></td><td>' . h($g['professor']) . '</td>';
            foreach ($periodes as $pe) {
                $n = fila('SELECT nota, observacions FROM qualificacions WHERE grup_id = ? AND alumne_id = ? AND periode_id = ?', [$g['id'], $al['id'], $pe['id']]);
                echo '<td class="nota' . ((int)$pe['id'] === $periodeObs ? ' actual' : '') . '">' . h($n['nota'] ?? '') . '</td>';
                if ((int)$pe['id'] === $periodeObs && !empty($n['observacions'])) {
                    $observacions[] = [$g['assignatura'], $n['observacions']];
                }
            }
            echo '</tr>';
        }
        if (!$grups) {
            echo '<tr><td colspan="' . (count($periodes) + 2) . '">' . h(t('butlleti.sense_grups')) . '</td></tr>';
        }
        echo '</tbody></table>';

        if ($observacions) {
            echo '<h3>' . h(t('notes.observacions')) . '</h3><dl class="obs">';
            foreach ($observacions as [$a, $o]) {
                echo '<dt>' . h($a) . '</dt><dd>' . nl2br(h($o)) . '</dd>';
            }
            echo '</dl>';
        }
        if ($peu) {
            echo '<p class="peu-text">' . nl2br(h($peu)) . '</p>';
        }
        echo '<footer><div class="data">' . h(t('butlleti.data', data_llegible(date('Y-m-d')))) . '</div>'
            . '<div class="signatures"><div>' . h(t('butlleti.signa_direccio')) . '</div><div>' . h(t('butlleti.signa_familia')) . '</div></div></footer></section>';
    }
    echo '</body></html>';
}
