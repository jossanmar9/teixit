<?php
// Model de registre de les activitats de tractament (RGPD, art. 30), generat amb el que hi ha a Teixit
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Activitats de tractament: clau => nombre aproximat de persones afectades (les que no tenen dades no es mostren) */
function activitats_tractament(): array
{
    $n = fn(string $sql) => (int)valor($sql);
    return [
        'socis' => $n("SELECT COUNT(*) FROM socis WHERE estat = 'actiu'"),
        'escola' => $n("SELECT COUNT(*) FROM alumnes WHERE estat = 'actiu'") + $n("SELECT COUNT(DISTINCT tutor_id) FROM tutories"),
        'professorat' => $n("SELECT COUNT(*) FROM professors WHERE estat = 'actiu'"),
        'agrupacions' => $n("SELECT COUNT(DISTINCT persona_id) FROM membres WHERE estat = 'actiu'"),
        'cobraments' => $n("SELECT COUNT(DISTINCT persona_id) FROM rebuts") + $n("SELECT COUNT(*) FROM mandats WHERE estat = 'actiu'"),
        'imatge' => $n("SELECT COUNT(*) FROM persones WHERE consentiment_imatge <> 'pendent'"),
        'junta' => $n("SELECT COUNT(DISTINCT persona_id) FROM carrecs"),
        'comunicacions' => $n("SELECT COUNT(*) FROM persones WHERE actiu = 1 AND COALESCE(email, '') <> ''"),
        'loteria' => $n("SELECT COUNT(DISTINCT persona_id) FROM talonaris"),
        'instruments' => $n("SELECT COUNT(DISTINCT persona_id) FROM cessions"),
        'uniformes' => $n("SELECT COUNT(DISTINCT persona_id) FROM uniforme_lliuraments"),
    ];
}

/** Mòdul de cada activitat de tractament: si està desactivat, no s'hi posa */
const MODUL_ACTIVITATS = ['escola' => 'escola', 'professorat' => 'escola', 'agrupacions' => 'agrupacions', 'cobraments' => 'economia', 'junta' => 'junta',
    'loteria' => 'loteria', 'instruments' => 'instruments', 'uniformes' => 'uniformes'];

function pagina_rgpd(): void
{
    requereix('societat.view');
    $societat = config('societat_nom') ?: 'Teixit';
    $dades = array_filter([config('societat_cif') ? 'CIF ' . config('societat_cif') : null, config('societat_adreca'), config('societat_poblacio'), config('societat_email'), config('societat_telefon')]);
    $mesures = [t('rgpd.mes.local'), t('rgpd.mes.comptes'), t('rgpd.mes.registre'), t('rgpd.mes.copies')];
    if (carpeta_externa()) {
        $mesures[] = xifrat_actiu() ? t('rgpd.mes.externa_xifrada') : t('rgpd.mes.externa');
    }
    $mesures[] = t('rgpd.mes.paper');

    document_inici(t('rgpd.titol'), t('rgpd.titol'), t('rgpd.consell'));
    echo '<section class="full document llistat rgpd">' . capcalera_societat() . '<h2 class="centrat">' . h(t('rgpd.titol')) . '</h2>';
    echo '<p class="subtitol centrat">' . h(t('rgpd.subtitol', data_llarga())) . '</p>';
    echo '<h3>' . h(t('rgpd.responsable')) . '</h3><table class="taula-llistat"><tbody>'
        . '<tr><th>' . h(t('rgpd.entitat')) . '</th><td>' . h($societat) . '</td></tr>'
        . '<tr><th>' . h(t('rgpd.dades_contacte')) . '</th><td>' . h(implode(' · ', $dades) ?: '……') . '</td></tr>'
        . '<tr><th>' . h(t('rgpd.dpd')) . '</th><td>' . h(t('rgpd.dpd_text')) . '</td></tr></tbody></table>';

    $n = 0;
    foreach (activitats_tractament() as $k => $quants) {
        if ((!$quants && !in_array($k, ['socis', 'comunicacions'], true)) || (isset(MODUL_ACTIVITATS[$k]) && !modul_actiu(MODUL_ACTIVITATS[$k]))) {
            continue;
        }
        $n++;
        echo '<h3>' . $n . '. ' . h(t("rgpd.act.$k")) . '</h3><table class="taula-llistat"><tbody>';
        foreach (['finalitat', 'base', 'interessats', 'dades', 'destinataris', 'conservacio'] as $camp) {
            $text = t("rgpd.$k.$camp");
            if ($camp === 'interessats' && $quants) {
                $text .= ' ' . t('rgpd.aprox', $quants);
            }
            echo '<tr><th>' . h(t("rgpd.camp.$camp")) . '</th><td>' . h($text) . '</td></tr>';
        }
        echo '<tr><th>' . h(t('rgpd.camp.transferencies')) . '</th><td>' . h(t('rgpd.cap_transferencia')) . '</td></tr></tbody></table>';
    }

    echo '<h3>' . h(t('rgpd.mesures')) . '</h3><ul class="llista-cert">' . implode('', array_map(fn($m) => '<li>' . h($m) . '</li>', $mesures)) . '</ul>';
    echo '<p class="text-legal">' . h(t('rgpd.avis')) . '</p></section>';
    audita('rgpd');
    document_fi();
}
