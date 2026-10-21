<?php
// Llistats per a imprimir, etiquetes, antiguitat i dades per a la memòria anual
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Cada informe retorna seccions (títol, columnes, files) i el mateix codi les mostra
// a la pantalla, les prepara per a imprimir o les exporta a CSV.

function edat(?string $naixement, ?string $referencia = null): ?int
{
    if (!$naixement) {
        return null;
    }
    $n = new DateTime($naixement);
    $r = new DateTime($referencia ?: date('Y-m-d'));
    return $n > $r ? 0 : $n->diff($r)->y;
}

function nom_complet(array $p): string
{
    return trim(($p['cognoms'] ?? '') !== '' ? $p['cognoms'] . ', ' . $p['nom'] : $p['nom']);
}

/** Grups d'un curs per a un selector */
function opcions_grups(int $curs): array
{
    return array_column(q("SELECT g.id, a.nom || ' · ' || g.nom AS e FROM grups g JOIN assignatures a ON a.id = g.assignatura_id
        WHERE g.curs_id = ? AND g.estat = 'actiu' ORDER BY e", [$curs])->fetchAll(), 'e', 'id');
}

/** Llista d'informes disponibles: clau => [capacitat, icona] */
function informes(): array
{
    return [
        'alumnes_grup' => ['escola.view', '👥'],
        'socis' => ['socis.view', '★'],
        'musics' => ['agrupacions.view', '🙋'],
        'consentiments' => ['persones.view', '📷'],
        'etiquetes' => ['persones.view', '🏷'],
        'antiguitat' => ['socis.view', '🏅'],
        'memoria' => ['socis.view', '📊'],
    ];
}

/** Mòdul del qual depén cada informe (si està desactivat, no es mostra) */
const MODUL_INFORMES = ['alumnes_grup' => 'escola', 'musics' => 'agrupacions'];

/* ---------- Paràmetres de cada informe ---------- */

function camp_param(string $etq, string $control, string $ajuda = ''): string
{
    return '<div class="camp"><label>' . h($etq) . '</label>' . $control . ($ajuda ? '<small>' . h($ajuda) . '</small>' : '') . '</div>';
}

function params_informe(string $inf): string
{
    $curs = (int)get('curs', (string)(curs_actual()['id'] ?? 0));
    switch ($inf) {
        case 'alumnes_grup':
            return camp_param(t('camp.curs_id'), select_simple('curs', opcions_ref('cursos'), $curs, false))
                . camp_param(t('camp.grup_id'), select_simple('grup', opcions_grups($curs), get('grup'), true, t('llist.tria_grup')));
        case 'socis':
            $tipus = opcions_ref('tipus_socis');
            return camp_param(t('camp.tipus'), select_simple('tipus', $tipus, get('tipus'), true, t('filtre.tots')))
                . '<div class="camp camp-check"><label><input type="checkbox" name="baixes" value="1"' . (get('baixes') === '1' ? ' checked' : '') . ' onchange="this.form.submit()"> ' . h(t('mostra_baixes')) . '</label></div>';
        case 'musics':
            return camp_param(t('camp.agrupacio_id'), select_simple('agrupacio', array_column(q("SELECT id, nom FROM agrupacions WHERE estat = 'actiu' ORDER BY nom")->fetchAll(), 'nom', 'id'), get('agrupacio'), true, t('llist.tria_agrupacio')));
        case 'consentiments':
            return camp_param(t('camp.grup_id'), select_simple('grup', opcions_grups((int)(curs_actual()['id'] ?? 0)), get('grup'), true, t('llist.tot_alumnat')));
        case 'antiguitat':
            return camp_param(t('antig.any'), '<input type="number" name="any" min="1900" max="2200" value="' . h(get('any', date('Y'))) . '" onchange="this.form.submit()">')
                . camp_param(t('antig.fites'), '<input name="fites" value="' . h(get('fites', config('antiguitat_fites', '25, 50'))) . '" onchange="this.form.submit()">', t('antig.fites_ajuda'))
                . '<div class="camp camp-check"><label><input type="checkbox" name="tots" value="1"' . (get('tots') === '1' ? ' checked' : '') . ' onchange="this.form.submit()"> ' . h(t('antig.tots')) . '</label></div>';
        case 'memoria':
            return camp_param(t('camp.curs_id'), select_simple('curs', opcions_ref('cursos'), $curs, false));
    }
    return '';
}

/* ---------- Dades de cada informe ---------- */

/** Retorna ['subtitol' => …, 'seccions' => [['titol', 'cols', 'num', 'files', 'destacats', 'peu']], 'nota' => …] o null si falta un paràmetre */
function dades_informe(string $inf): ?array
{
    switch ($inf) {
        case 'alumnes_grup':
            $grup = fila("SELECT g.*, a.nom AS assignatura, c.nom AS curs, " . NOM_PERSONA_SQL . " AS professor FROM grups g JOIN assignatures a ON a.id = g.assignatura_id
                JOIN cursos c ON c.id = g.curs_id LEFT JOIN professors pr ON pr.id = g.professor_id LEFT JOIN persones p ON p.id = pr.persona_id WHERE g.id = ?", [(int)get('grup')]);
            if (!$grup) {
                return null;
            }
            $alumnes = q("SELECT p.* FROM matricula_grups mg JOIN matricules m ON m.id = mg.matricula_id JOIN alumnes al ON al.id = m.alumne_id
                JOIN persones p ON p.id = al.persona_id WHERE mg.grup_id = ? AND m.estat = 'actiu' ORDER BY p.cognoms, p.nom", [$grup['id']])->fetchAll();
            $files = [];
            foreach ($alumnes as $a) {
                $tutors = tutors_persona((int)$a['id']);
                $files[] = [
                    nom_complet($a),
                    (string)(edat($a['data_naixement']) ?? ''),
                    implode("\n", array_map(fn($tu) => trim($tu['nom'] . ' ' . $tu['cognoms']) . ($tu['tipus_relacio'] ? ' (' . t('opt.' . $tu['tipus_relacio']) . ')' : ''), $tutors)),
                    implode("\n", array_filter($tutors ? array_column($tutors, 'telefon') : [$a['telefon']])),
                    implode("\n", array_filter($tutors ? array_column($tutors, 'email') : [$a['email']])),
                    t('imatge.' . $a['consentiment_imatge']),
                ];
            }
            return [
                'titol' => $grup['assignatura'] . ' · ' . $grup['nom'],
                'subtitol' => t('camp.curs_id') . ' ' . $grup['curs'] . ($grup['professor'] ? ' · ' . t('camp.professor_id') . ': ' . $grup['professor'] : ''),
                'seccions' => [[
                    'cols' => [t('ent1.alumnes'), t('llist.edat'), t('llist.tutors'), t('camp.telefon'), t('camp.email'), t('llist.imatge')],
                    'num' => [1], 'files' => $files,
                    'destacats' => array_keys(array_filter($alumnes, fn($a) => $a['consentiment_imatge'] !== 'autoritza')),
                ]],
                'nota' => t('llist.nota_imatge'),
            ];

        case 'socis':
            $where = get('baixes') === '1' ? '1=1' : "s.estat = 'actiu'";
            $params = [];
            if (get('tipus') !== '') {
                $where .= ' AND s.tipus = ?';
                $params[] = get('tipus');
            }
            $socis = q("SELECT s.*, p.nom, p.cognoms, p.document, p.telefon, p.email FROM socis s JOIN persones p ON p.id = s.persona_id WHERE $where
                ORDER BY (COALESCE(s.numero, '') = ''), CAST(s.numero AS INTEGER), s.numero, p.cognoms, p.nom", $params)->fetchAll();
            $files = array_map(fn($s) => [(string)$s['numero'], nom_complet($s), (string)$s['document'], (string)$s['telefon'], (string)$s['email'],
                nom_tipus_soci($s['tipus']), data_llegible($s['data_alta']) . ($s['estat'] !== 'actiu' ? ' · ' . t('opt.baixa') : '')], $socis);
            return [
                'titol' => t('llist.socis_titol'),
                'subtitol' => t('n_resultats', count($socis)) . (get('tipus') !== '' ? ' · ' . nom_tipus_soci(get('tipus')) : ''),
                'seccions' => [['cols' => [t('camp.numero'), t('ent1.socis'), t('camp.document'), t('camp.telefon'), t('camp.email'), t('camp.tipus'), t('camp.data_alta')],
                    'num' => [0], 'files' => $files]],
            ];

        case 'musics':
            $agr = fila('SELECT * FROM agrupacions WHERE id = ?', [(int)get('agrupacio')]);
            if (!$agr) {
                return null;
            }
            $membres = q("SELECT m.*, p.nom, p.cognoms, p.telefon, p.email FROM membres m JOIN persones p ON p.id = m.persona_id
                WHERE m.agrupacio_id = ? AND m.estat = 'actiu' ORDER BY COALESCE(NULLIF(m.instrument_veu, ''), 'zzz'), p.cognoms, p.nom", [$agr['id']])->fetchAll();
            $seccions = [];
            foreach ($membres as $m) {
                $inst = trim((string)$m['instrument_veu']) ?: t('llist.sense_instrument');
                $clau = mb_strtolower($inst);
                $seccions[$clau] ??= ['titol' => $inst, 'cols' => [t('llist.music'), t('camp.funcio'), t('camp.telefon'), t('camp.email'), t('camp.data_alta')], 'files' => []];
                $seccions[$clau]['files'][] = [nom_complet($m), $m['funcio'] !== 'membre' ? t('opt.' . $m['funcio']) : '', (string)$m['telefon'], (string)$m['email'], data_llegible($m['data_alta'])];
            }
            foreach ($seccions as &$s) {
                $s['titol'] .= ' (' . count($s['files']) . ')';
            }
            return ['titol' => $agr['nom'], 'subtitol' => t('llist.n_musics', count($membres)), 'seccions' => array_values($seccions)];

        case 'consentiments':
            $curs = curs_actual();
            if (get('grup') !== '') {
                $persones = q("SELECT DISTINCT p.* FROM matricula_grups mg JOIN matricules m ON m.id = mg.matricula_id JOIN alumnes al ON al.id = m.alumne_id
                    JOIN persones p ON p.id = al.persona_id WHERE mg.grup_id = ? AND m.estat = 'actiu' ORDER BY p.cognoms, p.nom", [(int)get('grup')])->fetchAll();
                $titol = opcions_grups((int)($curs['id'] ?? 0))[(int)get('grup')] ?? '';
            } else {
                $persones = q("SELECT DISTINCT p.* FROM matricules m JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
                    WHERE m.curs_id = ? AND m.estat = 'actiu' ORDER BY p.cognoms, p.nom", [$curs['id'] ?? 0])->fetchAll();
                $titol = t('llist.tot_alumnat') . ($curs ? ' · ' . $curs['nom'] : '');
            }
            $compte = array_count_values(array_column($persones, 'consentiment_imatge')) + ['autoritza' => 0, 'no_autoritza' => 0, 'pendent' => 0];
            return [
                'titol' => t('llist.consent_titol') . ' · ' . $titol,
                'subtitol' => t('llist.consent_resum', $compte['autoritza'], $compte['no_autoritza'], $compte['pendent']),
                'seccions' => [[
                    'cols' => [t('ent1.alumnes'), t('llist.edat'), t('llist.imatge'), t('camp.consentiment_data')],
                    'num' => [1],
                    'files' => array_map(fn($p) => [nom_complet($p), (string)(edat($p['data_naixement']) ?? ''), t('imatge.' . $p['consentiment_imatge']), data_llegible($p['consentiment_data'])], $persones),
                    'destacats' => array_keys(array_filter($persones, fn($p) => $p['consentiment_imatge'] !== 'autoritza')),
                ]],
                'nota' => t('llist.nota_imatge'),
            ];

        case 'antiguitat':
            return dades_antiguitat();

        case 'memoria':
            return dades_memoria();
    }
    return null;
}

function dades_antiguitat(): array
{
    $any = max(1900, min(2200, (int)get('any', date('Y'))));
    $fites = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', get('fites', config('antiguitat_fites', '25, 50')))), fn($n) => $n > 0 && $n < 120)));
    sort($fites);
    if (get('fites') !== '' && pot('socis.edit')) {
        desa_config('antiguitat_fites', implode(', ', $fites));
    }
    $seccions = [];
    $colsSoci = [t('ent1.socis'), t('camp.numero'), t('camp.data_alta'), t('antig.anys')];
    $colsMusic = [t('llist.music'), t('ent.agrupacions'), t('antig.primera_alta'), t('antig.anys')];
    $musics = q("SELECT p.id, p.nom, p.cognoms, MIN(m.data_alta) AS alta, GROUP_CONCAT(DISTINCT CASE WHEN m.estat = 'actiu' THEN a.nom END) AS agrupacions
        FROM membres m JOIN persones p ON p.id = m.persona_id JOIN agrupacions a ON a.id = m.agrupacio_id
        WHERE m.data_alta IS NOT NULL AND p.id IN (SELECT persona_id FROM membres WHERE estat = 'actiu') GROUP BY p.id ORDER BY alta, p.cognoms")->fetchAll();
    $socis = q("SELECT s.*, p.nom, p.cognoms FROM socis s JOIN persones p ON p.id = s.persona_id WHERE s.estat = 'actiu' AND s.data_alta IS NOT NULL ORDER BY s.data_alta, p.cognoms")->fetchAll();
    foreach ($fites as $n) {
        $s = array_filter($socis, fn($x) => (int)substr($x['data_alta'], 0, 4) === $any - $n);
        $seccions[] = ['titol' => t('antig.socis_fita', $n, $any), 'cols' => $colsSoci, 'num' => [1, 3],
            'files' => array_map(fn($x) => [nom_complet($x), (string)$x['numero'], data_llegible($x['data_alta']), (string)$n], array_values($s))];
        $m = array_filter($musics, fn($x) => (int)substr($x['alta'], 0, 4) === $any - $n);
        $seccions[] = ['titol' => t('antig.musics_fita', $n, $any), 'cols' => $colsMusic, 'num' => [3],
            'files' => array_map(fn($x) => [nom_complet($x), str_replace(',', ', ', (string)$x['agrupacions']), data_llegible($x['alta']), (string)$n], array_values($m))];
    }
    if (get('tots') === '1') {
        $seccions[] = ['titol' => t('antig.tots_socis'), 'cols' => $colsSoci, 'num' => [1, 3],
            'files' => array_map(fn($x) => [nom_complet($x), (string)$x['numero'], data_llegible($x['data_alta']), (string)($any - (int)substr($x['data_alta'], 0, 4))], $socis)];
        $seccions[] = ['titol' => t('antig.tots_musics'), 'cols' => $colsMusic, 'num' => [3],
            'files' => array_map(fn($x) => [nom_complet($x), str_replace(',', ', ', (string)$x['agrupacions']), data_llegible($x['alta']), (string)($any - (int)substr($x['alta'], 0, 4))], $musics)];
    }
    return ['titol' => t('antig.titol', $any), 'subtitol' => t('antig.subtitol', implode(', ', $fites)), 'seccions' => $seccions, 'nota' => t('antig.nota')];
}

function dades_memoria(): array
{
    $curs = fila('SELECT * FROM cursos WHERE id = ?', [(int)get('curs', (string)(curs_actual()['id'] ?? 0))]);
    $seccions = [];
    $ref = $curs['data_inici'] ?? date('Y-m-d');
    if ($curs && modul_actiu('escola')) {
        $alumnes = q("SELECT DISTINCT p.* FROM matricules m JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
            WHERE m.curs_id = ? AND m.estat = 'actiu'", [$curs['id']])->fetchAll();
        $trams = [[0, 7], [8, 12], [13, 17], [18, 25], [26, 200]];
        $compte = array_fill(0, count($trams) + 1, 0);
        foreach ($alumnes as $a) {
            $e = edat($a['data_naixement'], $ref);
            if ($e === null) {
                $compte[count($trams)]++;
                continue;
            }
            foreach ($trams as $i => [$de, $a2]) {
                if ($e >= $de && $e <= $a2) {
                    $compte[$i]++;
                }
            }
        }
        $files = [];
        foreach ($trams as $i => [$de, $a2]) {
            $files[] = [$de === 0 ? t('mem.fins', $a2) : ($a2 >= 200 ? t('mem.o_mes', $de) : t('mem.de_a', $de, $a2)), (string)$compte[$i]];
        }
        if ($compte[count($trams)]) {
            $files[] = [t('mem.sense_data'), (string)$compte[count($trams)]];
        }
        $menors = count(array_filter($alumnes, fn($a) => $a['data_naixement'] && edat($a['data_naixement'], $ref) < MAJORIA_EDAT));
        $seccions[] = ['titol' => t('mem.alumnes_edat', count($alumnes)), 'cols' => [t('llist.edat'), t('mem.alumnes')], 'num' => [1], 'files' => $files,
            'peu' => [t('mem.menors_majors', $menors, count($alumnes) - $menors), '']];

        $seccions[] = ['titol' => t('mem.per_assignatura'), 'cols' => [t('camp.assignatura_id'), t('ent.grups'), t('mem.alumnes')], 'num' => [1, 2],
            'files' => array_map(fn($r) => [$r['nom'], (string)$r['grups'], (string)$r['alumnes']], q("SELECT a.nom, COUNT(DISTINCT g.id) AS grups,
                COUNT(DISTINCT CASE WHEN m.estat = 'actiu' THEN m.alumne_id END) AS alumnes FROM grups g JOIN assignatures a ON a.id = g.assignatura_id
                LEFT JOIN matricula_grups mg ON mg.grup_id = g.id LEFT JOIN matricules m ON m.id = mg.matricula_id
                WHERE g.curs_id = ? GROUP BY a.id ORDER BY a.nom", [$curs['id']])->fetchAll())];

        $poblacions = [];
        foreach ($alumnes as $a) {
            $pob = trim((string)$a['poblacio']) ?: t('mem.sense_poblacio');
            $poblacions[$pob] = ($poblacions[$pob] ?? 0) + 1;
        }
        arsort($poblacions);
        $seccions[] = ['titol' => t('mem.per_poblacio'), 'cols' => [t('camp.poblacio'), t('mem.alumnes')], 'num' => [1],
            'files' => array_map(fn($k, $v) => [(string)$k, (string)$v], array_keys($poblacions), $poblacions)];

        $prof = (int)valor("SELECT COUNT(DISTINCT professor_id) FROM grups WHERE curs_id = ? AND professor_id IS NOT NULL", [$curs['id']]);
        $grups = (int)valor('SELECT COUNT(*) FROM grups WHERE curs_id = ?', [$curs['id']]);
        $seccions[] = ['titol' => t('mem.escola'), 'cols' => [t('mem.concepte'), t('mem.total')], 'num' => [1],
            'files' => [[t('mem.professors'), (string)$prof], [t('ent.grups'), (string)$grups], [t('mem.alumnes'), (string)count($alumnes)]]];
    }

    $agr = !modul_actiu('agrupacions') ? [] : q("SELECT a.nom, COUNT(DISTINCT m.persona_id) AS musics,
        COUNT(DISTINCT CASE WHEN p.data_naixement > date(?, '-18 years') THEN m.persona_id END) AS menors
        FROM agrupacions a LEFT JOIN membres m ON m.agrupacio_id = a.id AND m.estat = 'actiu' LEFT JOIN persones p ON p.id = m.persona_id
        WHERE a.estat = 'actiu' GROUP BY a.id ORDER BY a.nom", [$ref])->fetchAll();
    if ($agr) {
        $seccions[] = ['titol' => t('mem.agrupacions'), 'cols' => [t('ent1.agrupacions'), t('mem.musics'), t('mem.menors')], 'num' => [1, 2],
            'files' => array_map(fn($r) => [$r['nom'], (string)$r['musics'], (string)$r['menors']], $agr),
            'peu' => [t('mem.musics_diferents'), (string)valor("SELECT COUNT(DISTINCT persona_id) FROM membres WHERE estat = 'actiu'"), '']];
    }

    $any = (int)substr($ref, 0, 4);
    $socis = q("SELECT COALESCE(tipus, '') AS tipus, COUNT(*) AS n FROM socis WHERE estat = 'actiu' GROUP BY tipus ORDER BY n DESC")->fetchAll();
    $total = array_sum(array_column($socis, 'n'));
    $seccions[] = ['titol' => t('mem.socis'), 'cols' => [t('camp.tipus'), t('ent.socis')], 'num' => [1],
        'files' => array_map(fn($r) => [nom_tipus_soci($r['tipus']) ?: '—', (string)$r['n']], $socis),
        'peu' => [t('mem.total'), (string)$total]];
    $seccions[] = ['titol' => t('mem.socis_moviment', $any), 'cols' => [t('mem.concepte'), t('mem.total')], 'num' => [1], 'files' => [
        [t('mem.altes'), (string)valor("SELECT COUNT(*) FROM socis WHERE substr(data_alta, 1, 4) = ?", [(string)$any])],
        [t('mem.baixes'), (string)valor("SELECT COUNT(*) FROM socis WHERE substr(data_baixa, 1, 4) = ?", [(string)$any])],
    ]];
    return [
        'titol' => t('mem.titol') . ($curs ? ' · ' . $curs['nom'] : ''),
        'subtitol' => t('mem.subtitol', data_llegible($ref)),
        'seccions' => $seccions,
        'nota' => t('mem.nota'),
    ];
}

/* ---------- Mostrar, imprimir i exportar ---------- */

function taula_seccio(array $s, string $classe): string
{
    $num = array_flip($s['num'] ?? []);
    $dest = array_flip($s['destacats'] ?? []);
    $html = '<table class="' . $classe . '"><thead><tr>';
    foreach ($s['cols'] as $i => $c) {
        $html .= '<th' . (isset($num[$i]) ? ' class="num"' : '') . '>' . h($c) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($s['files'] as $j => $f) {
        $html .= '<tr' . (isset($dest[$j]) ? ' class="destacat"' : '') . '>';
        foreach ($f as $i => $v) {
            $html .= '<td' . (isset($num[$i]) ? ' class="num"' : '') . '>' . nl2br(h($v)) . '</td>';
        }
        $html .= '</tr>';
    }
    if (!$s['files']) {
        $html .= '<tr><td colspan="' . count($s['cols']) . '">' . h(t('cap_element')) . '</td></tr>';
    }
    $html .= '</tbody>';
    if (!empty($s['peu'])) {
        $html .= '<tfoot><tr>';
        foreach ($s['peu'] as $i => $v) {
            $html .= '<td' . (isset($num[$i]) ? ' class="num"' : '') . '>' . h($v) . '</td>';
        }
        $html .= '</tr></tfoot>';
    }
    return $html . '</table>';
}

function exporta_informe_csv(string $inf, array $d): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $inf . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [$d['titol']], ';', '"', '');
    foreach ($d['seccions'] as $s) {
        if (!empty($s['titol'])) {
            fputcsv($out, [], ';', '"', '');
            fputcsv($out, [$s['titol']], ';', '"', '');
        }
        fputcsv($out, $s['cols'], ';', '"', '');
        foreach ($s['files'] as $f) {
            fputcsv($out, array_map(fn($v) => str_replace("\n", ' / ', $v), $f), ';', '"', '');
        }
        if (!empty($s['peu'])) {
            fputcsv($out, $s['peu'], ';', '"', '');
        }
    }
    audita('exporta', null, null, $inf);
    exit;
}

function imprimeix_informe(array $d): void
{
    document_inici($d['titol'], $d['titol'], t('llist.consell'));
    echo '<section class="full llistat">' . capcalera_societat() . '<h2>' . h($d['titol']) . '</h2>';
    if (!empty($d['subtitol'])) {
        echo '<p class="subtitol">' . h($d['subtitol']) . '</p>';
    }
    foreach ($d['seccions'] as $s) {
        if (!empty($s['titol'])) {
            echo '<h3>' . h($s['titol']) . '</h3>';
        }
        echo taula_seccio($s, 'taula-llistat');
    }
    echo '<p class="peu-llistat">' . h(($d['nota'] ?? '') . ' ' . t('llist.generat', data_llegible(date('Y-m-d H:i:s')))) . '</p></section>';
    document_fi();
}

function pagina_llistats(): void
{
    $inf = get('informe');
    $informes = array_filter(informes(), fn($i, $k) => pot($i[0]) && (!isset(MODUL_INFORMES[$k]) || modul_actiu(MODUL_INFORMES[$k])), ARRAY_FILTER_USE_BOTH);
    if ($inf === '' || !isset($informes[$inf])) {
        $html = '<p>' . h(t('llist.intro')) . '</p><div class="graella-informes">';
        foreach ($informes as $k => [$cap, $icona]) {
            $html .= '<a class="informe" href="' . h(url('llistats', ['informe' => $k])) . '"><span class="ic">' . h($icona) . '</span><b>' . h(t('inf.' . $k))
                . '</b><small>' . h(t('inf.' . $k . '_text')) . '</small></a>';
        }
        $html .= '</div><p class="nota">' . h(t('llist.cert_nota')) . '</p>';
        pagina(t('menu.llistats'), $html);
        return;
    }
    if ($inf === 'etiquetes') {
        pagina_etiquetes();
        return;
    }
    $d = dades_informe($inf);
    if ($d && get('csv') === '1') {
        exporta_informe_csv($inf, $d);
    }
    if ($d && get('imprimir') === '1') {
        audita('llistat', null, null, $inf);
        imprimeix_informe($d);
        return;
    }
    $html = '<p><a href="' . h(url('llistats')) . '">← ' . h(t('menu.llistats')) . '</a></p>'
        . '<form class="formulari" method="get"><input type="hidden" name="r" value="llistats"><input type="hidden" name="informe" value="' . h($inf) . '">'
        . '<div class="camps">' . params_informe($inf) . '</div></form>';
    if (!$d) {
        $html .= '<div class="buit">' . h(t('llist.tria_' . ($inf === 'musics' ? 'agrupacio' : 'grup'))) . '</div>';
    } else {
        $qs = $_GET;
        $html .= '<div class="accions"><a class="boto" target="_blank" href="?' . h(http_build_query(['imprimir' => 1] + $qs)) . '">🖨 ' . h(t('llist.imprimeix')) . '</a>'
            . '<a class="boto secundari" href="?' . h(http_build_query(['csv' => 1] + $qs)) . '">' . h(t('exporta_csv')) . '</a>';
        if ($inf === 'consentiments') {
            $html .= '<a class="boto secundari" target="_blank" href="' . h(url('consentiment', get('grup') !== '' ? ['grup' => get('grup')] : ['pendents' => 1])) . '">🖨 '
                . h(t(get('grup') !== '' ? 'llist.fulls_grup' : 'llist.fulls_pendents')) . '</a>';
        }
        $html .= '</div><h2>' . h($d['titol']) . '</h2>' . (!empty($d['subtitol']) ? '<p class="nota">' . h($d['subtitol']) . '</p>' : '');
        foreach ($d['seccions'] as $s) {
            $html .= (!empty($s['titol']) ? '<h3>' . h($s['titol']) . '</h3>' : '') . '<div class="taula-wrap">' . taula_seccio($s, 'taula compacta taula-informe') . '</div>';
        }
        if (!empty($d['nota'])) {
            $html .= '<p class="nota">' . h($d['nota']) . '</p>';
        }
    }
    pagina(t('inf.' . $inf), $html, t('menu.llistats'));
}

/* ---------- Etiquetes ---------- */

/** Formats d'etiquetes A4: columnes, files, amplada, alçada, marge superior i esquerre (mm) */
const FORMATS_ETIQUETES = [
    '3x8' => [3, 8, 70, 37, 0, 0],
    '3x7' => [3, 7, 63.5, 38.1, 15.15, 7.2],
    '2x7' => [2, 7, 99.1, 38.1, 15.15, 4.65],
];

function destinataris_etiquetes(string $qui): array
{
    $sql = match ($qui) {
        'socis' => "SELECT p.* FROM socis s JOIN persones p ON p.id = s.persona_id WHERE s.estat = 'actiu' AND p.actiu = 1",
        'tutors' => "SELECT DISTINCT p.* FROM tutories tu JOIN alumnes al ON al.id = tu.alumne_id JOIN persones p ON p.id = tu.tutor_id
            WHERE al.estat = 'actiu' AND tu.rep_comunicacions = 1 AND p.actiu = 1",
        'musics' => "SELECT DISTINCT p.* FROM membres m JOIN persones p ON p.id = m.persona_id WHERE m.estat = 'actiu' AND p.actiu = 1",
        default => 'SELECT p.* FROM persones p WHERE p.actiu = 1',
    };
    return q($sql . ' ORDER BY p.cognoms, p.nom')->fetchAll();
}

function pagina_etiquetes(): void
{
    $qui = in_array(get('qui'), ['socis', 'tutors', 'musics', 'totes'], true) ? get('qui') : 'socis';
    $format = isset(FORMATS_ETIQUETES[get('format')]) ? get('format') : '3x8';
    $nomesAdreca = get('enviat') === '' || get('adreca') === '1';
    $salta = max(0, min(30, (int)get('salta')));
    $persones = destinataris_etiquetes($qui);
    if ($nomesAdreca) {
        $persones = array_values(array_filter($persones, fn($p) => trim((string)$p['adreca']) !== ''));
    }

    if (get('imprimir') === '1') {
        audita('etiquetes', null, null, $qui . ' · ' . count($persones));
        [$cols, $files, $w, $hgt, $top, $left] = FORMATS_ETIQUETES[$format];
        $perFull = $cols * $files;
        document_inici(t('inf.etiquetes'), t('etiq.n', count($persones)), t('etiq.consell'));
        echo '<style>@page { size: A4; margin: 0; }</style>';
        $caselles = array_merge(array_fill(0, $salta, null), $persones);
        // L'últim full es completa amb etiquetes buides perquè la graella quede sencera
        $caselles = array_pad($caselles, (int)max(1, ceil(count($caselles) / $perFull)) * $perFull, null);
        foreach (array_chunk($caselles, $perFull) as $full) {
            echo '<div class="full-etiquetes' . (get('vores') === '1' ? ' mostra-vores' : '') . '" style="grid-template-columns: repeat(' . $cols . ', ' . $w . 'mm); grid-auto-rows: ' . $hgt . 'mm; padding: ' . $top . 'mm 0 0 ' . $left . 'mm;">';
            foreach ($full as $p) {
                echo '<div class="etiqueta">';
                if ($p) {
                    $linia3 = trim(trim((string)$p['codi_postal']) . ' ' . trim((string)$p['poblacio']));
                    if ($p['provincia'] && mb_strtolower(trim($p['provincia'])) !== mb_strtolower(trim((string)$p['poblacio']))) {
                        $linia3 .= ($linia3 !== '' ? ' (' . trim($p['provincia']) . ')' : trim($p['provincia']));
                    }
                    echo '<b>' . h(trim($p['nom'] . ' ' . $p['cognoms'])) . '</b>' . ($p['adreca'] ? '<span>' . h($p['adreca']) . '</span>' : '') . ($linia3 !== '' ? '<span>' . h($linia3) . '</span>' : '');
                }
                echo '</div>';
            }
            echo '</div>';
        }
        document_fi();
        return;
    }

    $ops = fn(array $a, string $pref) => array_combine($a, array_map(fn($x) => t($pref . $x), $a));
    $html = '<p><a href="' . h(url('llistats')) . '">← ' . h(t('menu.llistats')) . '</a></p><p>' . h(t('etiq.intro')) . '</p>'
        . '<form class="formulari" method="get" target="_blank"><input type="hidden" name="r" value="llistats"><input type="hidden" name="informe" value="etiquetes">'
        . '<input type="hidden" name="imprimir" value="1"><input type="hidden" name="enviat" value="1"><div class="camps">'
        . camp_param(t('etiq.qui'), select_simple('qui', $ops(['socis', 'tutors', 'musics', 'totes'], 'etiq.qui_'), $qui, false, '', false))
        . camp_param(t('etiq.format'), select_simple('format', $ops(array_keys(FORMATS_ETIQUETES), 'etiq.format_'), $format, false, '', false), t('etiq.format_ajuda'))
        . camp_param(t('etiq.salta'), '<input type="number" name="salta" min="0" max="30" value="' . $salta . '">', t('etiq.salta_ajuda'))
        . '<div class="camp camp-check"><label><input type="checkbox" name="adreca" value="1" checked> ' . h(t('etiq.nomes_adreca')) . '</label>'
        . '<label><input type="checkbox" name="vores" value="1"> ' . h(t('etiq.vores')) . '</label></div>'
        . '</div><div class="accions"><button>🖨 ' . h(t('etiq.prepara')) . '</button></div></form>';
    $resum = [];
    foreach (['socis', 'tutors', 'musics', 'totes'] as $k) {
        $tots = destinataris_etiquetes($k);
        $resum[] = '<li>' . h(t('etiq.qui_' . $k)) . ': ' . h(t('etiq.resum', count($tots), count(array_filter($tots, fn($p) => trim((string)$p['adreca']) === '')))) . '</li>';
    }
    $html .= '<section class="fill"><header><h2>' . h(t('etiq.quantes')) . '</h2></header><ul>' . implode('', $resum) . '</ul></section>';
    pagina(t('inf.etiquetes'), $html, t('menu.llistats'));
}
