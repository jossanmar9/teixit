<?php
// Avís de responsabilitat: es mostra en la configuració inicial i, a les instal·lacions d'abans, una vegada a l'inici
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Caixa de l'avís a l'inici mentre ningú no l'haja acceptat */
function avis_responsabilitat_pendent(): string
{
    if (config('avis_responsabilitat')) {
        return '';
    }
    return '<div class="avis-responsabilitat"><h2>⚠ ' . h(t('avis_resp.titol')) . '</h2><p>' . h(t('avis_resp.text')) . '</p>'
        . '<form method="post" action="' . h(url('avis_accepta')) . '">' . csrf_camp() . '<button>' . h(t('avis_resp.entesos')) . '</button></form></div>';
}

/** Text de l'avís per a l'ajuda i la configuració, amb qui l'ha acceptat */
function avis_responsabilitat_text(): string
{
    $acceptat = (string)config('avis_responsabilitat');
    [$quan, $qui] = array_pad(explode('|', $acceptat, 2), 2, '');
    return '<div class="avis-responsabilitat"><h2>⚠ ' . h(t('avis_resp.titol')) . '</h2><p>' . h(t('avis_resp.text')) . '</p>'
        . ($quan ? '<p class="nota">' . h(t('avis_resp.acceptat', data_llegible(substr($quan, 0, 10)), $qui)) . '</p>' : '') . '</div>';
}

function accio_accepta_avis(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !config('avis_responsabilitat')) {
        desa_config('avis_responsabilitat', date('Y-m-d H:i') . '|' . usuari()['usuari']);
        audita('avis_responsabilitat');
    }
    redirigeix('inici');
}
