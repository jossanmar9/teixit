<?php
// Còpies de seguretat de la base de dades
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// - Automàtica: la primera vegada que s'utilitza Teixit cada dia, i en tancar el programa si hi ha hagut canvis.
// - Manual: botó «Fes una còpia ara» o baixada directa.
// - Cada còpia es guarda a data\copies i, si està configurada, també a una carpeta externa (USB, disc extern, núvol…).

require_once SRC_DIR . '/rotacio.php';

const PATRO_COPIA = '/^teixit-\d{8}-\d{6}-(auto|manual|tancament|abans-restaurar|abans-importar)\.db$/';
const ACCIONS_SENSE_CANVIS = ['entrada', 'eixida', 'entrada_fallida', 'exporta', 'copia_baixada', 'copia_feta'];

function carpeta_copies(): string
{
    $d = DADES_DIR . DIRECTORY_SEPARATOR . 'copies';
    if (!is_dir($d)) {
        mkdir($d, 0770, true);
    }
    return $d;
}

function carpeta_externa(): ?string
{
    $c = config('copia_externa');
    return $c ? rtrim($c, '/\\') : null;
}

/** Mesos que es conserva una còpia automàtica per mes (la resta de la rotació és a rotacio.php) */
function mesos_copies(): int
{
    $m = (int)config('copia_mesos', '12');
    return in_array($m, [1, 3, 6, 12, 24], true) ? $m : 12;
}

/** Hi ha canvis en les dades des de l'última còpia? */
function hi_ha_canvis(): bool
{
    $marques = implode(',', array_fill(0, count(ACCIONS_SENSE_CANVIS), '?'));
    $ultim = (int)valor("SELECT COALESCE(MAX(id), 0) FROM auditoria WHERE accio NOT IN ($marques)", ACCIONS_SENSE_CANVIS);
    return $ultim > (int)config('copia_ultim_canvi', '0');
}

/**
 * Fa una còpia consistent amb VACUUM INTO i la copia a la carpeta externa.
 * Retorna [nom del fitxer, error de la carpeta externa o null].
 */
function fes_copia(string $tipus): array
{
    $nom = 'teixit-' . date('Ymd-His') . '-' . $tipus . '.db';
    $desti = carpeta_copies() . DIRECTORY_SEPARATOR . $nom;
    if (is_file($desti)) {
        unlink($desti);
    }
    q('VACUUM INTO ?', [$desti]);

    $marques = implode(',', array_fill(0, count(ACCIONS_SENSE_CANVIS), '?'));
    desa_config('copia_ultim_canvi', (string)valor("SELECT COALESCE(MAX(id), 0) FROM auditoria WHERE accio NOT IN ($marques)", ACCIONS_SENSE_CANVIS));
    desa_config('copia_ultima', date('Y-m-d H:i:s'));

    $errorExterna = null;
    if ($ext = carpeta_externa()) {
        // Si està configurat, a la carpeta externa (USB, núvol…) la còpia va xifrada amb contrasenya
        $copiat = fn() => xifrat_actiu() ? xifra_fitxer($desti, $ext . DIRECTORY_SEPARATOR . $nom . XIFRAT_EXTENSIO) : @copy($desti, $ext . DIRECTORY_SEPARATOR . $nom);
        if (!is_dir($ext) || !is_writable($ext)) {
            $errorExterna = t('copia.err_externa', $ext);
        } elseif (!$copiat()) {
            $errorExterna = t('copia.err_copiar', $ext);
        } else {
            desa_config('copia_ultima_externa', date('Y-m-d H:i:s'));
            neteja_copies($ext);
        }
        desa_config('copia_error_externa', $errorExterna);
    }
    neteja_copies(carpeta_copies());
    audita('copia_feta', null, null, $nom . ($errorExterna ? ' · ' . $errorExterna : ''));
    return [$nom, $errorExterna];
}

/** Esborra les còpies automàtiques que ja no calen segons la rotació; les manuals es conserven */
function neteja_copies(string $carpeta): void
{
    $auto = array_values(array_filter(glob($carpeta . DIRECTORY_SEPARATOR . 'teixit-*.db*') ?: [], fn($f) => preg_match('/-(auto|tancament)\.db(\.xifrat)?$/', $f)));
    foreach (array_diff($auto, copies_a_mantindre($auto, mesos_copies())) as $vella) {
        @unlink($vella);
    }
}

/** «12,3 MB» */
function mida_llegible(int $bytes): string
{
    return $bytes >= 1024 * 1024 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : number_format($bytes / 1024, 0, ',', '.') . ' KB';
}

/** Mida d'una carpeta de còpies (bytes) */
function mida_copies(string $carpeta): int
{
    return array_sum(array_map('filesize', glob($carpeta . DIRECTORY_SEPARATOR . 'teixit-*.db*') ?: []));
}

/** Còpia automàtica diària (es crida en cada pàgina, després d'entrar) */
function copia_automatica(): void
{
    $avui = date('Y-m-d');
    if (config('copia_auto_dia') === $avui) {
        return;
    }
    desa_config('copia_auto_dia', $avui);
    try {
        fes_copia('auto');
    } catch (Throwable $e) {
        error_log('Còpia automàtica: ' . $e->getMessage());
    }
}

/** Còpia en tancar: la demana el llançador amb un testimoni secret abans d'aturar PHP */
function copia_en_tancar(): void
{
    $token = getenv('TEIXIT_TOKEN');
    header('Content-Type: text/plain; charset=UTF-8');
    if (!$token || !hash_equals($token, (string)get('token'))) {
        http_response_code(403);
        exit('no');
    }
    if ((int)valor('SELECT COUNT(*) FROM usuaris') > 0 && hi_ha_canvis()) {
        [$nom, $err] = fes_copia('tancament');
        exit($err ? 'ok-externa-error' : 'ok');
    }
    exit('sense-canvis');
}

function llista_copies(): array
{
    $files = [];
    foreach (glob(carpeta_copies() . DIRECTORY_SEPARATOR . 'teixit-*.db') ?: [] as $f) {
        $nom = basename($f);
        if (preg_match(PATRO_COPIA, $nom, $m)) {
            $files[] = ['nom' => $nom, 'tipus' => $m[1], 'mida' => filesize($f), 'data' => filemtime($f)];
        }
    }
    usort($files, fn($a, $b) => strcmp($b['nom'], $a['nom']));
    return $files;
}

function ruta_copia(string $nom): ?string
{
    $nom = basename($nom);
    $f = carpeta_copies() . DIRECTORY_SEPARATOR . $nom;
    return preg_match(PATRO_COPIA, $nom) && is_file($f) ? $f : null;
}

function es_bd_teixit(string $fitxer): bool
{
    if (filesize($fitxer) < 100 || file_get_contents($fitxer, false, null, 0, 16) !== "SQLite format 3\0") {
        return false;
    }
    try {
        $p = new PDO('sqlite:' . $fitxer, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ok = (int)$p->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('usuaris','persones','rols')")->fetchColumn() === 3
            && (int)$p->query('SELECT COUNT(*) FROM usuaris')->fetchColumn() > 0;
        $p = null;
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/** Deixa preparada una còpia per a restaurar-la en la pròxima petició */
function prepara_restauracio(string $origen, bool $pujat): void
{
    $conserva = [];
    // El codi de recuperació tampoc no torna arrere: un codi antic ja invalidat no ha de tornar a valdre
    foreach (['copia_externa', 'copia_mesos', 'copia_ultima_externa', 'copia_auto_dia', 'codi_recuperacio', 'codi_recuperacio_data',
        'copia_xifra_clau', 'copia_xifra_sal', 'copia_xifra_data'] as $c) {
        $conserva[$c] = config($c);
    }
    $conserva['copia_error_externa'] = null;
    file_put_contents(DADES_DIR . DIRECTORY_SEPARATOR . 'restaura-config.json', json_encode($conserva));
    $desti = DADES_DIR . DIRECTORY_SEPARATOR . 'restaura.db';
    $pujat ? move_uploaded_file($origen, $desti) : copy($origen, $desti);
}

function envia_fitxer(string $ruta, string $nom): never
{
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

/** Avís per a la pàgina d'inici si les còpies no estan al dia */
function avis_copies(): string
{
    if (!pot('copia.manage')) {
        return '';
    }
    $enllac = ' <a href="' . h(url('copia')) . '">' . h(t('menu.copia')) . '</a>';
    if ($err = config('copia_error_externa')) {
        return '<div class="avis error">⚠ ' . h($err) . $enllac . '</div>';
    }
    if (!carpeta_externa()) {
        return !avis_actiu('copia_externa') ? '' : '<div class="avis info">' . h(t('copia.avis_sense_externa')) . $enllac . '</div>';
    }
    $ultima = config('copia_ultima_externa');
    if (!$ultima || strtotime($ultima) < strtotime('-7 days')) {
        return '<div class="avis info">' . h(t('copia.avis_antiga')) . $enllac . '</div>';
    }
    return '';
}

function pagina_copia(): void
{
    requereix('copia.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accio = post('accio');
        $nom = (string)post('nom');
        switch ($accio) {
            case 'fes':
                [$fet, $err] = fes_copia('manual');
                avis(t('copia.feta', $fet));
                if ($err) {
                    avis($err, 'error');
                } elseif (carpeta_externa()) {
                    avis(t('copia.feta_externa', carpeta_externa()));
                }
                break;
            case 'baixa':
                $tmp = DADES_DIR . DIRECTORY_SEPARATOR . 'baixada-' . bin2hex(random_bytes(4)) . '.db';
                q('VACUUM INTO ?', [$tmp]);
                audita('copia_baixada');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="teixit-' . date('Ymd-His') . '.db"');
                header('Content-Length: ' . filesize($tmp));
                readfile($tmp);
                unlink($tmp);
                exit;
            case 'baixa_llista':
                if ($f = ruta_copia($nom)) {
                    audita('copia_baixada', null, null, $nom);
                    envia_fitxer($f, $nom);
                }
                break;
            case 'restaura_llista':
                requereix('copia.restaura');
                if (($f = ruta_copia($nom)) && es_bd_teixit($f)) {
                    fes_copia('abans-restaurar');
                    prepara_restauracio($f, false);
                    audita('copia_restaurada', null, null, $nom);
                    avis(t('copia.restaurada'));
                } else {
                    avis(t('copia.no_valida'), 'error');
                }
                break;
            case 'elimina':
                requereix('copia.restaura');
                if ($f = ruta_copia($nom)) {
                    unlink($f);
                    audita('copia_eliminada', null, null, $nom);
                    avis(t('eliminat'));
                }
                break;
            case 'restaura_fitxer':
                requereix('copia.restaura');
                $up = $_FILES['fitxer'] ?? null;
                $fitxer = $up && $up['error'] === UPLOAD_ERR_OK ? $up['tmp_name'] : null;
                $desxifrat = null;
                if ($fitxer && es_xifrat($fitxer)) {
                    // Còpia xifrada: es desxifra amb la contrasenya abans de comprovar-la
                    $desxifrat = DADES_DIR . DIRECTORY_SEPARATOR . 'desxifrat-' . bin2hex(random_bytes(4)) . '.db';
                    if (!xifrat_disponible()) {
                        avis(t('copia.xifrat_no_disponible'), 'error');
                        break;
                    }
                    if (!desxifra_fitxer($fitxer, $desxifrat, (string)($_POST['contrasenya'] ?? ''))) {
                        audita('copia_desxifrat_fallit', null, null, $up['name']);
                        avis(t('copia.err_contrasenya'), 'error');
                        break;
                    }
                    $fitxer = $desxifrat;
                }
                if (!$fitxer || !es_bd_teixit($fitxer)) {
                    avis(t('copia.no_valida'), 'error');
                } elseif (post('confirma') !== '1') {
                    avis(t('copia.cal_confirmar'), 'error');
                } else {
                    fes_copia('abans-restaurar');
                    prepara_restauracio($fitxer, !$desxifrat);
                    audita('copia_restaurada', null, null, $up['name']);
                    avis(t('copia.restaurada'));
                }
                if ($desxifrat && is_file($desxifrat)) {
                    unlink($desxifrat);
                }
                break;
            case 'configura':
                requereix('config.manage');
                $ext = trim((string)post('copia_externa'));
                if ($ext !== '' && (!is_dir($ext) || !is_writable($ext))) {
                    avis(t('copia.err_externa', $ext), 'error');
                    break;
                }
                desa_config('copia_externa', $ext !== '' ? $ext : null);
                desa_config('copia_error_externa', null);
                desa_config('copia_mesos', in_array((int)post('copia_mesos'), [1, 3, 6, 12, 24], true) ? (string)(int)post('copia_mesos') : '12');
                neteja_copies(carpeta_copies());
                // Xifrat de les còpies externes
                $c1 = (string)($_POST['xifra_contrasenya'] ?? '');
                if (post('xifra') === '1') {
                    if ($c1 !== '' || !xifrat_actiu()) {
                        if (!xifrat_disponible()) {
                            avis(t('copia.xifrat_no_disponible'), 'error');
                            break;
                        }
                        if ($err = valida_contrasenya($c1, (string)($_POST['xifra_contrasenya2'] ?? ''))) {
                            avis(t('copia.xifrat') . ': ' . $err, 'error');
                            break;
                        }
                        configura_xifrat($c1);
                        audita('copia_xifrat', null, null, 'activat');
                        avis(t('copia.xifrat_activat'));
                    }
                } elseif (xifrat_actiu()) {
                    desa_config('copia_xifra_clau', null);
                    desa_config('copia_xifra_sal', null);
                    desa_config('copia_xifra_data', null);
                    audita('copia_xifrat', null, null, 'desactivat');
                    avis(t('copia.xifrat_desactivat'), 'info');
                }
                audita('modifica', 'config', null, 'copies');
                avis(t('desat'));
                break;
        }
        redirigeix('copia');
    }

    $restaura = pot('copia.restaura');
    $ultima = config('copia_ultima');
    $ext = carpeta_externa();
    $html = '<div class="targetes">'
        . targeta($ultima ? data_llegible($ultima) : '—', t('copia.ultima'))
        . targeta($ext ? (config('copia_ultima_externa') ? data_llegible(config('copia_ultima_externa')) : '—') : t('copia.no_configurada'), t('copia.ultima_externa'))
        . '</div>';
    if ($err = config('copia_error_externa')) {
        $html .= '<div class="avis error">' . h($err) . '</div>';
    }
    $html .= '<p>' . h(t('copia.text')) . '</p><div class="accions">'
        . '<form method="post">' . csrf_camp() . '<input type="hidden" name="accio" value="fes"><button>' . h(t('copia.fes')) . '</button></form>'
        . '<form method="post">' . csrf_camp() . '<input type="hidden" name="accio" value="baixa"><button class="secundari">' . h(t('copia.baixa')) . '</button></form></div>';

    // Llista
    $copies = llista_copies();
    $html .= '<section class="fill"><header><h2>' . h(t('copia.llista')) . ' <span class="compte">' . count($copies) . '</span></h2></header>';
    $mida = filesize(DADES_DIR . DIRECTORY_SEPARATOR . 'teixit.db');
    $docs = (int)valor('SELECT COALESCE(SUM(length(contingut)), 0) FROM document_fitxers');
    $html .= '<p class="nota">' . h(t('copia.llista_text', ROTACIO_DIES, mesos_copies())) . ' <code>' . h(carpeta_copies()) . '</code></p>'
        . '<p class="nota">' . h(t('copia.mides', mida_llegible($mida), mida_llegible($docs), mida_llegible(mida_copies(carpeta_copies())))) . '</p>';
    if ($mida > 200 * 1024 * 1024) {
        $html .= '<div class="avis atencio">⚠ ' . h(t('copia.avis_gran', mida_llegible($mida))) . '</div>';
    }
    if ($copies) {
        $html .= '<div class="taula-wrap"><table class="taula compacta"><thead><tr><th>' . h(t('camp.data')) . '</th><th>' . h(t('camp.tipus')) . '</th><th>' . h(t('copia.mida'))
            . '</th><th></th></tr></thead><tbody>';
        foreach ($copies as $c) {
            $form = fn(string $accio, string $text, string $classe = 'secundari', string $confirma = '') => '<form method="post"' . ($confirma ? ' onsubmit="return confirm(\'' . h($confirma) . '\')"' : '') . '>'
                . csrf_camp() . '<input type="hidden" name="accio" value="' . $accio . '"><input type="hidden" name="nom" value="' . h($c['nom']) . '"><button class="petit ' . $classe . '">' . h($text) . '</button></form>';
            $html .= '<tr><td>' . h(date('d/m/Y H:i', $c['data'])) . '</td><td>' . h(t('copia.tipus.' . $c['tipus'])) . '</td><td>' . h(number_format($c['mida'] / 1024, 0, ',', '.')) . ' KB</td><td><div class="accions-fila">'
                . $form('baixa_llista', t('copia.baixa_curt'));
            if ($restaura) {
                $html .= $form('restaura_llista', t('copia.restaura_curt'), 'secundari', t('copia.confirma_restaurar'))
                    . $form('elimina', t('elimina'), 'perill', t('confirma_eliminar'));
            }
            $html .= '</div></td></tr>';
        }
        $html .= '</tbody></table></div>';
    } else {
        $html .= '<p class="buit petit">' . h(t('cap_element')) . '</p>';
    }
    $html .= '</section>';

    // Configuració
    if (pot('config.manage')) {
        $html .= '<section class="fill"><header><h2>' . h(t('copia.configuracio')) . '</h2></header><p class="nota">' . h(t('copia.externa_text')) . '</p>'
            . '<form method="post" class="formulari">' . csrf_camp() . '<input type="hidden" name="accio" value="configura"><div class="camps">'
            . '<div class="camp ample"><label>' . h(t('copia.externa')) . '</label><input name="copia_externa" value="' . h($ext) . '" placeholder="E:\Copies Teixit"><small>' . h(t('copia.externa_ajuda')) . '</small></div>'
            . '<div class="camp"><label>' . h(t('copia.mesos')) . '</label>' . select_simple('copia_mesos', [1 => t('copia.mesos_1'), 3 => t('copia.mesos_n', 3), 6 => t('copia.mesos_n', 6),
                12 => t('copia.mesos_12'), 24 => t('copia.mesos_24')], mesos_copies(), false, '', false) . '<small>' . h(t('copia.mesos_ajuda', ROTACIO_DIES, ROTACIO_SETMANES)) . '</small></div>'
            . '<fieldset class="camp ample"><legend>🔒 ' . h(t('copia.xifrat')) . '</legend>'
            . (xifrat_disponible()
                ? '<label class="check"><input type="checkbox" name="xifra" value="1"' . (xifrat_actiu() ? ' checked' : '') . '> ' . h(t('copia.xifra')) . '</label>'
                    . '<small>' . h(xifrat_actiu() ? t('copia.xifrat_estat', data_llegible(config('copia_xifra_data'))) : t('copia.xifrat_ajuda')) . '</small>'
                    . '<div class="camps"><div class="camp"><label>' . h(xifrat_actiu() ? t('copia.xifrat_nova') : t('camp.contrasenya')) . '</label><input type="password" name="xifra_contrasenya" minlength="' . MIN_CONTRASENYA . '" autocomplete="new-password"></div>'
                    . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="xifra_contrasenya2" autocomplete="new-password"></div></div>'
                    . '<div class="avis atencio">' . h(t('copia.xifrat_avis')) . '</div>'
                : '<p class="nota">' . h(t('copia.xifrat_no_disponible')) . '</p>')
            . '</fieldset>'
            . '</div><div class="accions"><button>' . h(t('desa')) . '</button></div></form></section>';
    }

    if ($restaura) {
        $html .= '<section class="fill perill-zona"><header><h2>' . h(t('copia.restaurar')) . '</h2></header><p>' . h(t('copia.restaurar_text')) . '</p>'
            . '<form method="post" enctype="multipart/form-data" class="formulari vertical estret">' . csrf_camp() . '<input type="hidden" name="accio" value="restaura_fitxer">'
            . '<input type="file" name="fitxer" accept=".db,.xifrat" required>'
            . '<div class="camp"><label>' . h(t('copia.contrasenya_xifrat')) . '</label><input type="password" name="contrasenya" autocomplete="off"><small>' . h(t('copia.contrasenya_xifrat_ajuda')) . '</small></div>'
            . '<label class="check"><input type="checkbox" name="confirma" value="1" required> ' . h(t('copia.entenc')) . '</label>'
            . '<button class="perill">' . h(t('copia.restaurar')) . '</button></form></section>';
    }
    $html = '<div class="avis atencio">⚠ ' . h(t('avis_resp.curt')) . '</div>' . $html;
    pagina(t('menu.copia'), $html);
}
