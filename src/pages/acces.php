<?php
// Entrada, configuració inicial i perfil
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const MIN_CONTRASENYA = 8;

function valida_contrasenya(string $c1, string $c2): ?string
{
    if (mb_strlen($c1) < MIN_CONTRASENYA) {
        return t('err.contrasenya_curta', MIN_CONTRASENYA);
    }
    if ($c1 !== $c2) {
        return t('err.contrasenyes');
    }
    return null;
}

function pagina_configuracio_inicial(): void
{
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = post('societat_nom');
        $usuari = post('usuari');
        $l = post('idioma');
        $error = valida_contrasenya((string)post('contrasenya'), (string)post('contrasenya2'));
        if (!in_array(post('tipus_entitat'), TIPUS_ENTITAT, true)) {
            $error = t('err.tipus_entitat');
        }
        if (!$nom || !preg_match('/^[\p{L}\p{N}._-]{3,40}$/u', $usuari)) {
            $error = t('err.dades_inicials');
        }
        $usuari2 = post('usuari2');
        if (!$error && $usuari2 !== '') {
            if (!preg_match('/^[\p{L}\p{N}._-]{3,40}$/u', $usuari2) || mb_strtolower($usuari2) === mb_strtolower($usuari)) {
                $error = t('err.nom_usuari');
            } else {
                $error = valida_contrasenya((string)post('contrasenya_b'), (string)post('contrasenya_b2'));
            }
        }
        if (!$error && post('accepta_avis') !== '1') {
            $error = t('avis_resp.cal_acceptar');
        }
        if (!$error) {
            db()->beginTransaction();
            desa_config('societat_nom', $nom);
            desa_config('idioma', isset(IDIOMES[$l]) ? $l : 'ca');
            desa_tipus_entitat((string)post('tipus_entitat'), true);
            sembra_tipus_socis(db(), isset(IDIOMES[$l]) ? $l : 'ca');
            desa_config('avis_responsabilitat', date('Y-m-d H:i') . '|' . $usuari);
            q('INSERT INTO usuaris(usuari, contrasenya, idioma) VALUES(?, ?, ?)', [$usuari, password_hash(post('contrasenya'), PASSWORD_DEFAULT), $l]);
            $id = (int)db()->lastInsertId();
            assigna_rol($id, 'admin');
            if ($usuari2 !== '') {
                q('INSERT INTO usuaris(usuari, contrasenya, idioma) VALUES(?, ?, ?)', [$usuari2, password_hash(post('contrasenya_b'), PASSWORD_DEFAULT), $l]);
                assigna_rol((int)db()->lastInsertId(), 'administratiu');
            }
            if (post('exemple') === '1') {
                carrega_dades_exemple();
            }
            $codi = genera_codi_recuperacio();
            db()->commit();
            session_regenerate_id(true);
            $_SESSION['usuari_id'] = $id;
            $_SESSION['idioma'] = $l;
            audita('instal·lacio', null, null, $nom);
            avis(t('benvinguda'));
            // Abans de començar, el codi per a recuperar l'accés si s'oblida la contrasenya
            $_SESSION['codi_nou'] = $codi;
            redirigeix('codi');
        }
    }
    $html = '<h1>' . h(t('setup.titol')) . '</h1><p>' . h(t('setup.text')) . '</p>';
    if ($error) {
        $html .= '<div class="avis error">' . h($error) . '</div>';
    }
    $html .= '<form method="post" class="formulari vertical">' . csrf_camp()
        . '<fieldset class="camp"><legend>' . h(t('camp.tipus_entitat')) . '</legend><small>' . h(t('setup.tipus_ajuda')) . '</small><div class="tipus-entitat">';
    foreach (TIPUS_ENTITAT as $tp) {
        $html .= '<label class="tipus-opcio"><input type="radio" name="tipus_entitat" value="' . h($tp) . '" required' . (post('tipus_entitat') === $tp ? ' checked' : '') . '>'
            . '<span><b>' . h(t('tipus_entitat.' . $tp)) . '</b><small>' . h(t('tipus_entitat_ajuda.' . $tp)) . '</small></span></label>';
    }
    $html .= '</div></fieldset>'
        . '<div class="camp"><label>' . h(t('setup.nom')) . '</label><input name="societat_nom" required value="' . h(post('societat_nom')) . '" placeholder="' . h(t('setup.exemple_nom')) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.idioma')) . '</label><select name="idioma">';
    foreach (IDIOMES as $k => $n) {
        $html .= '<option value="' . $k . '"' . ($k === idioma() ? ' selected' : '') . '>' . h($n) . '</option>';
    }
    $html .= '</select></div>'
        . '<div class="camp"><label>' . h(t('setup.usuari_admin')) . '</label><input name="usuari" required autocomplete="username" value="' . h(post('usuari', 'admin')) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya')) . '</label><input type="password" name="contrasenya" required minlength="' . MIN_CONTRASENYA . '" autocomplete="new-password"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="contrasenya2" required autocomplete="new-password"></div>'
        . '<fieldset class="camp"><legend>' . h(t('setup.administratiu')) . '</legend><small>' . h(t('setup.administratiu_ajuda')) . '</small>'
        . '<div class="camp"><label>' . h(t('camp.usuari')) . '</label><input name="usuari2" autocomplete="off" value="' . h(post('usuari2')) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya')) . '</label><input type="password" name="contrasenya_b" autocomplete="new-password"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="contrasenya_b2" autocomplete="new-password"></div></fieldset>'
        . '<div class="camp camp-check"><label><input type="checkbox" name="exemple" value="1"> ' . h(t('setup.exemple')) . '</label><small>' . h(t('setup.exemple_ajuda')) . '</small></div>'
        . '<div class="avis-responsabilitat"><h2>⚠ ' . h(t('avis_resp.titol')) . '</h2><p>' . h(t('avis_resp.text')) . '</p>'
        . '<label class="check"><input type="checkbox" name="accepta_avis" value="1" required> <b>' . h(t('avis_resp.accepte')) . '</b></label></div>'
        . '<button>' . h(t('setup.comenca')) . '</button></form>';
    $html .= '<p class="idiomes-peu">' . enllacos_idioma() . '</p>';
    pagina_simple(t('setup.titol'), $html);
}

function enllacos_idioma(): string
{
    $parts = [];
    foreach (IDIOMES as $k => $n) {
        $parts[] = $k === idioma() ? '<strong>' . h($n) . '</strong>' : '<a href="' . h(url('idioma', ['l' => $k, 'tornar' => '/'])) . '">' . h($n) . '</a>';
    }
    return implode(' · ', $parts);
}

function pagina_entrada(): void
{
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && get('r') === 'entrada') {
        $intents = $_SESSION['intents'] ?? 0;
        // El servidor integrat de PHP atén una petició cada vegada: no fem sleep(), bloquegem un temps
        if ($intents >= 5 && time() - ($_SESSION['darrer_intent'] ?? 0) < 30) {
            pagina_simple(t('entra'), '<div class="avis error">' . h(t('err.massa_intents')) . '</div><p><a href="?">' . h(t('torna')) . '</a></p>');
            return;
        }
        $_SESSION['darrer_intent'] = time();
        $u = fila('SELECT * FROM usuaris WHERE usuari = ? AND actiu = 1', [post('usuari')]);
        if ($u && password_verify((string)post('contrasenya'), $u['contrasenya'])) {
            session_regenerate_id(true);
            $_SESSION['usuari_id'] = $u['id'];
            $_SESSION['intents'] = 0;
            if ($u['idioma']) {
                $_SESSION['idioma'] = $u['idioma'];
            }
            if (password_needs_rehash($u['contrasenya'], PASSWORD_DEFAULT)) {
                q('UPDATE usuaris SET contrasenya = ? WHERE id = ?', [password_hash(post('contrasenya'), PASSWORD_DEFAULT), $u['id']]);
            }
            q("UPDATE usuaris SET ultim_acces = datetime('now','localtime') WHERE id = ?", [$u['id']]);
            audita('entrada');
            redirigeix('inici');
        }
        $_SESSION['intents'] = $intents + 1;
        q('INSERT INTO auditoria(accio, usuari, detall) VALUES(?, ?, ?)', ['entrada_fallida', mb_substr((string)post('usuari'), 0, 60), $_SERVER['REMOTE_ADDR'] ?? '']);
        $error = t('err.entrada');
    }
    $html = '';
    if ($error) {
        $html .= '<div class="avis error">' . h($error) . '</div>';
    }
    $html .= '<form method="post" action="' . h(url('entrada')) . '" class="formulari vertical">' . csrf_camp()
        . '<div class="camp"><label>' . h(t('camp.usuari')) . '</label><input name="usuari" required autofocus autocomplete="username" value="' . h(post('usuari')) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya')) . '</label><input type="password" name="contrasenya" required autocomplete="current-password"></div>'
        . '<button>' . h(t('entra')) . '</button></form>'
        . '<p class="nota">' . h(t('entrada.oblit')) . ' <a href="' . h(url('recupera')) . '">' . h(t('entrada.oblit_admin')) . '</a></p>'
        . '<p class="idiomes-peu">' . enllacos_idioma() . '</p>';
    pagina_simple(t('entra'), $html);
}

function pagina_perfil(): void
{
    $u = usuari();
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!password_verify((string)post('actual'), $u['contrasenya'])) {
            $error = t('err.contrasenya_actual');
        } else {
            $error = valida_contrasenya((string)post('contrasenya'), (string)post('contrasenya2'));
        }
        if (!$error) {
            q('UPDATE usuaris SET contrasenya = ? WHERE id = ?', [password_hash(post('contrasenya'), PASSWORD_DEFAULT), $u['id']]);
            audita('canvi_contrasenya', 'usuaris', $u['id']);
            avis(t('contrasenya_canviada'));
            redirigeix('perfil');
        }
    }
    $rols = array_map('nom_rol', q('SELECT r.* FROM rols r JOIN usuari_rols ur ON ur.rol_id = r.id WHERE ur.usuari_id = ?', [$u['id']])->fetchAll());
    $html = '<dl class="fitxa"><dt>' . h(t('camp.usuari')) . '</dt><dd>' . h($u['usuari']) . '</dd>'
        . '<dt>' . h(t('camp.rol')) . '</dt><dd>' . h(implode(', ', $rols) ?: '—') . '</dd></dl>';
    $html .= '<section class="fill"><header><h2>' . h(t('canvia_contrasenya')) . '</h2></header>';
    if ($error) {
        $html .= '<div class="avis error">' . h($error) . '</div>';
    }
    $html .= '<form method="post" class="formulari vertical estret">' . csrf_camp()
        . '<div class="camp"><label>' . h(t('camp.contrasenya_actual')) . '</label><input type="password" name="actual" required autocomplete="current-password"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya_nova')) . '</label><input type="password" name="contrasenya" required minlength="' . MIN_CONTRASENYA . '" autocomplete="new-password"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="contrasenya2" required autocomplete="new-password"></div>'
        . '<button>' . h(t('desa')) . '</button></form></section>';
    pagina(t('menu.perfil'), $html);
}
