<?php
// Societat: junta directiva, reunions i actes, documents, full d'alta de soci i comunicats
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/* ---------------- Junta directiva ---------------- */

/** Càrrecs vigents (sense data de fi o amb la data de fi a partir de hui), en l'ordre habitual */
function carrecs_vigents(): array
{
    return q("SELECT t.*, p.nom, p.cognoms, p.document FROM carrecs t JOIN persones p ON p.id = t.persona_id
        WHERE t.data_fi IS NULL OR t.data_fi >= date('now','localtime') ORDER BY " . ORDRE_CARRECS_SQL . ', t.data_inici')->fetchAll();
}

/** Persona que ocupa ara un càrrec */
function carrec_vigent(string $carrec): ?array
{
    foreach (carrecs_vigents() as $c) {
        if ($c['carrec'] === $carrec) {
            return $c;
        }
    }
    return null;
}

/** Nom del càrrec, amb la descripció si en té («Vocal d'escola») */
function nom_carrec(array $c): string
{
    return t('opt.' . $c['carrec']) . ($c['descripcio'] ? ' (' . $c['descripcio'] . ')' : '');
}

/**
 * Qui signa els certificats i les actes: el que diga la configuració o, si està buit, la secretaria i la presidència de la junta.
 * Retorna [signant, càrrec del signant, president/a].
 */
function signants_certificat(): array
{
    $secretaria = carrec_vigent('secretaria');
    $presidencia = carrec_vigent('presidencia');
    $signant = config('certificat_signant') ?: ($secretaria ? trim($secretaria['nom'] . ' ' . $secretaria['cognoms']) : null);
    $carrec = config('certificat_carrec') ?: ($secretaria && !config('certificat_signant') ? mb_strtolower(t('opt.secretaria')) : t('cert.carrec_defecte'));
    $president = config('certificat_president') ?: ($presidencia ? trim($presidencia['nom'] . ' ' . $presidencia['cognoms']) : null);
    return [$signant, $carrec, $president];
}

/** Quan s'ha de renovar la junta: a partir de l'inici de la presidència actual i dels anys de mandat dels estatuts */
function renovacio_junta(): ?array
{
    $presidencia = carrec_vigent('presidencia');
    $anys = (int)(config('junta_anys_mandat') ?: 0);
    if (!$presidencia || $anys < 1) {
        return null;
    }
    $limit = date('Y-m-d', strtotime($presidencia['data_inici'] . " +$anys years"));
    $dies = (int)floor((strtotime($limit) - strtotime(date('Y-m-d'))) / 86400);
    return ['eleccio' => $presidencia['data_inici'], 'limit' => $limit, 'dies' => $dies, 'anys' => $anys];
}

function pagina_junta(): void
{
    requereix('societat.view');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('societat.edit');
        if (get('accio') === 'mandat') {
            $anys = (int)post('anys');
            desa_config('junta_anys_mandat', $anys > 0 && $anys <= 20 ? (string)$anys : null);
            avis(t('desat'));
        } elseif (get('accio') === 'tanca') {
            $data = normalitza(['tipus' => 'date'], post('data')) ?? date('Y-m-d');
            $n = q("UPDATE carrecs SET data_fi = ? WHERE data_fi IS NULL OR data_fi > ?", [$data, $data])->rowCount();
            audita('tanca_junta', 'carrecs', null, "$data · $n");
            avis(t('junta.tancada', $n, data_llegible($data)));
        }
        redirigeix('junta');
    }

    $vigents = carrecs_vigents();
    $html = '';
    $r = renovacio_junta();
    if ($r) {
        $tipus = $r['dies'] < 0 ? 'error' : ($r['dies'] <= 120 ? 'atencio' : 'info');
        $html .= '<div class="avis ' . $tipus . '">' . h(t($r['dies'] < 0 ? 'junta.renovacio_passada' : 'junta.renovacio', data_llegible($r['eleccio']), $r['anys'], data_llegible($r['limit']))) . ' ' . h(t('junta.registre')) . '</div>';
    }
    if (pot('societat.edit')) {
        $html .= '<div class="accions"><a class="boto" href="' . h(url('edita', ['e' => 'carrecs', 'tornar' => url('junta')])) . '">+ ' . h(t('junta.afig')) . '</a>'
            . ($vigents ? boto_document(url('junta_doc'), t('junta.certificat')) : '') . '<a class="boto secundari" href="' . h(url('llista', ['e' => 'reunions'])) . '">📖 ' . h(t('ent.reunions')) . '</a></div>';
    }
    $html .= '<section class="fill"><header><h2>' . h(t('junta.actual')) . ' <span class="compte">' . count($vigents) . '</span></h2></header>';
    if (!$vigents) {
        $html .= '<p class="buit petit">' . h(t('junta.buida')) . '</p>';
    } else {
        $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.carrec')) . '</th><th>' . h(t('camp.persona_id')) . '</th><th>' . h(t('camp.data_inici')) . '</th><th>' . h(t('camp.data_fi')) . '</th></tr></thead><tbody>';
        foreach ($vigents as $c) {
            $u = url('fitxa', ['e' => 'carrecs', 'id' => $c['id']]);
            $html .= '<tr data-href="' . h($u) . '"><td><a href="' . h($u) . '">' . h(nom_carrec($c)) . '</a></td><td>' . h(nom_persona($c)) . '</td><td>' . h(data_llegible($c['data_inici'])) . '</td><td>' . h(data_llegible($c['data_fi'])) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '</section>';

    // Historial: juntes anteriors agrupades per la data d'inici
    $anteriors = q("SELECT t.*, p.nom, p.cognoms FROM carrecs t JOIN persones p ON p.id = t.persona_id WHERE t.data_fi IS NOT NULL AND t.data_fi < date('now','localtime')
        ORDER BY t.data_fi DESC, " . ORDRE_CARRECS_SQL)->fetchAll();
    if ($anteriors) {
        $html .= '<section class="fill"><header><h2>' . h(t('junta.historial')) . '</h2></header>';
        $grups = [];
        foreach ($anteriors as $c) {
            $grups[data_llegible($c['data_inici']) . ' – ' . data_llegible($c['data_fi'])][] = $c;
        }
        foreach ($grups as $periode => $llista) {
            $html .= '<h3>' . h($periode) . '</h3><ul class="llista-simple">';
            foreach ($llista as $c) {
                $html .= '<li><a href="' . h(url('fitxa', ['e' => 'carrecs', 'id' => $c['id']])) . '">' . h(nom_carrec($c)) . '</a> — ' . h(nom_persona($c)) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';
    }

    if (pot('societat.edit')) {
        $html .= '<section class="fill"><header><h2>' . h(t('junta.gestio')) . '</h2></header><div class="graella-informes graella-genera">'
            . '<form class="informe" method="post" action="' . h(url('junta', ['accio' => 'mandat'])) . '">' . csrf_camp() . '<b>' . h(t('junta.mandat')) . '</b><small>' . h(t('junta.mandat_ajuda')) . '</small>'
            . '<label>' . h(t('junta.anys')) . ' <input type="number" name="anys" min="1" max="20" value="' . h(config('junta_anys_mandat')) . '"></label><button>' . h(t('desa')) . '</button></form>'
            . ($vigents ? '<form class="informe" method="post" action="' . h(url('junta', ['accio' => 'tanca'])) . '" onsubmit="return confirm(\'' . h(t('junta.confirma_tanca')) . '\')">' . csrf_camp()
                . '<b>' . h(t('junta.nova')) . '</b><small>' . h(t('junta.nova_ajuda')) . '</small><label>' . h(t('junta.data_fi')) . ' <input type="date" name="data" value="' . h(date('Y-m-d')) . '" required></label>'
                . '<button class="secundari">' . h(t('junta.tanca')) . '</button></form>' : '')
            . '</div></section>';
    }
    pagina(t('menu.junta'), $html);
}

/** Certificat de la composició de la junta (per al banc, el registre, subvencions…) */
function pagina_junta_doc(): void
{
    requereix('societat.view');
    [$signant, $carrec, $president] = signants_certificat();
    $societat = config('societat_nom') ?: 'Teixit';
    document_inici(t('junta.certificat'), t('junta.certificat'), t('cert.consell'));
    echo '<section class="full document certificat">' . capcalera_societat() . '<h2 class="centrat">' . h(t('cert.titol')) . '</h2>';
    echo '<p class="justificat">' . ($signant ? h($signant) : '<span class="linia llarga"></span>') . ', ' . h(t('cert.intro', $carrec, $societat)) . '</p>';
    echo '<p class="certifica">' . h(t('cert.certifica')) . '</p><p class="justificat">' . h(t('junta.cert_text')) . '</p>';
    echo '<table class="taula-llistat"><thead><tr><th>' . h(t('camp.carrec')) . '</th><th>' . h(t('camp.nom')) . '</th><th>' . h(t('camp.document')) . '</th><th>' . h(t('camp.data_inici')) . '</th></tr></thead><tbody>';
    foreach (carrecs_vigents() as $c) {
        echo '<tr><td>' . h(nom_carrec($c)) . '</td><td>' . h(trim($c['nom'] . ' ' . $c['cognoms'])) . '</td><td>' . h($c['document']) . '</td><td>' . h(data_llegible($c['data_inici'])) . '</td></tr>';
    }
    echo '</tbody></table>';
    $poblacio = config('societat_poblacio');
    echo '<p class="justificat">' . h($poblacio ? t('cert.final', $poblacio, data_llarga()) : t('cert.final_sense', data_llarga())) . '</p>';
    echo signatures_document($signant, $carrec, $president) . '</section>';
    audita('certificat_junta');
    document_fi();
}

/** Signatures del secretari/ària amb el vistiplau del president/a */
function signatures_document(?string $signant, string $carrec, ?string $president): string
{
    $html = '<footer><div class="signatures">';
    if ($president) {
        $html .= '<div><span class="rol-signa">' . h(t('cert.vistiplau')) . '</span>' . h($president) . '<br><small>' . h(t('cert.president')) . '</small></div>';
    }
    return $html . '<div><span class="rol-signa">' . h(t('cert.signatura')) . '</span>' . h($signant ?: '') . '<br><small>' . h(mb_strtoupper(mb_substr($carrec, 0, 1)) . mb_substr($carrec, 1)) . '</small></div></div></footer>';
}

/* ---------------- Reunions i actes ---------------- */

/** Línies d'un camp de text (ordre del dia, acords), sense numeració inicial */
function linies_text(?string $text): array
{
    $l = [];
    foreach (preg_split('/\R/', (string)$text) as $linia) {
        $linia = trim(preg_replace('/^\s*(\d+[\.\)\-]|[-•*])\s*/u', '', $linia));
        if ($linia !== '') {
            $l[] = $linia;
        }
    }
    return $l;
}

function accions_reunio(array $f): string
{
    $html = boto_document(url('reunio_doc', ['id' => $f['id'], 'doc' => 'convocatoria']), t('reunio.convocatoria'))
        . boto_document(url('reunio_doc', ['id' => $f['id'], 'doc' => 'acta']), t('reunio.acta'));
    $acords = linies_text($f['acords']);
    if ($acords) {
        $ops = '';
        foreach ($acords as $i => $a) {
            $ops .= '<option value="' . ($i + 1) . '">' . ($i + 1) . '. ' . h(mb_strimwidth($a, 0, 60, '…')) . '</option>';
        }
        $html .= '<form class="en-linia" method="get" target="_blank"><input type="hidden" name="r" value="reunio_doc"><input type="hidden" name="id" value="' . (int)$f['id'] . '">'
            . '<input type="hidden" name="doc" value="certificat"><select name="acord">' . $ops . '</select><button class="secundari">🖨 ' . h(t('reunio.certificat')) . '</button></form>';
    }
    return $html;
}

function pagina_reunio_doc(): void
{
    requereix('societat.view');
    $r = fila('SELECT * FROM reunions WHERE id = ?', [(int)get('id')]);
    if (!$r) {
        redirigeix('llista', ['e' => 'reunions']);
    }
    $doc = in_array(get('doc'), ['convocatoria', 'acta', 'certificat'], true) ? get('doc') : 'acta';
    [$signant, $carrec, $president] = signants_certificat();
    $societat = config('societat_nom') ?: 'Teixit';
    $organ = t('reunio.organ.' . $r['tipus']);
    $data = data_llarga($r['data']);
    $poblacio = config('societat_poblacio');
    $titol = t('reunio.titol_' . $doc, $organ);
    document_inici($titol, $titol, t('cert.consell'));
    echo '<section class="full document certificat">' . capcalera_societat() . '<h2 class="centrat">' . h($titol) . '</h2>';
    $ordre = linies_text($r['ordre_del_dia']);
    $acords = linies_text($r['acords']);

    if ($doc === 'convocatoria') {
        $hores = $r['hora'] ? ($r['hora_segona'] ? t('reunio.hores_dues', $r['hora'], $r['hora_segona']) : t('reunio.hora', $r['hora'])) : '';
        echo '<p class="justificat">' . h(t('reunio.conv_text', $organ, $data, $hores, o_text($r['lloc']))) . '</p>';
        echo '<ol class="llista-cert">' . implode('', array_map(fn($p) => '<li>' . h($p) . '</li>', $ordre ?: ['……'])) . '</ol>';
        if (str_starts_with($r['tipus'], 'assemblea')) {
            echo '<p class="justificat">' . h(t('reunio.conv_socis')) . '</p>';
        }
        echo '<p class="justificat">' . h(($poblacio ? $poblacio . ', ' : '') . data_llarga($r['data_convocatoria'] ?: date('Y-m-d'))) . '</p>';
    } elseif ($doc === 'acta') {
        $assistents = $r['assistents'] ?: ($r['tipus'] === 'junta' ? implode("\n", array_map(fn($c) => trim($c['nom'] . ' ' . $c['cognoms']) . ' (' . nom_carrec($c) . ')', carrecs_vigents())) : '');
        echo '<p class="justificat">' . h(t('reunio.acta_intro', o_text($r['lloc']), $data, $r['hora'] ?: '___', $organ, $societat)) . '</p>';
        echo '<h3>' . h(t('camp.assistents')) . '</h3><p>' . nl2br(h($assistents ?: '……')) . '</p>';
        echo '<h3>' . h(t('camp.ordre_del_dia')) . '</h3><ol class="llista-cert">' . implode('', array_map(fn($p) => '<li>' . h($p) . '</li>', $ordre ?: ['……'])) . '</ol>';
        if ($r['desenvolupament']) {
            echo '<h3>' . h(t('camp.desenvolupament')) . '</h3><p class="justificat">' . nl2br(h($r['desenvolupament'])) . '</p>';
        }
        echo '<h3>' . h(t('camp.acords')) . '</h3><ol class="llista-cert">' . implode('', array_map(fn($a) => '<li>' . h($a) . '</li>', $acords ?: ['……'])) . '</ol>';
        echo '<p class="justificat">' . h(t('reunio.acta_final', $r['hora_fi'] ?: '___')) . '</p>';
    } else {
        $n = max(1, (int)get('acord'));
        $acord = $acords[$n - 1] ?? '……';
        echo '<p class="justificat">' . ($signant ? h($signant) : '<span class="linia llarga"></span>') . ', ' . h(t('cert.intro', $carrec, $societat)) . '</p>';
        echo '<p class="certifica">' . h(t('cert.certifica')) . '</p>';
        echo '<p class="justificat">' . h(t('reunio.cert_text', $organ, $data)) . '</p><blockquote class="acord">' . h($acord) . '</blockquote>';
        echo '<p class="justificat">' . h($poblacio ? t('cert.final', $poblacio, data_llarga()) : t('cert.final_sense', data_llarga())) . '</p>';
    }
    echo signatures_document($signant, $carrec, $president) . '</section>';
    audita('reunio_' . $doc, 'reunions', $r['id']);
    document_fi();
}

/* ---------------- Documents ---------------- */

function avis_document(array $f): ?string
{
    if (!$f['data_caducitat']) {
        return null;
    }
    if ($f['data_caducitat'] < date('Y-m-d')) {
        return t('doc.caducat', data_llegible($f['data_caducitat']));
    }
    return $f['data_caducitat'] <= date('Y-m-d', strtotime('+60 days')) ? t('doc.caduca', data_llegible($f['data_caducitat'])) : null;
}

/** Comprova el fitxer pujat (si n'hi ha) abans de desar el document */
function valida_fitxer_document(): ?string
{
    $f = $_FILES['fitxer'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MIDA_DOCUMENT_MB * 1024 * 1024) {
        return t('doc.err_mida', MIDA_DOCUMENT_MB);
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return t('doc.err_pujar');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    return isset(FITXERS_PERMESOS[$ext]) ? null : t('doc.err_tipus', implode(', ', array_keys(FITXERS_PERMESOS)));
}

/** Guarda el fitxer pujat dins de la base de dades (substitueix l'anterior) */
function desa_fitxer_document(int $id): void
{
    $f = $_FILES['fitxer'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $nom = mb_substr(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '_', basename($f['name'])), 0, 150);
    $st = db()->prepare('INSERT INTO document_fitxers(document_id, contingut) VALUES(?, ?) ON CONFLICT(document_id) DO UPDATE SET contingut = excluded.contingut');
    $fh = fopen($f['tmp_name'], 'rb');
    $st->bindValue(1, $id, PDO::PARAM_INT);
    $st->bindValue(2, $fh, PDO::PARAM_LOB);
    $st->execute();
    fclose($fh);
    q('UPDATE documents SET fitxer_nom = ?, fitxer_tipus = ?, fitxer_mida = ? WHERE id = ?', [$nom, FITXERS_PERMESOS[$ext] ?? 'application/octet-stream', (int)$f['size'], $id]);
}

function serveix_document(): never
{
    requereix('societat.view');
    $d = fila('SELECT * FROM documents WHERE id = ?', [(int)get('id')]);
    $contingut = $d ? valor('SELECT contingut FROM document_fitxers WHERE document_id = ?', [$d['id']]) : null;
    if (!$d || $contingut === null) {
        http_response_code(404);
        exit(t('err.no_trobat'));
    }
    audita('obri_document', 'documents', $d['id']);
    $enLinia = get('baixa') !== '1' && in_array($d['fitxer_tipus'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true);
    header('Content-Type: ' . $d['fitxer_tipus'] . ($d['fitxer_tipus'] === 'text/plain' ? '; charset=UTF-8' : ''));
    header('Content-Length: ' . strlen($contingut));
    header('Content-Disposition: ' . ($enLinia ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($d['fitxer_nom']));
    echo $contingut;
    exit;
}

/* ---------------- Full d'alta de soci ---------------- */

function pagina_full_alta(): void
{
    requereix('socis.view');
    $societat = config('societat_nom') ?: 'Teixit';
    $nif = config('sepa_nif') ?: config('societat_cif');
    // Tipus de soci: amb la quota si n'hi ha tarifa
    $tarifes = [];
    foreach (q("SELECT * FROM tarifes WHERE tipus = 'quota_soci' AND estat = 'actiu' ORDER BY id")->fetchAll() as $t) {
        $tarifes[$t['tipus_soci'] ?? ''] ??= $t;
    }
    $tipus = '';
    // Els tipus que la societat ha marcat per al full d'alta (els d'honor, per exemple, no es demanen: els nomena la societat)
    foreach (q("SELECT * FROM tipus_socis WHERE estat = 'actiu' AND full_alta = 1 ORDER BY COALESCE(ordre, 999999), nom")->fetchAll() as $ts) {
        $t = $tarifes[(string)$ts['id']] ?? $tarifes[''] ?? null;
        $tipus .= '<span><span class="casella"></span>' . h($ts['nom']) . ($t ? ' <small>(' . h(euros($t['import']) . ' ' . mb_strtolower(t('opt.' . $t['periodicitat']))) . ')</small>' : '')
            . ($ts['descripcio'] ? ' <small class="desc-tipus">' . h($ts['descripcio']) . '</small>' : '') . '</span>';
    }
    $caselles = fn(int $n) => str_repeat('<span class="caixeta"></span>', $n);
    $intro = config('full_alta_intro') ?: t('alta.intro_defecte', $societat);
    $peu = config('full_alta_peu') ?: t('alta.peu_defecte', $societat, config('societat_email') ?: config('societat_adreca') ?: '…');

    document_inici(t('alta.titol'), t('alta.titol'), t('alta.consell'));
    echo '<section class="full document full-alta">' . capcalera_societat() . '<h2 class="centrat">' . h(t('alta.titol')) . '</h2>';
    echo '<p class="justificat">' . nl2br(h($intro)) . '</p>';
    echo '<h3>' . h(t('alta.dades')) . '</h3><table class="formulari-paper">'
        . '<tr><th>' . h(t('camp.nom')) . '</th><td></td><th>' . h(t('camp.cognoms')) . '</th><td></td></tr>'
        . '<tr><th>' . h(t('camp.document')) . '</th><td></td><th>' . h(t('camp.data_naixement')) . '</th><td></td></tr>'
        . '<tr><th>' . h(t('camp.adreca')) . '</th><td colspan="3"></td></tr>'
        . '<tr><th>' . h(t('camp.codi_postal')) . '</th><td></td><th>' . h(t('camp.poblacio')) . '</th><td></td></tr>'
        . '<tr><th>' . h(t('camp.telefon')) . '</th><td></td><th>' . h(t('camp.email')) . '</th><td></td></tr></table>';
    echo '<h3>' . h(t('alta.tipus')) . '</h3><div class="opcions-paper">' . $tipus . '</div>';
    echo '<h3>' . h(t('alta.pagament')) . '</h3><div class="opcions-paper">'
        . '<span><span class="casella"></span>' . h(t('opt.domiciliacio')) . '</span><span><span class="casella"></span>' . h(t('opt.efectiu')) . '</span><span><span class="casella"></span>' . h(t('opt.transferencia')) . '</span></div>';
    echo '<div class="bloc-sepa"><b>' . h(t('alta.sepa_titol')) . '</b> <small>' . h(t('alta.sepa_ref', $nif ? identificador_creditor($nif, config('sepa_sufix') ?: '000') : '…')) . '</small>'
        . '<table class="formulari-paper"><tr><th>' . h(t('mandat.titular')) . '</th><td colspan="3"></td></tr>'
        . '<tr><th>IBAN</th><td colspan="3" class="caixetes">' . $caselles(4) . ' ' . $caselles(4) . ' ' . $caselles(4) . ' ' . $caselles(4) . ' ' . $caselles(4) . ' ' . $caselles(4) . '</td></tr></table>'
        . '<p class="text-legal">' . h(t('mandat.text_legal', $societat)) . '</p></div>';
    echo '<h3>' . h(t('alta.imatge')) . '</h3><div class="opcions-paper"><span><span class="casella"></span>' . h(t('opt.autoritza')) . '</span><span><span class="casella"></span>' . h(t('opt.no_autoritza')) . '</span></div>';
    echo '<p class="lloc-data">' . h(t('alta.lloc_data')) . '</p>';
    echo '<footer><div class="signatures"><div>' . h(t('alta.signatura')) . '</div></div></footer>';
    echo '<p class="text-legal">' . nl2br(h($peu)) . '</p></section>';
    document_fi();
}

/* ---------------- Comunicats: llistes de correu ---------------- */

/** Grups de destinataris possibles: clau => etiqueta */
function grups_destinataris(): array
{
    $escola = modul_actiu('escola');
    $g = ['socis' => t('com.socis')] + (modul_actiu('junta') ? ['junta' => t('com.junta')] : [])
        + ($escola ? ['professorat' => t('com.professorat'), 'families' => t('com.families')] : []) + ['tothom' => t('com.tothom')];
    foreach (modul_actiu('agrupacions') ? q("SELECT id, nom FROM agrupacions WHERE estat = 'actiu' ORDER BY nom")->fetchAll() : [] as $a) {
        $g['agrupacio:' . $a['id']] = t('com.agrupacio', $a['nom']);
    }
    if ($escola && ($curs = curs_actual())) {
        foreach (opcions_grups((int)$curs['id']) as $id => $nom) {
            $g['grup:' . $id] = t('com.grup', $nom);
        }
    }
    return $g;
}

/** Persones d'un grup de destinataris (id, nom, cognoms, email) */
function persones_destinataries(string $grup): array
{
    $curs = (int)(curs_actual()['id'] ?? 0);
    // Famílies: tutors que reben comunicacions i alumnes majors d'edat (d'un grup o de tota l'escola)
    $families = function (?int $grup) use ($curs): array {
        $filtre = $grup ? 'AND m.id IN (SELECT matricula_id FROM matricula_grups WHERE grup_id = ' . (int)$grup . ')' : '';
        return q("SELECT DISTINCT p.id, p.nom, p.cognoms, p.email FROM matricules m JOIN alumnes al ON al.id = m.alumne_id
            JOIN tutories tu ON tu.alumne_id = al.id AND tu.rep_comunicacions = 1 JOIN persones p ON p.id = tu.tutor_id
            WHERE m.curs_id = ? AND m.estat = 'actiu' $filtre
            UNION SELECT DISTINCT p.id, p.nom, p.cognoms, p.email FROM matricules m JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
            WHERE m.curs_id = ? AND m.estat = 'actiu' AND p.data_naixement <= date('now','localtime','-18 years') $filtre", [$curs, $curs])->fetchAll();
    };
    [$tipus, $id] = array_pad(explode(':', $grup, 2), 2, null);
    $files = match ($tipus) {
        'socis' => q("SELECT p.id, p.nom, p.cognoms, p.email FROM socis s JOIN persones p ON p.id = s.persona_id WHERE s.estat = 'actiu'")->fetchAll(),
        'junta' => q("SELECT p.id, p.nom, p.cognoms, p.email FROM carrecs t JOIN persones p ON p.id = t.persona_id WHERE t.data_fi IS NULL OR t.data_fi >= date('now','localtime')")->fetchAll(),
        'professorat' => q("SELECT p.id, p.nom, p.cognoms, p.email FROM professors pr JOIN persones p ON p.id = pr.persona_id WHERE pr.estat = 'actiu'")->fetchAll(),
        'agrupacio' => q("SELECT p.id, p.nom, p.cognoms, p.email FROM membres m JOIN persones p ON p.id = m.persona_id WHERE m.estat = 'actiu' AND m.agrupacio_id = ?", [(int)$id])->fetchAll(),
        'families' => $families(null),
        'grup' => $families((int)$id),
        'tothom' => q('SELECT id, nom, cognoms, email FROM persones WHERE actiu = 1')->fetchAll(),
        default => [],
    };
    $unics = [];
    foreach ($files as $f) {
        $unics[$f['id']] = $f;
    }
    usort($unics, fn($a, $b) => strcmp(nom_persona($a), nom_persona($b)));
    return $unics;
}

function pagina_comunicats(): void
{
    requereix('societat.view');
    $grups = grups_destinataris();
    $grup = isset($grups[get('a')]) ? get('a') : 'socis';
    $persones = persones_destinataries($grup);
    $adreces = [];
    $sense = [];
    foreach ($persones as $p) {
        $e = trim((string)$p['email']);
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $adreces[mb_strtolower($e)] = $e; // una adreça compartida (pare i mare) només una vegada
        } else {
            $sense[] = $p;
        }
    }
    $llista = implode('; ', $adreces);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('societat.edit');
        $assumpte = trim((string)post('assumpte'));
        if ($assumpte !== '') {
            q('INSERT INTO comunicats(data, destinataris, n_destinataris, adreces, assumpte, cos, usuari) VALUES(?,?,?,?,?,?,?)', [
                normalitza(['tipus' => 'date'], post('data')) ?? date('Y-m-d'), $grups[$grup], count($adreces), $llista, $assumpte, post('cos') ?: null, usuari()['usuari'] ?? null,
            ]);
            audita('comunicat', 'comunicats', (int)db()->lastInsertId(), $assumpte);
            avis(t('com.registrat'));
        }
        redirigeix('comunicats', ['a' => $grup]);
    }

    $ops = '';
    foreach ($grups as $k => $nom) {
        $ops .= '<option value="' . h($k) . '"' . ($k === $grup ? ' selected' : '') . '>' . h($nom) . '</option>';
    }
    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="comunicats"><label>' . h(t('com.a_qui')) . ' <select name="a" onchange="this.form.submit()">' . $ops . '</select></label>'
        . '<noscript><button>' . h(t('filtrar')) . '</button></noscript></form>';
    $html .= '<div class="targetes">' . targeta((string)count($adreces), t('com.adreces')) . targeta((string)count($sense), t('com.sense_correu')) . '</div>';
    if ($adreces) {
        $html .= '<section class="fill"><header><h2>' . h(t('com.llista')) . '</h2></header><p>' . h(t('com.llista_ajuda')) . '</p>'
            . '<textarea id="adreces" class="adreces" rows="4" readonly>' . h($llista) . '</textarea>'
            . '<div class="accions"><button type="button" onclick="const a=document.getElementById(\'adreces\');a.select();navigator.clipboard&&navigator.clipboard.writeText(a.value);this.textContent=\'✔ ' . h(t('com.copiat')) . '\'">📋 ' . h(t('com.copia')) . '</button>'
            . (strlen($llista) < 1800 ? '<a class="boto secundari" href="mailto:?bcc=' . h(rawurlencode(implode(',', $adreces))) . '">✉ ' . h(t('com.obri_correu')) . '</a>' : '')
            . '</div></section>';
    }
    if ($sense) {
        $html .= '<details class="fill"><summary>' . h(t('com.sense_llista', count($sense))) . '</summary><ul class="llista-simple">';
        foreach ($sense as $p) {
            $html .= '<li><a href="' . h(url('fitxa', ['e' => 'persones', 'id' => $p['id']])) . '">' . h(nom_persona($p)) . '</a></li>';
        }
        $html .= '</ul></details>';
    }
    if ($adreces && pot('societat.edit')) {
        $html .= '<section class="fill"><header><h2>' . h(t('com.registra')) . '</h2></header><p>' . h(t('com.registra_ajuda')) . '</p><form method="post" class="formulari">' . csrf_camp() . '<div class="camps">'
            . '<div class="camp"><label>' . h(t('camp.assumpte')) . ' <span class="obl">*</span></label><input name="assumpte" required></div>'
            . '<div class="camp"><label>' . h(t('camp.data')) . '</label><input type="date" name="data" value="' . h(date('Y-m-d')) . '"></div>'
            . '<div class="camp ample"><label>' . h(t('camp.cos')) . '</label><textarea name="cos" rows="4"></textarea></div></div>'
            . '<div class="accions"><button>' . h(t('com.registra_boto')) . '</button></div></form></section>';
    }
    $fets = q('SELECT * FROM comunicats ORDER BY data DESC, id DESC LIMIT 15')->fetchAll();
    if ($fets) {
        $html .= '<section class="fill"><header><h2>' . h(t('com.historial')) . '</h2><a class="boto petit secundari" href="' . h(url('llista', ['e' => 'comunicats'])) . '">' . h(t('com.tots')) . '</a></header><ul class="llista-simple">';
        foreach ($fets as $c) {
            $html .= '<li><span class="hora">' . h(data_llegible($c['data'])) . '</span> <a href="' . h(url('fitxa', ['e' => 'comunicats', 'id' => $c['id']])) . '">' . h($c['assumpte']) . '</a> — '
                . h($c['destinataris']) . ' <span class="etiqueta">' . (int)$c['n_destinataris'] . '</span></li>';
        }
        $html .= '</ul></section>';
    }
    $html .= '<p class="nota">' . h(t('com.nota')) . '</p>';
    pagina(t('menu.comunicats'), $html);
}
