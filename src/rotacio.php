<?php
// Quines còpies automàtiques es conserven: totes les dels últims dies, una per setmana i una per mes
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const ROTACIO_DIES = 7;      // totes les còpies d'estos últims dies
const ROTACIO_SETMANES = 5;  // la més nova de cada setmana d'estes últimes setmanes
const ROTACIO_MINIM = 7;     // mai no es deixen menys còpies automàtiques que estes

/** Data i hora d'una còpia a partir del nom: «teixit-20261021-153000-auto.db» → marca de temps */
function data_nom_copia(string $nom): ?int
{
    if (!preg_match('/teixit-(\d{8})-(\d{6})-/', basename($nom), $m)) {
        return null;
    }
    $d = DateTime::createFromFormat('Ymd His', $m[1] . ' ' . $m[2]);
    return $d ? $d->getTimestamp() : null;
}

/**
 * De les còpies automàtiques (noms o rutes), les que s'han de conservar:
 * - totes les dels últims ROTACIO_DIES dies;
 * - la més nova de cada setmana de les últimes ROTACIO_SETMANES setmanes;
 * - la més nova de cada mes dels últims $mesos mesos;
 * - i, en tot cas, les ROTACIO_MINIM més noves.
 */
function copies_a_mantindre(array $copies, int $mesos, ?int $ara = null): array
{
    $ara ??= time();
    $dates = [];
    foreach ($copies as $c) {
        if (($t = data_nom_copia($c)) !== null) {
            $dates[$c] = $t;
        }
    }
    arsort($dates); // de la més nova a la més antiga
    $conserva = [];
    $setmanes = $mesosVists = [];
    $limitSetmanes = strtotime('-' . ROTACIO_SETMANES . ' weeks', $ara);
    $limitMesos = strtotime('-' . $mesos . ' months', $ara);
    $i = 0;
    foreach ($dates as $c => $t) {
        $setmana = date('o-W', $t);
        $mes = date('Y-m', $t);
        if ($i++ < ROTACIO_MINIM || $t >= $ara - ROTACIO_DIES * 86400) {
            $conserva[$c] = true;
        }
        if ($t >= $limitSetmanes && !isset($setmanes[$setmana])) {
            $setmanes[$setmana] = $conserva[$c] = true;
        }
        if ($t >= $limitMesos && !isset($mesosVists[$mes])) {
            $mesosVists[$mes] = $conserva[$c] = true;
        }
    }
    return array_values(array_filter($copies, fn($c) => isset($conserva[$c])));
}
