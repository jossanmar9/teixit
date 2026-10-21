<?php
// Traduccions: valencià (ca) i castellà (es)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const IDIOMES = ['ca' => 'Valencià', 'es' => 'Castellano'];

/** Etiqueta curta per al selector d'idioma */
const IDIOMES_CURT = ['ca' => 'VAL', 'es' => 'ES'];

function idioma(): string
{
    static $cache = null;
    if ($cache) {
        return $cache;
    }
    $l = $_SESSION['idioma'] ?? null;
    if (!$l && function_exists('usuari') && session_status() === PHP_SESSION_ACTIVE) {
        $u = usuari();
        $l = $u['idioma'] ?? null;
    }
    if (!$l) {
        try {
            $l = config('idioma');
        } catch (Throwable $e) {
            $l = null;
        }
    }
    return $cache = isset(IDIOMES[$l]) ? $l : 'ca';
}

function t(string $clau, ...$args): string
{
    static $textos = [];
    $l = idioma();
    if (!isset($textos[$l])) {
        $textos[$l] = require SRC_DIR . "/lang/$l.php";
        if ($l !== 'ca') {
            $textos[$l] += require SRC_DIR . '/lang/ca.php';
        }
    }
    // Primer el text propi del tipus d'entitat («ent.agrupacions@club» → «Equips»), si n'hi ha
    $s = $textos[$l][$clau . '@' . tipus_entitat()] ?? $textos[$l][$clau] ?? $clau;
    return $args ? vsprintf($s, $args) : $s;
}
