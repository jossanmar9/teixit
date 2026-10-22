<?php
// Administració: usuaris, configuració, registre d'activitat i ajuda
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Comprova que almenys un usuari actiu continua podent gestionar usuaris */
function queda_administrador(): bool
{
    return (bool)valor("SELECT COUNT(*) FROM usuaris u JOIN usuari_rols ur ON ur.usuari_id = u.id JOIN rol_capacitats rc ON rc.rol_id = ur.rol_id
        WHERE u.actiu = 1 AND rc.capacitat = 'usuaris.manage'");
}

/** Rol d'un usuari ('admin' o 'administratiu') */
function rol_usuari(int $id): ?string
{
    return valor('SELECT r.clau FROM usuari_rols ur JOIN rols r ON r.id = ur.rol_id WHERE ur.usuari_id = ? ORDER BY r.id LIMIT 1', [$id]);
}

function assigna_rol(int $usuariId, string $rol): void
{
    q('DELETE FROM usuari_rols WHERE usuari_id = ?', [$usuariId]);
    q('INSERT INTO usuari_rols(usuari_id, rol_id) SELECT ?, id FROM rols WHERE clau = ?', [$usuariId, in_array($rol, ROLS, true) ? $rol : 'administratiu']);
}

function pagina_usuaris(): void
{
    requereix('usuaris.manage');
    $id = get('id');
    if ($id !== '') {
        formulari_usuari($id === 'nou' ? null : (int)$id);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accio') === 'genera_codi') {
        $_SESSION['codi_nou'] = genera_codi_recuperacio();
        audita('codi_recuperacio');
        redirigeix('codi');
    }
    $files = q('SELECT * FROM usuaris ORDER BY usuari')->fetchAll();
    $html = '<div class="barra"><span class="espai"></span><a class="boto" href="' . h(url('usuaris', ['id' => 'nou'])) . '">+ ' . h(t('nou')) . '</a></div>';
    $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('camp.usuari')) . '</th><th>' . h(t('camp.rol'))
        . '</th><th>' . h(t('camp.actiu')) . '</th><th>' . h(t('camp.ultim_acces')) . '</th></tr></thead><tbody>';
    foreach ($files as $f) {
        $html .= '<tr data-href="' . h(url('usuaris', ['id' => $f['id']])) . '"><td><a href="' . h(url('usuaris', ['id' => $f['id']])) . '">' . h($f['usuari']) . '</a></td><td>'
            . h(t('rol.' . (rol_usuari((int)$f['id']) ?? 'administratiu'))) . '</td><td>' . ($f['actiu'] ? '✔' : '—') . '</td><td>' . h(data_llegible($f['ultim_acces'])) . '</td></tr>';
    }
    $html .= '</tbody></table></div><p class="nota">' . h(t('usuaris.nota')) . '</p>' . seccio_codi_recuperacio();
    pagina(t('menu.usuaris'), $html);
}

function formulari_usuari(?int $id): void
{
    $u = $id ? fila('SELECT * FROM usuaris WHERE id = ?', [$id]) : null;
    if ($id && !$u) {
        redirigeix('usuaris');
    }
    $rol = $id ? (rol_usuari($id) ?? 'administratiu') : 'administratiu';
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (post('accio') === 'elimina' && $id) {
            if ($id === (int)usuari()['id']) {
                $error = t('err.eliminar_propi');
            } else {
                db()->beginTransaction();
                q('DELETE FROM usuaris WHERE id = ?', [$id]);
                if (!queda_administrador()) {
                    db()->rollBack();
                    $error = t('err.ultim_admin');
                } else {
                    db()->commit();
                    audita('elimina', 'usuaris', $id, $u['usuari']);
                    avis(t('eliminat'));
                    redirigeix('usuaris');
                }
            }
        } else {
            $nom = post('usuari');
            $actiu = post('actiu') === '1' ? 1 : 0;
            $l = post('idioma');
            $l = isset(IDIOMES[$l]) ? $l : null;
            $rol = in_array(post('rol'), ROLS, true) ? post('rol') : 'administratiu';
            $c1 = (string)post('contrasenya');
            if (!preg_match('/^[\p{L}\p{N}._-]{3,40}$/u', $nom)) {
                $error = t('err.nom_usuari');
            } elseif (!$id || $c1 !== '') {
                $error = valida_contrasenya($c1, (string)post('contrasenya2'));
            }
            if (!$error) {
                try {
                    db()->beginTransaction();
                    if ($id) {
                        q('UPDATE usuaris SET usuari = ?, actiu = ?, idioma = ? WHERE id = ?', [$nom, $actiu, $l, $id]);
                        if ($c1 !== '') {
                            q('UPDATE usuaris SET contrasenya = ? WHERE id = ?', [password_hash($c1, PASSWORD_DEFAULT), $id]);
                        }
                    } else {
                        q('INSERT INTO usuaris(usuari, contrasenya, actiu, idioma) VALUES(?,?,?,?)', [$nom, password_hash($c1, PASSWORD_DEFAULT), $actiu, $l]);
                        $id = (int)db()->lastInsertId();
                    }
                    assigna_rol($id, $rol);
                    if (!queda_administrador()) {
                        throw new RuntimeException(t('err.ultim_admin'));
                    }
                    db()->commit();
                    audita($u ? 'modifica' : 'crea', 'usuaris', $id, $nom . ($c1 !== '' && $u ? ' (contrasenya)' : ''));
                    avis(t('desat'));
                    redirigeix('usuaris');
                } catch (PDOException $ex) {
                    db()->rollBack();
                    $error = error_bd($ex);
                } catch (RuntimeException $ex) {
                    db()->rollBack();
                    $error = $ex->getMessage();
                }
                if (!$u) {
                    $id = null;
                }
            }
            $u = ['usuari' => $nom, 'actiu' => $actiu, 'idioma' => $l];
        }
    }
    $u = $u ?? ['usuari' => '', 'actiu' => 1, 'idioma' => null];
    $html = $error ? '<div class="avis error">' . h($error) . '</div>' : '';
    $html .= '<form method="post" class="formulari">' . csrf_camp() . '<div class="camps">'
        . '<div class="camp"><label>' . h(t('camp.usuari')) . ' <span class="obl">*</span></label><input name="usuari" required value="' . h($u['usuari']) . '" autocomplete="off"></div>'
        . '<div class="camp"><label>' . h(t('camp.rol')) . '</label>' . select_simple('rol', array_combine(ROLS, array_map(fn($r) => t('rol.' . $r), ROLS)), $rol, false, '', false)
        . '<small>' . h(t('usuaris.rol_ajuda')) . '</small></div>'
        . '<div class="camp"><label>' . h(t('camp.idioma')) . '</label>' . select_simple('idioma', IDIOMES, $u['idioma'], true, t('usuaris.idioma_defecte'), false) . '</div>'
        . '<div class="camp camp-check"><label><input type="checkbox" name="actiu" value="1"' . ($u['actiu'] ? ' checked' : '') . '> ' . h(t('camp.actiu')) . '</label></div>'
        . '<div class="camp"><label>' . h($id ? t('camp.contrasenya_nova') : t('camp.contrasenya')) . ($id ? '' : ' <span class="obl">*</span>') . '</label><input type="password" name="contrasenya" autocomplete="new-password"'
        . ($id ? '' : ' required') . ' minlength="' . MIN_CONTRASENYA . '">' . ($id ? '<small>' . h(t('usuaris.contrasenya_buida')) . '</small>' : '') . '</div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="contrasenya2" autocomplete="new-password"></div>'
        . '</div><div class="accions"><button>' . h(t('desa')) . '</button><a class="boto secundari" href="' . h(url('usuaris')) . '">' . h(t('cancel·la')) . '</a>';
    if ($id) {
        $html .= '<span class="espai"></span><button class="perill" name="accio" value="elimina" formnovalidate onclick="return confirm(\'' . h(t('confirma_eliminar')) . '\')">' . h(t('elimina')) . '</button>';
    }
    $html .= '</div></form>';
    pagina($id ? t('edita_x', $u['usuari']) : t('nou_x', t('usuaris.usuari')), $html);
}

function pagina_configuracio(): void
{
    requereix('config.manage');
    $camps = ['societat_nom', 'societat_cif', 'societat_adreca', 'societat_poblacio', 'societat_telefon', 'societat_email', 'societat_web'];
    $campsDocs = ['certificat_signant', 'certificat_carrec', 'certificat_president'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_merge($camps, $campsDocs) as $c) {
            desa_config($c, mb_substr((string)post($c), 0, 200) ?: null);
        }
        foreach (['consentiment_extra', 'full_alta_intro', 'full_alta_peu'] as $c) {
            desa_config($c, mb_substr(trim((string)post($c)), 0, 3000) ?: null);
        }
        $l = post('idioma');
        desa_config('idioma', isset(IDIOMES[$l]) ? $l : 'ca');
        if (in_array(post('tipus_entitat'), TIPUS_ENTITAT, true) && post('tipus_entitat') !== tipus_entitat()) {
            audita('tipus_entitat', 'config', null, tipus_entitat() . ' → ' . post('tipus_entitat'));
            desa_tipus_entitat((string)post('tipus_entitat'), false);
        }
        if (post('esborra_logo') === '1') {
            esborra_logo();
        } elseif (!empty($_FILES['logo']['name'])) {
            if ($err = desa_logo($_FILES['logo'])) {
                avis($err, 'error');
            }
        }
        audita('modifica', 'config');
        avis(t('desat'));
        redirigeix('configuracio');
    }
    $html = '<form method="post" class="formulari" enctype="multipart/form-data">' . csrf_camp() . '<div class="camps">';
    foreach ($camps as $c) {
        $html .= '<div class="camp"><label>' . h(t('camp.' . $c)) . '</label><input name="' . $c . '" value="' . h(config($c)) . '"' . ($c === 'societat_nom' ? ' required' : '') . '></div>';
    }
    $html .= '<div class="camp"><label>' . h(t('camp.tipus_entitat')) . '</label>'
        . select_simple('tipus_entitat', array_combine(TIPUS_ENTITAT, array_map(fn($tp) => t('tipus_entitat.' . $tp), TIPUS_ENTITAT)), tipus_entitat(), false, '', false)
        . '<small>' . h(t('ajuda.tipus_entitat')) . '</small></div>';
    $html .= '<div class="camp"><label>' . h(t('config.idioma_defecte')) . '</label>' . select_simple('idioma', IDIOMES, config('idioma', 'ca'), false, '', false) . '</div>';
    $logo = url_logo();
    $html .= '<div class="camp ample"><label>' . h(t('logo.titol')) . '</label>'
        . ($logo ? '<img class="logo-previ" src="' . h($logo) . '" alt=""><label class="check"><input type="checkbox" name="esborra_logo" value="1"> ' . h(t('logo.esborra')) . '</label>' : '')
        . '<input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>' . h(t('logo.ajuda')) . '</small></div>';
    // Dades dels certificats i del full de consentiment
    $html .= '<fieldset class="camp ample"><legend>' . h(t('config.documents')) . '</legend><small>' . h(t('config.documents_ajuda')) . '</small><div class="camps">';
    foreach ($campsDocs as $c) {
        $html .= '<div class="camp"><label>' . h(t('camp.' . $c)) . '</label><input name="' . $c . '" value="' . h(config($c)) . '"'
            . ($c === 'certificat_carrec' ? ' placeholder="' . h(t('cert.carrec_defecte')) . '"' : '') . '><small>' . h(t('config.de_la_junta')) . '</small></div>';
    }
    $html .= '<div class="camp ample"><label>' . h(t('camp.consentiment_extra')) . '</label><textarea name="consentiment_extra" rows="3">' . h(config('consentiment_extra')) . '</textarea>'
        . '<small>' . h(t('config.consentiment_extra_ajuda')) . '</small></div>';
    $societat = (string)(config('societat_nom') ?: 'Teixit');
    foreach (['full_alta_intro' => t('alta.intro_defecte', $societat), 'full_alta_peu' => t('alta.peu_defecte', $societat, (string)(config('societat_email') ?: '…'))] as $c => $defecte) {
        $html .= '<div class="camp ample"><label>' . h(t('camp.' . $c)) . '</label><textarea name="' . $c . '" rows="3" placeholder="' . h($defecte) . '">' . h(config($c)) . '</textarea>'
            . '<small>' . h(t('config.full_alta_ajuda')) . ' <a target="_blank" href="' . h(url('full_alta')) . '">' . h(t('alta.boto')) . '</a></small></div>';
    }
    $html .= '</div></fieldset>';
    $html .= '</div><div class="accions"><button>' . h(t('desa')) . '</button></div></form>';
    $html .= '<section class="fill"><header><h2>' . h(t('config.sistema')) . '</h2></header><dl class="fitxa">'
        . '<dt>' . h(t('config.versio')) . '</dt><dd>Teixit ' . TEIXIT_VERSIO . '</dd>'
        . '<dt>PHP</dt><dd>' . h(PHP_VERSION) . ' · SQLite ' . h(valor('SELECT sqlite_version()')) . '</dd>'
        . '<dt>' . h(t('config.carpeta_dades')) . '</dt><dd><code>' . h(DADES_DIR) . '</code></dd>'
        . '<dt>' . h(t('config.mida_bd')) . '</dt><dd>' . h(number_format(filesize(DADES_DIR . '/teixit.db') / 1024, 0, ',', '.')) . ' KB</dd></dl></section>'
        . avis_responsabilitat_text();
    pagina(t('menu.configuracio'), $html);
}

/** Mòduls que l'entitat fa servir i avisos de l'inici que vol veure */
function pagina_moduls(): void
{
    requereix('config.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $actius = (array)($_POST['moduls'] ?? []);
        $avisos = (array)($_POST['avisos'] ?? []);
        desa_config('moduls_desactivats', implode(',', array_diff(array_keys(MODULS), $actius)) ?: null);
        desa_config('avisos_desactivats', implode(',', array_diff(AVISOS, $avisos)) ?: null);
        audita('modifica', 'config', null, 'moduls');
        avis(t('desat'));
        redirigeix('moduls');
    }
    $html = '<form method="post" class="formulari">' . csrf_camp() . '<p>' . h(t('moduls.intro')) . '</p>'
        . '<fieldset class="camp ample"><legend>' . h(t('moduls.titol')) . '</legend><div class="opcions vertical caselles-moduls">';
    foreach (array_keys(MODULS) as $m) {
        $html .= '<label class="check"><input type="checkbox" name="moduls[]" value="' . h($m) . '"' . (modul_actiu($m) ? ' checked' : '') . '> <span><b>'
            . h(t('modul.' . $m)) . '</b><small>' . h(t('modul_ajuda.' . $m)) . '</small></span></label>';
    }
    $html .= '</div></fieldset><fieldset class="camp ample"><legend>' . h(t('avisos.titol')) . '</legend><small>' . h(t('avisos.ajuda')) . '</small><div class="opcions vertical caselles-moduls">';
    foreach (AVISOS as $a) {
        $html .= '<label class="check"><input type="checkbox" name="avisos[]" value="' . h($a) . '"' . (avis_actiu($a) ? ' checked' : '') . '> <span>' . h(t('avis_cfg.' . $a)) . '</span></label>';
    }
    $html .= '</div></fieldset><div class="accions"><button>' . h(t('desa')) . '</button></div></form>'
        . '<p class="nota">' . h(t('moduls.nota')) . '</p>';
    pagina(t('menu.moduls'), $html);
}

function pagina_modul_desactivat(?string $modul): void
{
    http_response_code(404);
    $html = '<div class="avis info">' . h(t('moduls.desactivat', t('modul.' . $modul))) . (pot('config.manage') ? ' <a href="' . h(url('moduls')) . '">' . h(t('menu.moduls')) . '</a>' : '') . '</div>';
    pagina(t('modul.' . $modul), $html);
}

function pagina_auditoria(): void
{
    requereix('auditoria.view');
    $params = [];
    $where = '1=1';
    if (($qq = get('q')) !== '') {
        $where .= ' AND (usuari LIKE ? OR accio LIKE ? OR entitat LIKE ? OR detall LIKE ?)';
        array_push($params, "%$qq%", "%$qq%", "%$qq%", "%$qq%");
    }
    $files = q("SELECT * FROM auditoria WHERE $where ORDER BY id DESC LIMIT 300", $params)->fetchAll();
    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="auditoria"><input type="search" name="q" value="' . h(get('q')) . '" placeholder="' . h(t('cercar')) . '"><button>' . h(t('filtrar')) . '</button></form>';
    $html .= '<div class="taula-wrap"><table class="taula compacta"><thead><tr><th>' . h(t('camp.data')) . '</th><th>' . h(t('camp.usuari')) . '</th><th>' . h(t('audit.accio'))
        . '</th><th>' . h(t('audit.element')) . '</th><th>' . h(t('audit.detall')) . '</th></tr></thead><tbody>';
    foreach ($files as $f) {
        $element = '';
        if ($f['entitat']) {
            $element = isset(entitats()[$f['entitat']]) ? t('ent1.' . $f['entitat']) : $f['entitat'];
            if ($f['element_id'] && isset(entitats()[$f['entitat']]) && $f['accio'] !== 'elimina') {
                $element = '<a href="' . h(url('fitxa', ['e' => $f['entitat'], 'id' => $f['element_id']])) . '">' . h($element . ' #' . $f['element_id']) . '</a>';
            } else {
                $element = h($element . ($f['element_id'] ? ' #' . $f['element_id'] : ''));
            }
        }
        $html .= '<tr><td>' . h(data_llegible($f['data'])) . '</td><td>' . h($f['usuari']) . '</td><td>' . h(t('audit.' . $f['accio'])) . '</td><td>' . $element . '</td><td>' . h($f['detall']) . '</td></tr>';
    }
    $html .= '</tbody></table></div><p class="peu-taula">' . h(t('audit.limit')) . '</p>';
    pagina(t('menu.auditoria'), $html);
}

/* ---------- Ajuda ---------- */

/** Manuals i guies que es poden descarregar en PDF */
const DOCUMENTS_PDF = ['manual-usuari', 'manual-usuario', 'manual-administracio', 'manual-administracion', 'guia-installacio', 'guia-instalacion'];

/** URL d'una captura del manual (docs/imatges/…) per a mostrar-la dins del programa */
function url_imatge_doc(string $ruta): ?string
{
    $rel = preg_replace('#^imatges/#', '', $ruta);
    return preg_match('#^(ca|es)/[\w-]+\.(jpg|png|webp)$#', $rel) && is_file(DOCS_DIR . '/imatges/' . $rel) ? url('doc_img', ['f' => $rel]) : null;
}

function serveix_imatge_doc(): never
{
    $rel = (string)get('f');
    if (!preg_match('#^(ca|es)/[\w-]+\.(jpg|png|webp)$#', $rel, $m) || !is_file($f = DOCS_DIR . '/imatges/' . $rel)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/' . ['jpg' => 'jpeg', 'png' => 'png', 'webp' => 'webp'][$m[2]]);
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

function serveix_pdf(): never
{
    $nom = (string)get('doc');
    $f = DOCS_DIR . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $nom . '.pdf';
    if (!in_array($nom, DOCUMENTS_PDF, true) || !is_file($f)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Teixit-' . $nom . '.pdf"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

function pagina_ajuda(): void
{
    if (get('doc') === 'canvis') {
        pagina_canvis();
        return;
    }
    if (get('doc') === 'privacitat') {
        pagina_privacitat();
        return;
    }
    if (get('doc') === 'llicencia') {
        pagina_llicencia();
        return;
    }
    // Dos manuals: el d'usuari (la gestió diària) i el d'administració (configuració, comptes, còpies i actualitzacions).
    // L'administrador/a pot vore els dos; la resta, només el d'usuari.
    $es = idioma() === 'es';
    $nomManual = ['usuari' => $es ? 'manual-usuario' : 'manual-usuari', 'admin' => $es ? 'manual-administracion' : 'manual-administracio'];
    $quin = get('manual') === 'admin' && pot('config.manage') ? 'admin' : 'usuari';
    $fitxer = DOCS_DIR . '/' . $nomManual[$quin] . '.md';
    $md = is_file($fitxer) ? file_get_contents($fitxer) : t('ajuda.no_manual');
    $docs = [$nomManual['usuari'] => t('ajuda.pdf_manual')] + (pot('config.manage') ? [$nomManual['admin'] => t('ajuda.pdf_manual_admin')] : [])
        + [$es ? 'guia-instalacion' : 'guia-installacio' => t('ajuda.pdf_guia')];
    $enllacos = '';
    foreach ($docs as $doc => $text) {
        if (is_file(DOCS_DIR . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $doc . '.pdf')) {
            $enllacos .= '<a class="boto secundari" target="_blank" href="' . h(url('manual_pdf', ['doc' => $doc])) . '">📄 ' . h($text) . '</a>';
        }
    }
    if (is_file(ARREL_DIR . DIRECTORY_SEPARATOR . 'CHANGELOG.md')) {
        $enllacos .= '<a class="boto secundari" href="' . h(url('ajuda', ['doc' => 'canvis'])) . '">🆕 ' . h(t('ajuda.canvis')) . '</a>';
    }
    if (is_file(ARREL_DIR . DIRECTORY_SEPARATOR . 'PRIVACITAT.md')) {
        $enllacos .= '<a class="boto secundari" href="' . h(url('ajuda', ['doc' => 'privacitat'])) . '">🔒 ' . h(t('ajuda.privacitat')) . '</a>';
    }
    if (is_file(ARREL_DIR . DIRECTORY_SEPARATOR . 'LLICENCIA.md')) {
        $enllacos .= '<a class="boto secundari" href="' . h(url('ajuda', ['doc' => 'llicencia'])) . '">⚖ ' . h(t('ajuda.llicencia')) . '</a>';
    }
    // Teixit no es connecta a Internet: el botó només obri al navegador la pàgina de l'última versió
    $enllacos .= '<a class="boto secundari" target="_blank" rel="noopener" href="https://github.com/jossanmar9/teixit/releases/latest" title="'
        . h(t('ajuda.versio_ajuda', TEIXIT_VERSIO)) . '">🔄 ' . h(t('ajuda.versio_nova')) . '</a>';
    $pestanyes = !pot('config.manage') ? '' : '<nav class="pestanyes-manual">'
        . '<a href="' . h(url('ajuda')) . '"' . ($quin === 'usuari' ? ' class="actiu"' : '') . '>📘 ' . h(t('ajuda.manual_usuari')) . '</a>'
        . '<a href="' . h(url('ajuda', ['manual' => 'admin'])) . '"' . ($quin === 'admin' ? ' class="actiu"' : '') . '>🛠 ' . h(t('ajuda.manual_admin')) . '</a></nav>';
    $html = ($enllacos ? '<div class="accions">' . $enllacos . '</div>' : '') . $pestanyes . '<p class="nota">' . h(t('ajuda.versio_ajuda', TEIXIT_VERSIO)) . '</p>' . avis_responsabilitat_text()
        . '<article class="manual">' . markdown_simple(preg_replace('/^# .*\R/', '', $md), 'url_imatge_doc') . '</article>';
    pagina(t('menu.ajuda'), $html);
}

/** Declaració de privacitat (PRIVACITAT.md): la part de l'idioma de l'usuari */
function pagina_privacitat(): void
{
    $fitxer = ARREL_DIR . DIRECTORY_SEPARATOR . 'PRIVACITAT.md';
    $parts = preg_split('/^---\s*$/m', is_file($fitxer) ? (string)file_get_contents($fitxer) : '');
    $md = preg_replace('/^# .*\R/', '', $parts[idioma() === 'es' ? 1 : 0] ?? $parts[0]);
    $html = '<div class="accions"><a class="boto secundari" href="' . h(url('ajuda')) . '">← ' . h(t('menu.ajuda')) . '</a></div>'
        . '<article class="manual">' . markdown_simple($md) . '</article>';
    pagina(t('ajuda.privacitat'), $html);
}

/** Resum de la llicència (LLICENCIA.md) en l'idioma de l'usuari; l'enllaç a LICENSE porta al text original */
function pagina_llicencia(): void
{
    $fitxer = ARREL_DIR . DIRECTORY_SEPARATOR . 'LLICENCIA.md';
    $parts = preg_split('/^---\s*$/m', is_file($fitxer) ? (string)file_get_contents($fitxer) : '');
    $md = preg_replace('/^# .*\R/', '', $parts[idioma() === 'es' ? 1 : 0] ?? $parts[0]);
    $md = str_replace('(LICENSE)', '(https://www.gnu.org/licenses/agpl-3.0.html)', $md);
    $html = '<div class="accions"><a class="boto secundari" href="' . h(url('ajuda')) . '">← ' . h(t('menu.ajuda')) . '</a></div>'
        . '<article class="manual">' . markdown_simple($md) . '</article>';
    pagina(t('ajuda.llicencia'), $html);
}

/** Registre de canvis (CHANGELOG.md), per a saber què porta cada versió */
function pagina_canvis(): void
{
    $fitxer = ARREL_DIR . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
    $md = is_file($fitxer) ? (string)file_get_contents($fitxer) : '';
    $md = preg_replace('/^# .*\R/', '', $md);
    $md = preg_replace('/^\[[^\]]+\]: \S+\R?/m', '', $md);       // definicions d'enllaços del final
    $md = preg_replace('/^(##+ )\[([^\]]+)\]/m', '$1$2', $md);     // «## [1.2.0]» → «## 1.2.0»
    $html = '<div class="accions"><a class="boto secundari" href="' . h(url('ajuda')) . '">← ' . h(t('menu.ajuda')) . '</a></div>'
        . '<p>' . h(t('ajuda.versio_actual', TEIXIT_VERSIO)) . '</p>'
        . '<article class="manual">' . markdown_simple($md) . '</article>';
    pagina(t('ajuda.canvis'), $html);
}
