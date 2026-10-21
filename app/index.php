<?php
// Teixit: Gestió lliure per a associacions
// Punt d'entrada únic. També fa de "router" per al servidor integrat de PHP.
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

if (PHP_SAPI === 'cli-server') {
    $ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($ruta !== '/' && is_file(__DIR__ . $ruta) && str_starts_with(realpath(__DIR__ . $ruta), __DIR__ . DIRECTORY_SEPARATOR . 'assets')) {
        return false; // fitxer estàtic
    }
    if ($ruta !== '/' && $ruta !== '/index.php') {
        http_response_code(404);
        exit('404');
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';
require SRC_DIR . '/entitats.php';
require SRC_DIR . '/crud.php';
foreach (glob(SRC_DIR . '/pages/*.php') as $fitxer) {
    require $fitxer;
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

inicia_sessio();
comprova_csrf();

$r = (string)get('r', 'inici');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

try {
    // Peticions que no necessiten sessió
    if ($r === 'copia_tancament') {
        copia_en_tancar();
    }
    if ($r === 'logo') {
        serveix_logo();
    }

    if ($r === 'idioma') {
        $l = get('l');
        if (isset(IDIOMES[$l])) {
            $_SESSION['idioma'] = $l;
            if (usuari()) {
                q('UPDATE usuaris SET idioma = ? WHERE id = ?', [$l, usuari()['id']]);
            }
        }
        $tornar = get('tornar');
        header('Location: ' . (str_starts_with($tornar, '/') && !str_starts_with($tornar, '//') ? $tornar : '?'));
        exit;
    }

    // Primera execució: no hi ha cap usuari
    if ((int)valor('SELECT COUNT(*) FROM usuaris') === 0) {
        pagina_configuracio_inicial();
        exit;
    }

    if ($r === 'recupera') {
        if (usuari()) {
            redirigeix('inici');
        }
        pagina_recuperacio();
        exit;
    }

    if (!usuari()) {
        pagina_entrada();
        exit;
    }
    copia_automatica();

    // Pantalles d'un mòdul que l'entitat ha desactivat
    $clauModul = in_array($r, ['llista', 'fitxa', 'edita', 'elimina'], true) ? (string)get('e') : $r;
    if (!disponible($clauModul)) {
        pagina_modul_desactivat(modul_de($clauModul));
        exit;
    }

    switch ($r) {
        case 'inici':
            pagina_inici();
            break;
        case 'eixir':
            if ($post) {
                audita('eixida');
                session_destroy();
            }
            header('Location: ?');
            break;
        case 'llista':
            pagina_llista((string)get('e'));
            break;
        case 'fitxa':
            pagina_fitxa((string)get('e'), (int)get('id'));
            break;
        case 'edita':
            pagina_edita((string)get('e'), get('id') !== '' ? (int)get('id') : null);
            break;
        case 'elimina':
            if ($post) {
                accio_elimina((string)get('e'), (int)get('id'));
            }
            redirigeix('inici');
        case 'codi':
            pagina_codi();
            break;
        case 'perfil':
            pagina_perfil();
            break;
        case 'horari':
            pagina_horari();
            break;
        case 'notes':
            pagina_notes();
            break;
        case 'butlleti':
            pagina_butlleti();
            break;
        case 'llistats':
            pagina_llistats();
            break;
        case 'consentiment':
            pagina_consentiment();
            break;
        case 'certificat':
            pagina_certificat();
            break;
        case 'passa_curs':
            pagina_passa_curs();
            break;
        case 'importa':
            pagina_importa();
            break;
        case 'economia':
            pagina_economia();
            break;
        case 'genera_rebuts':
            pagina_genera_rebuts();
            break;
        case 'remeses':
            pagina_remeses();
            break;
        case 'remesa':
            pagina_remesa();
            break;
        case 'remesa_xml':
            descarrega_remesa_xml();
        case 'rebut_accio':
            accio_rebut();
            break;
        case 'rebut_doc':
            pagina_rebut_doc();
            break;
        case 'mandat_doc':
            pagina_mandat_doc();
            break;
        case 'economia_config':
            pagina_economia_config();
            break;
        case 'loteria':
            pagina_loteria();
            break;
        case 'participacions':
            pagina_participacions();
            break;
        case 'junta':
            pagina_junta();
            break;
        case 'junta_doc':
            pagina_junta_doc();
            break;
        case 'reunio_doc':
            pagina_reunio_doc();
            break;
        case 'document_fitxer':
            serveix_document();
        case 'full_alta':
            pagina_full_alta();
            break;
        case 'comunicats':
            pagina_comunicats();
            break;
        case 'rgpd':
            pagina_rgpd();
            break;
        case 'avis_accepta':
            accio_accepta_avis();
            break;
        case 'uniformes':
            pagina_uniformes();
            break;
        case 'moduls':
            pagina_moduls();
            break;
        case 'caixa':
            pagina_caixa();
            break;
        case 'usuaris':
            pagina_usuaris();
            break;
        case 'configuracio':
            pagina_configuracio();
            break;
        case 'auditoria':
            pagina_auditoria();
            break;
        case 'copia':
            pagina_copia();
            break;
        case 'ajuda':
            pagina_ajuda();
            break;
        case 'manual_pdf':
            serveix_pdf();
        case 'doc_img':
            serveix_imatge_doc();
        default:
            if (isset(entitats()[$r])) {
                redirigeix('llista', ['e' => $r]);
            }
            http_response_code(404);
            pagina(t('err.no_trobat'), '<div class="buit">' . h(t('err.no_trobat')) . '</div>');
    }
} catch (Throwable $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log((string)$ex);
    http_response_code(500);
    pagina(t('err.titol'), '<div class="avis error">' . h(t('err.inesperat')) . '</div><pre class="detall">' . h($ex->getMessage()) . '</pre>');
}
