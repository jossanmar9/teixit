<?php
// Documents per a imprimir: fulls de consentiment d'imatge i certificats
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/* ---------- Peces comunes dels documents A4 ---------- */

/** «20 d'octubre de 2026» / «20 de octubre de 2026» */
function data_llarga(?string $ymd = null): string
{
    $t = strtotime($ymd ?: date('Y-m-d'));
    $mes = (int)date('n', $t);
    $nom = t('mes.' . $mes);
    $de = idioma() === 'ca' && preg_match('/^[aeiou]/u', $nom) ? "d'" : 'de ';
    return date('j', $t) . ' ' . $de . $nom . ' de ' . date('Y', $t);
}

/** Capçalera HTML d'un document per a imprimir (fulls A4 amb la barra d'impressió a dalt) */
function document_inici(string $titol, string $resum, string $consell = ''): void
{
    echo '<!doctype html><html lang="' . (idioma() === 'ca' ? 'ca-valencia' : 'es') . '"><head><meta charset="utf-8"><title>' . h($titol) . '</title>'
        . '<link rel="stylesheet" href="assets/butlleti.css?v=' . TEIXIT_VERSIO . '"><link rel="stylesheet" href="assets/document.css?v=' . TEIXIT_VERSIO . '"></head><body>';
    echo '<div class="barra-impressio"><button onclick="window.print()">🖨 ' . h(t('llist.imprimeix')) . '</button> <span>' . h($resum) . '</span>'
        . '<small>' . h($consell ?: t('butlleti.consell_pdf')) . '</small></div>';
}

function document_fi(): void
{
    echo '</body></html>';
}

/** Capçalera amb el logotip i les dades de la societat */
function capcalera_societat(): string
{
    $societat = config('societat_nom') ?: 'Teixit';
    $dades = array_filter([config('societat_cif') ? 'CIF ' . config('societat_cif') : null, config('societat_adreca'), config('societat_poblacio'),
        config('societat_telefon'), config('societat_email'), config('societat_web')]);
    $logo = url_logo();
    return '<header class="capcalera">' . ($logo ? '<img src="' . h($logo) . '" alt="">' : '')
        . '<div><h1>' . h($societat) . '</h1>' . ($dades ? '<p>' . h(implode(' · ', $dades)) . '</p>' : '') . '</div></header>';
}

/** Línia per a omplir a mà quan falta una dada */
function o_linia(?string $v, string $classe = ''): string
{
    return $v !== null && $v !== '' ? h($v) : '<span class="linia' . ($classe ? ' ' . $classe : '') . '"></span>';
}

/* ---------- Consentiment d'imatge ---------- */

/** Tutors d'una persona (si és alumne), primer el principal */
function tutors_persona(int $personaId): array
{
    return q("SELECT p.*, tu.tipus_relacio FROM tutories tu JOIN alumnes al ON al.id = tu.alumne_id JOIN persones p ON p.id = tu.tutor_id
        WHERE al.persona_id = ? ORDER BY tu.principal DESC, p.cognoms, p.nom", [$personaId])->fetchAll();
}

/** Fulls de consentiment: d'una persona, dels alumnes d'un grup o de tots els alumnes del curs actual */
function pagina_consentiment(): void
{
    requereix('persones.view');
    if (get('persona') !== '') {
        $persones = q('SELECT * FROM persones WHERE id = ?', [(int)get('persona')])->fetchAll();
    } elseif (get('grup') !== '') {
        $persones = q("SELECT DISTINCT p.* FROM matricula_grups mg JOIN matricules m ON m.id = mg.matricula_id JOIN alumnes al ON al.id = m.alumne_id
            JOIN persones p ON p.id = al.persona_id WHERE mg.grup_id = ? AND m.estat = 'actiu' ORDER BY p.cognoms, p.nom", [(int)get('grup')])->fetchAll();
    } else {
        $curs = curs_actual();
        $nomesPendents = get('pendents') === '1';
        $persones = q("SELECT DISTINCT p.* FROM matricules m JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
            WHERE m.curs_id = ? AND m.estat = 'actiu'" . ($nomesPendents ? " AND p.consentiment_imatge = 'pendent'" : '') . ' ORDER BY p.cognoms, p.nom', [$curs['id'] ?? 0])->fetchAll();
    }
    audita('consentiments', null, null, (string)count($persones));

    $societat = config('societat_nom') ?: 'Teixit';
    $contacte = config('societat_email') ?: config('societat_adreca');
    document_inici(t('consent.titol'), t('consent.n_fulls', count($persones)), t('consent.consell'));
    if (!$persones) {
        echo '<p class="buit">' . h(t('cap_resultat')) . '</p>';
    }
    foreach ($persones as $p) {
        $menor = !es_major_edat($p['data_naixement']);
        echo '<section class="full document">' . capcalera_societat();
        echo '<h2 class="centrat">' . h(t('consent.titol')) . '</h2>';

        echo '<h3>' . h($menor ? t('consent.dades_alumne') : t('consent.dades_persona')) . '</h3><table class="formulari-paper">'
            . '<tr><th>' . h(t('consent.nom_complet')) . '</th><td colspan="3"><b>' . h(trim($p['nom'] . ' ' . $p['cognoms'])) . '</b></td></tr>'
            . '<tr><th>' . h(t('camp.data_naixement')) . '</th><td>' . o_linia(data_llegible($p['data_naixement'])) . '</td>'
            . '<th>' . h(t('camp.document')) . '</th><td>' . o_linia($p['document']) . '</td></tr>';
        if (!$menor) {
            echo '<tr><th>' . h(t('camp.telefon')) . '</th><td>' . o_linia($p['telefon']) . '</td><th>' . h(t('camp.email')) . '</th><td>' . o_linia($p['email']) . '</td></tr>';
        }
        echo '</table>';

        if ($menor) {
            $tutor = tutors_persona((int)$p['id'])[0] ?? null;
            echo '<h3>' . h(t('consent.dades_tutor')) . '</h3><table class="formulari-paper">'
                . '<tr><th>' . h(t('consent.nom_complet')) . '</th><td colspan="3">' . o_linia($tutor ? trim($tutor['nom'] . ' ' . $tutor['cognoms']) : null) . '</td></tr>'
                . '<tr><th>' . h(t('camp.document')) . '</th><td>' . o_linia($tutor['document'] ?? null) . '</td>'
                . '<th>' . h(t('camp.tipus_relacio')) . '</th><td>' . o_linia(!empty($tutor['tipus_relacio']) ? t('opt.' . $tutor['tipus_relacio']) : null) . '</td></tr>'
                . '<tr><th>' . h(t('camp.telefon')) . '</th><td>' . o_linia($tutor['telefon'] ?? null) . '</td><th>' . h(t('camp.email')) . '</th><td>' . o_linia($tutor['email'] ?? null) . '</td></tr></table>';
        }

        echo '<p>' . h(t($menor ? 'consent.declara_menor' : 'consent.declara_major', $societat)) . '</p>'
            . '<div class="opcions-paper"><span><span class="casella"></span> <b>' . h(t('consent.autoritze')) . '</b></span>'
            . '<span><span class="casella"></span> <b>' . h(t('consent.no_autoritze')) . '</b></span></div>'
            . '<p class="justificat">' . h(t('consent.text')) . '</p>';
        if ($extra = config('consentiment_extra')) {
            echo '<p class="justificat">' . nl2br(h($extra)) . '</p>';
        }
        echo '<p class="text-legal">' . h(t('consent.legal', $societat . (config('societat_cif') ? ' (CIF ' . config('societat_cif') . ')' : ''), $contacte ? ' (' . $contacte . ')' : '')) . '</p>';
        echo '<footer><p class="lloc-data">' . h(t('consent.lloc_data')) . '</p><div class="signatures"><div>'
            . h($menor ? t('consent.signa_tutor') : t('consent.signa_persona')) . '</div></div></footer></section>';
    }
    document_fi();
}

/* ---------- Certificats ---------- */

/** «Que Nom Cognoms, amb DNI …, nascut/da el …,» */
function cert_persona(array $p): string
{
    $s = t('cert.que', trim($p['nom'] . ' ' . $p['cognoms']));
    if ($p['document']) {
        $s .= t('cert.amb_dni', $p['document']);
    }
    if ($p['data_naixement']) {
        $s .= t('cert.nascut', data_llegible($p['data_naixement']));
    }
    return $s . ',';
}

function pagina_certificat(): void
{
    $tipus = get('tipus');
    $id = (int)get('id');
    $cos = '';
    $llista = [];
    switch ($tipus) {
        case 'soci':
            requereix('socis.view');
            $s = fila('SELECT s.*, p.nom, p.cognoms, p.document, p.data_naixement FROM socis s JOIN persones p ON p.id = s.persona_id WHERE s.id = ?', [$id]);
            if (!$s) {
                break;
            }
            $p = $s;
            $num = $s['numero'] ? t('cert.amb_numero', $s['numero']) : '';
            $tip = nom_tipus_soci($s['tipus']) !== '' ? t('cert.tipus_soci', mb_strtolower(nom_tipus_soci($s['tipus']))) : '';
            if ($s['estat'] === 'actiu') {
                $cos = $s['data_alta'] ? t('cert.soci_actiu', data_llegible($s['data_alta']), $num . $tip) : t('cert.soci_actiu_sense', $num . $tip);
            } else {
                $cos = t('cert.soci_baixa', o_text(data_llegible($s['data_alta'])), o_text(data_llegible($s['data_baixa'])), $num);
            }
            break;
        case 'membre':
            requereix('agrupacions.view');
            $m = fila('SELECT m.*, a.nom AS agrupacio, p.nom, p.cognoms, p.document, p.data_naixement FROM membres m JOIN agrupacions a ON a.id = m.agrupacio_id
                JOIN persones p ON p.id = m.persona_id WHERE m.id = ?', [$id]);
            if (!$m) {
                break;
            }
            $p = $m;
            $extra = ($m['instrument_veu'] ? t('cert.amb_instrument', $m['instrument_veu']) : '')
                . ($m['funcio'] && $m['funcio'] !== 'membre' ? t('cert.com_a', mb_strtolower(t('opt.' . $m['funcio']))) : '');
            $cos = $m['estat'] === 'actiu'
                ? t('cert.membre_actiu', $m['agrupacio'], o_text(data_llegible($m['data_alta'])), $extra)
                : t('cert.membre_baixa', $m['agrupacio'], o_text(data_llegible($m['data_alta'])), o_text(data_llegible($m['data_baixa'])), $extra);
            break;
        case 'matricula':
            requereix('escola.view');
            $m = fila('SELECT m.*, c.nom AS curs, p.nom, p.cognoms, p.document, p.data_naixement FROM matricules m JOIN cursos c ON c.id = m.curs_id
                JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id WHERE m.id = ?', [$id]);
            if (!$m) {
                break;
            }
            $p = $m;
            $llista = array_column(q("SELECT a.nom || ' (' || g.nom || ')' AS e FROM matricula_grups mg JOIN grups g ON g.id = mg.grup_id
                JOIN assignatures a ON a.id = g.assignatura_id WHERE mg.matricula_id = ? ORDER BY a.nom", [$id])->fetchAll(), 'e');
            $cos = t($m['estat'] === 'actiu' ? 'cert.matricula_actiu' : 'cert.matricula_baixa', $m['curs']) . ($llista ? ' ' . t('cert.assignatures') : '.');
            break;
    }
    if ($cos === '') {
        avis(t('err.no_trobat'), 'error');
        redirigeix('inici');
    }
    audita('certificat', $tipus === 'membre' ? 'membres' : ($tipus === 'soci' ? 'socis' : 'matricules'), $id);

    $societat = config('societat_nom') ?: 'Teixit';
    [$signant, $carrec, $president] = signants_certificat();
    $poblacio = config('societat_poblacio');
    $nomPersona = trim($p['nom'] . ' ' . $p['cognoms']);

    document_inici(t('cert.titol') . ' · ' . $nomPersona, $nomPersona, t('cert.consell'));
    echo '<section class="full document certificat">' . capcalera_societat();
    echo '<h2 class="centrat">' . h(t('cert.titol')) . '</h2>';
    echo '<p class="justificat">' . ($signant ? h($signant) : '<span class="linia llarga"></span>') . ', ' . h(t('cert.intro', $carrec, $societat)) . '</p>';
    echo '<p class="certifica">' . h(t('cert.certifica')) . '</p>';
    echo '<p class="justificat">' . h(cert_persona($p) . ' ' . $cos) . '</p>';
    if ($llista) {
        echo '<ul class="llista-cert">';
        foreach ($llista as $l) {
            echo '<li>' . h($l) . '</li>';
        }
        echo '</ul>';
    }
    echo '<p class="justificat">' . h($poblacio ? t('cert.final', $poblacio, data_llarga()) : t('cert.final_sense', data_llarga())) . '</p>';
    echo '<footer><div class="signatures">';
    if ($president) {
        echo '<div><span class="rol-signa">' . h(t('cert.vistiplau')) . '</span>' . h($president) . '<br><small>' . h(t('cert.president')) . '</small></div>';
    }
    echo '<div><span class="rol-signa">' . h(t('cert.signatura')) . '</span>' . h($signant ?: '') . '<br><small>' . h(mb_strtoupper(mb_substr($carrec, 0, 1)) . mb_substr($carrec, 1)) . '</small></div>';
    echo '</div></footer></section>';
    document_fi();
}

/** Text o «___» si falta (dins d'una frase) */
function o_text(?string $v): string
{
    return $v !== null && $v !== '' ? $v : '________';
}
