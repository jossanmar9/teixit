<?php
// Tipus d'entitat: associació cultural, falla (o gaiata o foguera), club esportiu o AMPA/AFA.
// El tipus canvia el vocabulari (claus «text@tipus» de lang/), els mòduls actius per defecte,
// els tipus de soci inicials i alguns camps (per exemple, la llicència federativa dels clubs).
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const TIPUS_ENTITAT = ['cultural', 'falla', 'club', 'ampa'];

/** Mòduls que es desactiven en la configuració inicial segons el tipus (després cada entitat els canvia) */
const MODULS_DESACTIVATS_TIPUS = [
    'cultural' => ['uniformes', 'loteria'],
    'falla' => ['escola', 'qualificacions'],
    'club' => ['escola', 'qualificacions'],
    'ampa' => ['qualificacions', 'instruments', 'uniformes'],
];

/** Tipus de soci inicials: [valencià, castellà, va al full d'alta] */
const TIPUS_SOCI_TIPUS = [
    'cultural' => [['Ordinari', 'Ordinario', 1], ['Juvenil', 'Juvenil', 1], ['Protector', 'Protector', 1], ['D\'honor', 'De honor', 0]],
    'falla' => [['Adult', 'Adulto', 1], ['Juvenil', 'Juvenil', 1], ['Infantil', 'Infantil', 1], ['D\'honor', 'De honor', 0]],
    'club' => [['Ordinari', 'Ordinario', 1], ['Esportista', 'Deportista', 1], ['Protector', 'Protector', 1], ['D\'honor', 'De honor', 0]],
    'ampa' => [['Família', 'Familia', 1], ['Col·laborador', 'Colaborador', 1]],
];

/** Tipus de l'entitat, guardat a la configuració (si encara no n'hi ha, «cultural») */
function tipus_entitat(): string
{
    if (isset($GLOBALS['tipus_entitat'])) {
        return $GLOBALS['tipus_entitat'];
    }
    try {
        $t = config('tipus_entitat');
    } catch (Throwable $e) {
        return 'cultural';
    }
    return $GLOBALS['tipus_entitat'] = in_array($t, TIPUS_ENTITAT, true) ? $t : 'cultural';
}

/** Desa el tipus d'entitat. Amb $moduls, posa també els mòduls per defecte d'eixe tipus. */
function desa_tipus_entitat(string $tipus, bool $moduls): void
{
    if (!in_array($tipus, TIPUS_ENTITAT, true)) {
        $tipus = 'cultural';
    }
    desa_config('tipus_entitat', $tipus);
    $GLOBALS['tipus_entitat'] = $tipus;
    if ($moduls) {
        desa_config('moduls_desactivats', implode(',', MODULS_DESACTIVATS_TIPUS[$tipus]) ?: null);
    }
}

/** Els clubs porten la llicència federativa dels esportistes */
function amb_llicencies(): bool
{
    return tipus_entitat() === 'club';
}
