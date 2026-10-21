<?php
// Llistats, fitxes i formularis genèrics per a les entitats de entitats.php
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const PER_PAGINA = 50;

function opcions_ref(string $entitat): array
{
    static $cache = [];
    if (!isset($cache[$entitat])) {
        $sql = entitat($entitat)['etiqueta'] ?? null;
        $cache[$entitat] = $sql ? array_column(q($sql)->fetchAll(), 'e', 'id') : [];
    }
    return $cache[$entitat];
}

/** Camps que són columnes de la taula (no els «virtuals», com l'IBAN de la fitxa de persona, que es desa en una altra taula) */
function camps_reals(array $e): array
{
    return array_filter($e['camps'], fn($d) => empty($d['virtual']));
}

/** Valor d'un camp d'una fila, també dels virtuals */
function valor_camp(array $d, array $f, string $c)
{
    return !empty($d['virtual']) ? ($d['carrega'])($f) : ($f[$c] ?? null);
}

function etiqueta_camp(string $camp, array $def = []): string
{
    return t($def['etiqueta'] ?? 'camp.' . $camp);
}

function mostra_valor(array $def, $v, bool $enllac = false): string
{
    if ($v === null || $v === '' || $v === []) {
        return '';
    }
    switch ($def['tipus']) {
        case 'bool':
            return $v ? '✔' : '—';
        case 'date':
            return h(data_llegible($v));
        case 'select':
            return h(t('opt.' . $v));
        case 'dia':
            return h(t('dia.' . $v));
        case 'decimal':
            return h(number_format((float)$v, 2, ',', '.') . (!empty($def['euros']) ? ' €' : (!empty($def['pct']) ? ' %' : '')));
        case 'iban':
            // Als llistats només se'n veuen les primeres i les últimes xifres
            return h($enllac ? iban_format($v) : iban_amagat($v));
        case 'ref':
            $text = opcions_ref($def['ref'])[$v] ?? ('#' . $v);
            $e = entitat($def['ref']);
            if ($enllac && pot($e['cap'] . '.view')) {
                return '<a href="' . h(url('fitxa', ['e' => $def['ref'], 'id' => $v])) . '">' . h($text) . '</a>';
            }
            return h($text);
        case 'textarea':
            return nl2br(h($v));
        case 'refs':
            $noms = array_filter(array_map(fn($x) => opcions_ref($def['ref'])[(int)$x] ?? null, (array)$v));
            return h(implode(', ', $noms));
        case 'email':
            return $enllac ? '<a href="mailto:' . h($v) . '">' . h($v) . '</a>' : h($v);
        default:
            return h($v);
    }
}

/** Construeix el WHERE comú de llistats i exportacions */
function filtre_llista(array $e, array &$params): string
{
    $where = ['1=1'];
    $cerca = get('q');
    if ($cerca !== '' && $e['cerca']) {
        $parts = [];
        foreach ($e['cerca'] as $col) {
            $parts[] = "$col LIKE ?";
            $params[] = '%' . $cerca . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    $f = $_GET['f'] ?? [];
    if (is_array($f)) {
        foreach ($f as $camp => $v) {
            if ((isset($e['camps'][$camp]) || in_array($camp, $e['filtre_extra'] ?? [], true)) && is_string($v) && $v !== '') {
                $where[] = "t.$camp = ?";
                $params[] = $v;
            }
        }
    }
    $nomFiltre = get('filtre');
    if ($nomFiltre !== '' && isset($e['filtres'][$nomFiltre])) {
        $where[] = $e['filtres'][$nomFiltre];
    }
    if (get('inactius') !== '1') {
        if (isset($e['camps']['actiu'])) {
            $where[] = 't.actiu = 1';
        } elseif (isset($e['camps']['estat']) && in_array('actiu', $e['camps']['estat']['opcions'] ?? [], true) && !isset($f['estat'])) {
            $where[] = "t.estat = 'actiu'";
        }
    }
    return implode(' AND ', $where);
}

function pagina_llista(string $nom): void
{
    $e = entitat($nom);
    requereix($e['cap'] . '.view');
    $params = [];
    $where = filtre_llista($e, $params);

    if (get('csv') === '1') {
        exporta_csv($e, $where, $params);
    }

    $total = (int)valor("SELECT COUNT(*) FROM {$e['from']} WHERE $where", $params);
    $pag = max(1, (int)get('p', '1'));
    $offset = ($pag - 1) * PER_PAGINA;
    $files = q("SELECT t.* FROM {$e['from']} WHERE $where ORDER BY {$e['ordre']} LIMIT " . PER_PAGINA . " OFFSET $offset", $params)->fetchAll();

    $fixats = array_keys(array_filter((array)($_GET['f'] ?? []), fn($v) => $v !== ''));
    $cols = array_filter($e['camps'], fn($d, $c) => !empty($d['llista']) && !in_array($c, $fixats, true), ARRAY_FILTER_USE_BOTH);

    ob_start();
    if (isset($e['avis_llista']) && get('filtre') === '') {
        $n = (int)valor("SELECT COUNT(*) FROM {$e['from']} WHERE t.actiu = 1 AND {$e['avis_llista']}");
        if ($n) {
            echo '<div class="avis atencio">⚠ ' . h(t('avis_llista.' . $nom, $n)) . ' <a href="' . h(url('llista', ['e' => $nom, 'filtre' => 'incomplets'])) . '">'
                . h(t('avis_llista.veure')) . '</a></div>';
        }
    }
    echo '<form class="barra" method="get">';
    echo '<input type="hidden" name="r" value="llista"><input type="hidden" name="e" value="' . h($nom) . '">';
    foreach ((array)($_GET['f'] ?? []) as $k => $v) {
        echo '<input type="hidden" name="f[' . h($k) . ']" value="' . h($v) . '">';
    }
    if ($e['cerca']) {
        echo '<input type="search" name="q" value="' . h(get('q')) . '" placeholder="' . h(t('cercar')) . '" autofocus>';
    }
    if (!empty($e['filtres'])) {
        echo '<select name="filtre" onchange="this.form.submit()"><option value="">' . h(t('filtre.tots')) . '</option>';
        foreach ($e['filtres'] as $k => $_) {
            echo '<option value="' . h($k) . '"' . (get('filtre') === $k ? ' selected' : '') . '>' . h(t('filtre.' . $k)) . '</option>';
        }
        echo '</select>';
    }
    if (isset($e['camps']['actiu']) || isset($e['camps']['estat'])) {
        echo '<label class="check"><input type="checkbox" name="inactius" value="1"' . (get('inactius') === '1' ? ' checked' : '')
            . ' onchange="this.form.submit()"> ' . h(t('mostra_baixes')) . '</label>';
    }
    echo '<button>' . h(t('filtrar')) . '</button>';
    echo '<span class="espai"></span>';
    if (isset($e['accions_llista'])) {
        echo $e['accions_llista']();
    }
    $qs = $_GET;
    $qs['csv'] = '1';
    echo '<a class="boto secundari" href="?' . h(http_build_query($qs)) . '">' . h(t('exporta_csv')) . '</a>';
    if (pot($e['cap'] . '.edit') && empty($e['nomes_lectura'])) {
        $nou = ['e' => $nom];
        foreach ((array)($_GET['f'] ?? []) as $k => $v) {
            $nou["f[$k]"] = $v;
        }
        echo '<a class="boto" href="' . h(url('edita', $nou)) . '">+ ' . h(t('nou')) . '</a>';
    }
    echo '</form>';

    if (!$files) {
        echo '<div class="buit">' . h(t('cap_resultat')) . '</div>';
    } else {
        echo taula_files($e, $files, $cols);
        $pagines = (int)ceil($total / PER_PAGINA);
        if ($pagines > 1) {
            echo '<nav class="pagines">';
            for ($i = 1; $i <= $pagines; $i++) {
                $qs = $_GET;
                $qs['p'] = $i;
                echo $i === $pag ? '<strong>' . $i . '</strong>' : '<a href="?' . h(http_build_query($qs)) . '">' . $i . '</a>';
            }
            echo '</nav>';
        }
    }
    $suma = '';
    if (isset($e['suma']) && $total) {
        $suma = ' · ' . t('total') . ': ' . number_format((float)valor("SELECT SUM(t.{$e['suma']}) FROM {$e['from']} WHERE $where", $params), 2, ',', '.') . ' €';
    }
    echo '<p class="peu-taula">' . h(t('n_resultats', $total) . $suma) . '</p>';
    pagina(t('ent.' . $nom), ob_get_clean());
}

function taula_files(array $e, array $files, array $cols): string
{
    $html = '<div class="taula-wrap"><table class="taula"><thead><tr>';
    foreach ($cols as $c => $d) {
        $html .= '<th>' . h(etiqueta_camp($c, $d)) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($files as $f) {
        $href = h(url('fitxa', ['e' => $e['nom'], 'id' => $f['id']]));
        $avis = isset($e['avis_fila']) ? $e['avis_fila']($f) : null;
        $html .= '<tr data-href="' . $href . '"' . ($avis ? ' class="fila-avis" title="' . h($avis) . '"' : '') . '>';
        $primer = true;
        foreach ($cols as $c => $d) {
            $val = mostra_valor($d, $f[$c]);
            if ($primer) {
                $val = ($avis ? '<span class="icona-avis" aria-label="' . h($avis) . '">⚠</span> ' : '')
                    . '<a href="' . $href . '">' . ($val !== '' ? $val : '#' . (int)$f['id']) . '</a>';
                $primer = false;
            }
            $html .= '<td>' . $val . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function exporta_csv(array $e, string $where, array $params): never
{
    $files = q("SELECT t.* FROM {$e['from']} WHERE $where ORDER BY {$e['ordre']}", $params)->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $e['nom'] . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_merge(['id'], array_map(fn($c) => etiqueta_camp($c, $e['camps'][$c]), array_keys(camps_reals($e)))), ';', '"', '');
    foreach ($files as $f) {
        $fila = [$f['id']];
        foreach (camps_reals($e) as $c => $d) {
            $v = $f[$c];
            if ($d['tipus'] === 'ref' && $v !== null) {
                $v = opcions_ref($d['ref'])[$v] ?? $v;
            } elseif ($d['tipus'] === 'select' && $v !== null) {
                $v = t('opt.' . $v);
            } elseif ($d['tipus'] === 'dia' && $v !== null) {
                $v = t('dia.' . $v);
            }
            $fila[] = $v;
        }
        fputcsv($out, $fila, ';', '"', '');
    }
    audita('exporta', $e['nom'], null, (string)count($files));
    exit;
}

function pagina_fitxa(string $nom, int $id): void
{
    $e = entitat($nom);
    requereix($e['cap'] . '.view');
    $f = fila("SELECT * FROM {$e['taula']} WHERE id = ?", [$id]);
    if (!$f) {
        avis(t('err.no_trobat'), 'error');
        redirigeix('llista', ['e' => $nom]);
    }
    $titol = titol_element($e, $f);
    ob_start();
    if (isset($e['avis_fila']) && ($avis = $e['avis_fila']($f))) {
        echo '<div class="avis atencio">⚠ ' . h($avis) . (pot($e['cap'] . '.edit') ? ' <a href="' . h(url('edita', ['e' => $nom, 'id' => $id])) . '">' . h(t('edita')) . '</a>' : '') . '</div>';
    }
    echo '<div class="accions">';
    if (pot($e['cap'] . '.edit') && empty($e['nomes_lectura'])) {
        echo '<a class="boto" href="' . h(url('edita', ['e' => $nom, 'id' => $id])) . '">' . h(t('edita')) . '</a>';
        echo '<form method="post" action="' . h(url('elimina', ['e' => $nom, 'id' => $id])) . '" onsubmit="return confirm(\'' . h(t('confirma_eliminar')) . '\')">'
            . csrf_camp() . '<button class="perill">' . h(t('elimina')) . '</button></form>';
    }
    echo '<a class="boto secundari" href="' . h(url('llista', ['e' => $nom])) . '">' . h(t('torna_llista')) . '</a>';
    // Botons propis de l'entitat (certificats, fulls per a imprimir, assistents…)
    if (isset($e['accions_fitxa'])) {
        echo '<span class="espai"></span>' . $e['accions_fitxa']($f);
    }
    echo '</div>';

    echo '<dl class="fitxa">';
    foreach ($e['camps'] as $c => $d) {
        $v = mostra_valor($d, valor_camp($d, $f, $c), true);
        if ($v === '' && $d['tipus'] !== 'bool') {
            continue;
        }
        echo '<dt>' . h(etiqueta_camp($c, $d)) . '</dt><dd>' . $v . '</dd>';
    }
    echo '</dl>';
    // Seccions pròpies de l'entitat (per exemple, les existències de cada talla d'un uniforme)
    if (isset($e['seccio_fitxa'])) {
        echo $e['seccio_fitxa']($f);
    }

    if ($nom === 'grups') {
        echo '<p class="accions">';
        if (pot('escola.notes') && disponible('notes')) {
            echo '<a class="boto secundari" href="' . h(url('notes', ['grup' => $id])) . '">' . h(t('menu.notes')) . '</a>';
        }
        echo '</p>';
    }

    if ($nom === 'alumnes' && ($curs = curs_actual())) {
        echo '<section class="fill"><header><h2>' . h(t('resum.titol', $curs['nom'])) . '</h2>' . (disponible('butlleti') ? '<a class="boto petit secundari" target="_blank" href="'
            . h(url('butlleti', ['imprimir' => 1, 'curs' => $curs['id'], 'ambit' => 'alumne', 'alumne' => $id, 'periode' => valor('SELECT id FROM periodes WHERE curs_id = ? ORDER BY ordre DESC LIMIT 1', [$curs['id']])]))
            . '">🖨 ' . h(t('butlleti.imprimeix')) . '</a>' : '') . '</header>' . resum_alumne($id, $curs) . '</section>';
    }

    $tornar = url('fitxa', ['e' => $nom, 'id' => $id]);
    foreach ($e['fills'] as [$fill, $fk]) {
        $ef = entitat($fill);
        if (!pot($ef['cap'] . '.view') || !disponible($fill)) {
            continue;
        }
        $files = q("SELECT t.* FROM {$ef['from']} WHERE t.$fk = ? ORDER BY {$ef['ordre']}", [$id])->fetchAll();
        $cols = array_filter($ef['camps'], fn($d, $c) => !empty($d['llista']) && $c !== $fk, ARRAY_FILTER_USE_BOTH);
        echo '<section class="fill"><header><h2>' . h(t('ent.' . $fill)) . ' <span class="compte">' . count($files) . '</span></h2>';
        if (pot($ef['cap'] . '.edit') && empty($ef['nomes_lectura']) && empty($e['nomes_lectura'])) {
            echo '<a class="boto petit" href="' . h(url('edita', ['e' => $fill, "f[$fk]" => $id, 'tornar' => $tornar])) . '">+ ' . h(t('afegeix')) . '</a>';
        }
        echo '</header>';
        echo $files ? taula_files($ef, $files, $cols) : '<p class="buit petit">' . h(t('cap_element')) . '</p>';
        echo '</section>';
    }
    pagina($titol, ob_get_clean(), t('ent1.' . $nom));
}

function titol_element(array $e, array $f): string
{
    if (isset($e['etiqueta'])) {
        $o = opcions_ref($e['nom']);
        if (isset($o[$f['id']])) {
            return $o[$f['id']];
        }
    }
    foreach ($e['camps'] as $c => $d) {
        if (!empty($d['llista'])) {
            return strip_tags(html_entity_decode(mostra_valor($d, valor_camp($d, $f, $c)))) ?: t('ent1.' . $e['nom']);
        }
    }
    return t('ent1.' . $e['nom']);
}

function camp_formulari(string $c, array $d, $v): string
{
    $nom = h($c);
    $req = !empty($d['req']) ? ' required' : '';
    // «major»: obligatori només per a majors d'edat (ho activa app.js segons la data de naixement)
    $major = !empty($d['major']) ? ' data-major="1"' : '';
    $etq = '<label for="c_' . $nom . '">' . h(etiqueta_camp($c, $d)) . (!empty($d['req']) ? ' <span class="obl">*</span>' : '')
        . ($major ? ' <span class="obl obl-major" hidden>*</span>' : '') . '</label>';
    $ajuda = isset($d['ajuda']) ? '<small>' . h($d['ajuda']) . '</small>' : '';
    switch ($d['tipus']) {
        case 'textarea':
            $in = '<textarea id="c_' . $nom . '" name="' . $nom . '" rows="3"' . $req . '>' . h($v) . '</textarea>';
            break;
        case 'refs':
            // Caselles: el camp ocult fa que s'envie encara que no se'n marque cap
            $marcats = array_map('intval', (array)$v);
            $in = '<input type="hidden" name="' . $nom . '[]" value=""><div class="opcions">';
            foreach (opcions_ref($d['ref']) as $k => $text) {
                $in .= '<label><input type="checkbox" name="' . $nom . '[]" value="' . h($k) . '"' . (in_array((int)$k, $marcats, true) ? ' checked' : '') . '> ' . h($text) . '</label>';
            }
            $in .= '</div>';
            return '<fieldset class="camp ample"><legend>' . h(etiqueta_camp($c, $d)) . '</legend>' . $in . $ajuda . '</fieldset>';
        case 'bool':
            return '<div class="camp camp-check"><label><input type="hidden" name="' . $nom . '" value="0"><input type="checkbox" id="c_' . $nom . '" name="' . $nom . '" value="1"'
                . ($v ? ' checked' : '') . '> ' . h(etiqueta_camp($c, $d)) . '</label>' . $ajuda . '</div>';
        case 'select':
        case 'dia':
        case 'ref':
            if ($d['tipus'] === 'select') {
                $ops = array_combine($d['opcions'], array_map(fn($o) => t('opt.' . $o), $d['opcions']));
            } elseif ($d['tipus'] === 'dia') {
                $ops = [];
                for ($i = 1; $i <= 7; $i++) {
                    $ops[$i] = t('dia.' . $i);
                }
            } else {
                $ops = opcions_ref($d['ref']);
            }
            $gran = count($ops) > 12 ? ' data-cerca="1"' : '';
            $buit = $d['tipus'] !== 'select' || !empty($d['buit']);
            $in = '<select id="c_' . $nom . '" name="' . $nom . '"' . $req . $gran . '>' . ($buit ? '<option value="">' . h($d['buit_text'] ?? '—') . '</option>' : '');
            foreach ($ops as $k => $text) {
                $in .= '<option value="' . h($k) . '"' . ((string)$k === (string)$v ? ' selected' : '') . '>' . h($text) . '</option>';
            }
            $in .= '</select>';
            break;
        case 'fitxer':
            $in = '<input type="file" id="c_' . $nom . '" name="' . $nom . '"' . (isset($d['accepta']) ? ' accept="' . h($d['accepta']) . '"' : '') . '>'
                . ($v ? '<small>' . h(t('fitxer.actual', (string)$v)) . '</small>' : '');
            break;
        default:
            $tipus = ['date' => 'date', 'time' => 'time', 'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'decimal' => 'text'][$d['tipus']] ?? 'text';
            $extra = $d['tipus'] === 'decimal' ? ' inputmode="decimal"' : '';
            if ($d['tipus'] === 'talla') {
                // Les talles de cada article: app.js mostra només les de l'article triat
                $talles = $preus = [];
                foreach (q('SELECT id, talles, preu FROM uniformes')->fetchAll() as $u) {
                    $talles[$u['id']] = talles_text($u['talles']);
                    $preus[$u['id']] = $u['preu'] !== null ? (float)$u['preu'] : null;
                }
                $extra = ' list="l_' . $nom . '" autocomplete="off" data-talles="' . h(json_encode($talles, JSON_UNESCAPED_UNICODE)) . '" data-preus="' . h(json_encode($preus)) . '"';
                $tots = array_values(array_unique(array_merge([], ...array_values($talles))));
                $llista = '<datalist id="l_' . $nom . '">' . implode('', array_map(fn($x) => '<option value="' . h($x) . '">', $tots)) . '</datalist>';
            }
            $in = '<input type="' . $tipus . '" id="c_' . $nom . '" name="' . $nom . '" value="' . h($v) . '"' . $req . $extra . $major . '>' . ($llista ?? '');
    }
    // «nomes_major»: el camp només apareix si la persona és major d'edat (ho fa app.js)
    return '<div class="camp' . ($d['tipus'] === 'textarea' ? ' ample' : '') . '"' . (!empty($d['nomes_major']) ? ' data-nomes-major="1"' : '') . '>' . $etq . $in . $ajuda . '</div>';
}

function normalitza(array $d, $v)
{
    // Diverses opcions marcades (per exemple, les agrupacions d'una obra)
    if ($d['tipus'] === 'refs') {
        return array_values(array_unique(array_filter(array_map('intval', (array)$v), fn($x) => $x > 0)));
    }
    if (is_array($v)) {
        return null;
    }
    $v = trim((string)$v);
    switch ($d['tipus']) {
        case 'bool':
            return $v === '1' ? 1 : 0;
        case 'number':
        case 'ref':
        case 'dia':
            return $v === '' ? null : (int)$v;
        case 'talla':
            return $v === '' ? null : mb_strtoupper(preg_replace('/\s+/', ' ', $v));
        case 'decimal':
            if ($v === '') {
                return null;
            }
            if (str_contains($v, ',')) {
                $v = str_replace(['.', ','], ['', '.'], $v);
            }
            return is_numeric($v) ? (float)$v : null;
        case 'date':
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        case 'time':
            return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : null;
        case 'select':
            return in_array($v, $d['opcions'], true) ? $v : null;
        case 'iban':
            return $v === '' ? null : iban_entrada($v);
        case 'fitxer':
            return null; // el fitxer el desa el camp virtual a partir de $_FILES
        default:
            return $v === '' ? null : $v;
    }
}

function pagina_edita(string $nom, ?int $id): void
{
    $e = entitat($nom);
    requereix($e['cap'] . '.edit');
    if (!empty($e['nomes_lectura'])) {
        redirigeix($id ? 'fitxa' : 'llista', array_filter(['e' => $nom, 'id' => $id]));
    }
    $f = $id ? fila("SELECT * FROM {$e['taula']} WHERE id = ?", [$id]) : null;
    if ($id && !$f) {
        redirigeix('llista', ['e' => $nom]);
    }
    $tornar = get('tornar');
    if ($tornar !== '' && !str_starts_with($tornar, '?r=')) {
        $tornar = '';
    }
    $errors = [];
    $dades = [];
    $creat = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($e['camps'] as $c => $d) {
            $dades[$c] = normalitza($d, $_POST[$c] ?? '');
            if (!empty($d['req']) && ($dades[$c] === null || $dades[$c] === '')) {
                $errors[] = t('err.obligatori', etiqueta_camp($c, $d));
            }
        }
        if (!$errors && isset($e['valida']) && ($msg = $e['valida']($dades, $id))) {
            $errors[] = $msg;
        }
        if (!$errors) {
            // Un camp virtual que no s'ha enviat (amagat, per exemple l'IBAN d'un menor) no es toca
            $virtuals = array_filter(array_diff_key($dades, camps_reals($e)), fn($c) => array_key_exists($c, $_POST) || array_key_exists($c, $_FILES), ARRAY_FILTER_USE_KEY);
            $completes = $dades;
            $dades = array_intersect_key($dades, camps_reals($e));
            try {
                db()->beginTransaction();
                if ($id) {
                    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($dades)));
                    if ($nom === 'persones') {
                        $sets .= ", modificat = datetime('now','localtime')";
                    }
                    q("UPDATE {$e['taula']} SET $sets WHERE id = ?", array_merge(array_values($dades), [$id]));
                    audita('modifica', $nom, $id);
                } else {
                    $cols = implode(', ', array_keys($dades));
                    $marques = implode(', ', array_fill(0, count($dades), '?'));
                    q("INSERT INTO {$e['taula']} ($cols) VALUES ($marques)", array_values($dades));
                    $id = (int)db()->lastInsertId();
                    $creat = true;
                    audita('crea', $nom, $id);
                }
                if (isset($e['despres'])) {
                    $e['despres']($id, $dades);
                }
                foreach ($virtuals as $c => $v) {
                    ($e['camps'][$c]['desa'])($id, $v, $completes);
                }
                db()->commit();
                avis(t('desat'));
                // En crear un element amb subapartats (alumne, matrícula, grup…) s'obri la seua fitxa per a continuar
                if ($tornar && !($creat && $e['fills'])) {
                    header('Location: ' . $tornar);
                    exit;
                }
                redirigeix('fitxa', ['e' => $nom, 'id' => $id]);
            } catch (PDOException $ex) {
                db()->rollBack();
                $dades = $completes;
                $errors[] = error_bd($ex);
                if (!$f) {
                    $id = null;
                }
            }
        }
    } else {
        $prefix = (array)($_GET['f'] ?? []);
        foreach ($e['camps'] as $c => $d) {
            if ($f) {
                $dades[$c] = valor_camp($d, $f, $c);
            } elseif (isset($prefix[$c])) {
                $dades[$c] = $prefix[$c];
            } else {
                $def = $d['defecte'] ?? ($d['tipus'] === 'select' && empty($d['buit']) ? $d['opcions'][0] : null);
                $dades[$c] = $def instanceof Closure ? $def() : match ($def) {
                    'avui' => date('Y-m-d'),
                    'curs' => curs_actual()['id'] ?? null,
                    default => $def,
                };
            }
        }
    }

    ob_start();
    foreach ($errors as $err) {
        echo '<div class="avis error">' . h($err) . '</div>';
    }
    $accio = url('edita', array_filter(['e' => $nom, 'id' => $id, 'tornar' => $tornar]));
    $multipart = array_filter($e['camps'], fn($d) => $d['tipus'] === 'fitxer') ? ' enctype="multipart/form-data"' : '';
    echo '<form method="post" class="formulari" action="' . h($accio) . '"' . $multipart . '>' . csrf_camp() . '<div class="camps">';
    foreach ($e['camps'] as $c => $d) {
        echo camp_formulari($c, $d, $dades[$c] ?? null);
    }
    echo '</div><div class="accions"><button>' . h(t('desa')) . '</button>';
    $cancel = $tornar ?: ($id ? url('fitxa', ['e' => $nom, 'id' => $id]) : url('llista', ['e' => $nom]));
    echo '<a class="boto secundari" href="' . h($cancel) . '">' . h(t('cancel·la')) . '</a></div></form>';
    $titol = $f ? t('edita_x', titol_element($e, $f)) : t('nou_x', t('ent1.' . $nom));
    pagina($titol, ob_get_clean());
}

function error_bd(PDOException $ex): string
{
    $m = $ex->getMessage();
    if (str_contains($m, 'UNIQUE')) {
        return t('err.duplicat');
    }
    if (str_contains($m, 'FOREIGN KEY')) {
        return t('err.relacio');
    }
    return t('err.bd') . ' ' . $m;
}

function accio_elimina(string $nom, int $id): void
{
    $e = entitat($nom);
    requereix($e['cap'] . '.edit');
    if (!empty($e['nomes_lectura'])) {
        redirigeix('fitxa', ['e' => $nom, 'id' => $id]);
    }
    if (isset($e['elimina_permes']) && ($msg = $e['elimina_permes']($id))) {
        avis($msg, 'error');
        redirigeix('fitxa', ['e' => $nom, 'id' => $id]);
    }
    try {
        $abans = fila("SELECT * FROM {$e['taula']} WHERE id = ?", [$id]);
        q("DELETE FROM {$e['taula']} WHERE id = ?", [$id]);
        if ($abans && isset($e['despres_elimina'])) {
            $e['despres_elimina']($abans);
        }
        audita('elimina', $nom, $id);
        avis(t('eliminat'));
        if ($abans && isset($e['pare'])) {
            redirigeix('fitxa', ['e' => $e['pare'][0], 'id' => $abans[$e['pare'][1]]]);
        }
        redirigeix('llista', ['e' => $nom]);
    } catch (PDOException $ex) {
        avis(str_contains($ex->getMessage(), 'FOREIGN KEY') ? t('err.no_eliminable') : error_bd($ex), 'error');
        redirigeix('fitxa', ['e' => $nom, 'id' => $id]);
    }
}
