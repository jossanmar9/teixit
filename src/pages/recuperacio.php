<?php
// Recuperar l'accés quan l'administrador/a ha oblidat la contrasenya
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Dues maneres:
//  1. Codi de recuperació: es mostra una sola vegada (en la configuració inicial o quan es genera
//     a Usuaris) i es guarda en paper. Només se'n guarda el resum (password_hash), mai el codi.
//  2. Fitxer a la carpeta de dades: qui pot crear un fitxer en eixa carpeta ja té accés a la base
//     de dades, així que no obri cap porta nova. El fitxer caduca al cap d'una hora i s'esborra en usar-lo.

const CODI_ALFABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sense 0/O ni 1/I, que es confonen
const FITXER_RECUPERACIO = 'recupera-acces';
const RECUPERACIO_MINUTS = 60;
const RECUPERACIO_INTENTS = 5;
const RECUPERACIO_ESPERA = 60;

/** Crea un codi nou (invalida l'anterior) i el retorna en clar perquè es mostre una sola vegada */
function genera_codi_recuperacio(): string
{
    $codi = '';
    for ($i = 0; $i < 16; $i++) {
        $codi .= CODI_ALFABET[random_int(0, strlen(CODI_ALFABET) - 1)];
    }
    desa_config('codi_recuperacio', password_hash($codi, PASSWORD_DEFAULT));
    desa_config('codi_recuperacio_data', date('Y-m-d H:i:s'));
    return implode('-', str_split($codi, 4));
}

function normalitza_codi(string $codi): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($codi));
}

function te_codi_recuperacio(): bool
{
    return (bool)config('codi_recuperacio');
}

/** Fitxer de recuperació vàlid a la carpeta de dades (accepta «recupera-acces», «.txt» i «.txt.txt») */
function fitxer_recuperacio(): ?string
{
    foreach (glob(DADES_DIR . DIRECTORY_SEPARATOR . FITXER_RECUPERACIO . '*') ?: [] as $f) {
        if (is_file($f) && preg_match('/^' . FITXER_RECUPERACIO . '(\.txt){0,2}$/i', basename($f))) {
            if (filemtime($f) >= time() - RECUPERACIO_MINUTS * 60) {
                return $f;
            }
            @unlink($f); // caducat: no ha de quedar com una porta oberta
        }
    }
    return null;
}

/** Avís a l'inici per a l'administrador si encara no hi ha codi (instal·lacions d'abans de la 1.3) */
function avis_codi_recuperacio(): string
{
    if (!pot('usuaris.manage') || te_codi_recuperacio()) {
        return '';
    }
    return '<div class="avis atencio">🔑 ' . h(t('recup.avis_sense_codi')) . ' <a href="' . h(url('usuaris')) . '#codi-recuperacio">' . h(t('recup.genera')) . '</a></div>';
}

/** Secció de la pàgina Usuaris per a generar un codi nou */
function seccio_codi_recuperacio(): string
{
    $data = config('codi_recuperacio_data');
    $estat = te_codi_recuperacio() ? t('recup.estat_si', data_llegible($data)) : t('recup.estat_no');
    return '<section class="fill" id="codi-recuperacio"><header><h2>🔑 ' . h(t('recup.titol')) . '</h2></header>'
        . '<p>' . h(t('recup.explicacio')) . '</p><p><strong>' . h($estat) . '</strong></p>'
        . '<form method="post" action="' . h(url('usuaris')) . '">' . csrf_camp()
        . '<button name="accio" value="genera_codi"' . (te_codi_recuperacio() ? ' onclick="return confirm(\'' . h(t('recup.confirma_nou')) . '\')"' : '') . '>'
        . h(te_codi_recuperacio() ? t('recup.genera_nou') : t('recup.genera')) . '</button></form></section>';
}

/** Mostra el codi acabat de generar (una sola vegada) */
function pagina_codi(): void
{
    $codi = $_SESSION['codi_nou'] ?? null;
    unset($_SESSION['codi_nou']);
    if (!$codi) {
        redirigeix('inici');
    }
    $societat = config('societat_nom', '');
    $html = '<h1>🔑 ' . h(t('recup.titol')) . '</h1>'
        . '<p>' . h(t('recup.guarda')) . '</p>'
        . '<p class="codi-recuperacio">' . h($codi) . '</p>'
        . '<p class="nota">' . h(t('recup.on_guardar')) . '</p>'
        . '<p class="nomes-impres">' . h($societat) . ' · ' . h(t('recup.impres', date('d/m/Y'))) . '</p>'
        . '<div class="accions no-imprimir"><button type="button" class="secundari" onclick="window.print()">🖨 ' . h(t('recup.imprimeix')) . '</button>'
        . '<a class="boto" href="' . h(url('inici')) . '">' . h(t('recup.guardat')) . '</a></div>';
    pagina_simple(t('recup.titol'), $html);
}

/** Pàgina pública «He oblidat la contrasenya» */
function pagina_recuperacio(): void
{
    $fitxer = fitxer_recuperacio();
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fallides = (int)config('recupera_fallides', '0');
        $darrer = (int)config('recupera_darrer', '0');
        // El límit es guarda a la base de dades, no a la sessió: esborrar la galeta no el salta
        if ($fallides >= RECUPERACIO_INTENTS && time() - $darrer < RECUPERACIO_ESPERA) {
            $error = t('recup.massa_intents', RECUPERACIO_ESPERA);
        } else {
            $u = fila('SELECT * FROM usuaris WHERE usuari = ? AND actiu = 1', [post('usuari')]);
            $esAdmin = $u && rol_usuari((int)$u['id']) === 'admin';
            $codiBo = !$fitxer && te_codi_recuperacio() && password_verify(normalitza_codi((string)post('codi')), config('codi_recuperacio'));
            if (!$esAdmin || !($fitxer || $codiBo)) {
                desa_config('recupera_fallides', (string)($fallides >= RECUPERACIO_INTENTS ? 1 : $fallides + 1));
                desa_config('recupera_darrer', (string)time());
                q('INSERT INTO auditoria(accio, usuari, detall) VALUES(?, ?, ?)', ['recuperacio_fallida', mb_substr((string)post('usuari'), 0, 60), $fitxer ? 'fitxer' : 'codi']);
                $error = t('recup.err_dades');
            } else {
                $error = valida_contrasenya((string)post('contrasenya'), (string)post('contrasenya2'));
            }
            if (!$error) {
                db()->beginTransaction();
                q('UPDATE usuaris SET contrasenya = ? WHERE id = ?', [password_hash(post('contrasenya'), PASSWORD_DEFAULT), $u['id']]);
                $codiNou = genera_codi_recuperacio(); // el codi usat ja no val
                desa_config('recupera_fallides', '0');
                db()->commit();
                if ($fitxer) {
                    @unlink($fitxer);
                }
                session_regenerate_id(true);
                $_SESSION['usuari_id'] = $u['id'];
                $_SESSION['intents'] = 0;
                if ($u['idioma']) {
                    $_SESSION['idioma'] = $u['idioma'];
                }
                q('INSERT INTO auditoria(usuari_id, usuari, accio, entitat, element_id, detall) VALUES(?,?,?,?,?,?)',
                    [$u['id'], $u['usuari'], 'recuperacio_contrasenya', 'usuaris', $u['id'], $fitxer ? 'fitxer' : 'codi']);
                avis(t('recup.feta'));
                $_SESSION['codi_nou'] = $codiNou;
                redirigeix('codi');
            }
        }
    }

    $html = '<h1>' . h(t('recup.oblit_titol')) . '</h1>';
    if ($error) {
        $html .= '<div class="avis error">' . h($error) . '</div>';
    }
    $html .= '<p>' . h(t('recup.nomes_admin')) . '</p>';
    if ($fitxer) {
        $html .= '<div class="avis ok">' . h(t('recup.fitxer_trobat')) . '</div>';
    } elseif (te_codi_recuperacio()) {
        $html .= '<p>' . h(t('recup.amb_codi')) . '</p>';
    }
    $html .= '<form method="post" action="' . h(url('recupera')) . '" class="formulari vertical">' . csrf_camp()
        . '<div class="camp"><label>' . h(t('recup.usuari_admin')) . '</label><input name="usuari" required autocomplete="username" value="' . h(post('usuari')) . '"></div>';
    if (!$fitxer) {
        $html .= '<div class="camp"><label>' . h(t('recup.codi')) . '</label><input name="codi" required autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX" class="entrada-codi"></div>';
    }
    $html .= '<div class="camp"><label>' . h(t('camp.contrasenya_nova')) . '</label><input type="password" name="contrasenya" required minlength="' . MIN_CONTRASENYA . '" autocomplete="new-password"></div>'
        . '<div class="camp"><label>' . h(t('camp.contrasenya2')) . '</label><input type="password" name="contrasenya2" required autocomplete="new-password"></div>'
        . '<button>' . h(t('recup.canvia')) . '</button></form>';
    if (!$fitxer) {
        $html .= '<details class="sense-codi"><summary>' . h(t('recup.sense_codi')) . '</summary>'
            . '<ol><li>' . h(t('recup.pas1')) . '<br><code class="ruta">' . h(DADES_DIR) . '</code></li>'
            . '<li>' . t('recup.pas2', '<code>' . FITXER_RECUPERACIO . '</code>') . '</li>'
            . '<li>' . h(t('recup.pas3', RECUPERACIO_MINUTS)) . '</li></ol></details>';
    }
    $html .= '<p><a href="?">← ' . h(t('recup.torna')) . '</a></p>';
    pagina_simple(t('recup.oblit_titol'), $html);
}
