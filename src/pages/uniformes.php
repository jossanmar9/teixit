<?php
// Uniformes i marxandatge: talles, compres, lliuraments (venda, préstec o gratuït) i existències
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** «s, M ,l» → ['S', 'M', 'L'] (sense repetides i en l'ordre escrit) */
function talles_text(?string $t): array
{
    $talles = [];
    foreach (preg_split('/[,;\/]+/', (string)$t) as $x) {
        $x = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $x)));
        if ($x !== '' && !in_array($x, $talles, true)) {
            $talles[] = $x;
        }
    }
    return $talles;
}

/** Comprova la talla d'una compra o d'un lliurament i que la quantitat siga positiva */
function valida_talla(array $d): ?string
{
    if ((int)($d['quantitat'] ?? 0) <= 0) {
        return t('err.quantitat');
    }
    $talles = talles_text(valor('SELECT talles FROM uniformes WHERE id = ?', [$d['uniforme_id']]));
    if (!$talles) {
        return null;
    }
    if (($d['talla'] ?? null) === null) {
        return t('err.tria_talla', implode(', ', $talles));
    }
    return in_array($d['talla'], $talles, true) ? null : t('err.talla', $d['talla'], implode(', ', $talles));
}

/**
 * Existències d'un article per talla: talla => [total, en préstec, disponibles].
 * Total: les que té la societat (comprades menys les venudes i les gratuïtes). Disponibles: les que no estan en préstec.
 */
function existencies(int $uniformeId): array
{
    $u = fila('SELECT talles FROM uniformes WHERE id = ?', [$uniformeId]);
    $files = [];
    foreach (talles_text($u['talles'] ?? null) as $t) {
        $files[$t] = [0, 0, 0];
    }
    foreach (q("SELECT COALESCE(talla, '') AS talla, SUM(quantitat) AS n FROM uniforme_compres WHERE uniforme_id = ? GROUP BY 1", [$uniformeId])->fetchAll() as $r) {
        $files[$r['talla']] ??= [0, 0, 0];
        $files[$r['talla']][0] += (int)$r['n'];
    }
    foreach (q("SELECT COALESCE(talla, '') AS talla, SUM(CASE WHEN tipus <> 'prestec' THEN quantitat ELSE 0 END) AS fora,
            SUM(CASE WHEN tipus = 'prestec' AND data_retorn IS NULL THEN quantitat ELSE 0 END) AS prestades
            FROM uniforme_lliuraments WHERE uniforme_id = ? GROUP BY 1", [$uniformeId])->fetchAll() as $r) {
        $files[$r['talla']] ??= [0, 0, 0];
        $files[$r['talla']][0] -= (int)$r['fora'];
        $files[$r['talla']][1] += (int)$r['prestades'];
    }
    foreach ($files as &$f) {
        $f[2] = $f[0] - $f[1];
    }
    return $files;
}

/** Taula d'existències d'un article */
function taula_existencies(int $uniformeId): string
{
    $files = existencies($uniformeId);
    if (!$files) {
        return '<p class="buit petit">' . h(t('unif.sense_moviments')) . '</p>';
    }
    $html = '<div class="taula-wrap"><table class="taula taula-informe existencies"><thead><tr><th>' . h(t('camp.talla')) . '</th><th class="num">' . h(t('unif.total'))
        . '</th><th class="num">' . h(t('unif.en_prestec')) . '</th><th class="num">' . h(t('unif.disponibles')) . '</th></tr></thead><tbody>';
    $tot = [0, 0, 0];
    foreach ($files as $talla => $f) {
        $html .= '<tr><td>' . h($talla !== '' ? (string)$talla : t('unif.sense_talla')) . '</td>';
        foreach ($f as $i => $n) {
            $tot[$i] += $n;
            $html .= '<td class="num' . ($n < 0 ? ' dif-neg' : ($i === 2 ? ($n === 0 ? ' esgotat' : ' queden') : '')) . '">' . $n . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody><tfoot><tr><td>' . h(t('total')) . '</td>' . implode('', array_map(fn($n) => '<td class="num">' . $n . '</td>', $tot)) . '</tr></tfoot></table></div>';
    return $html;
}

/** Secció de la fitxa de l'article */
function seccio_existencies(array $f): string
{
    $botons = '';
    if (pot('instruments.edit')) {
        $tornar = url('fitxa', ['e' => 'uniformes', 'id' => $f['id']]);
        $botons = '<a class="boto petit" href="' . h(url('edita', ['e' => 'uniforme_compres', 'f[uniforme_id]' => $f['id'], 'tornar' => $tornar])) . '">+ ' . h(t('unif.nova_compra')) . '</a>'
            . '<a class="boto petit secundari" href="' . h(url('edita', ['e' => 'uniforme_lliuraments', 'f[uniforme_id]' => $f['id'], 'tornar' => $tornar])) . '">+ ' . h(t('unif.nou_lliurament')) . '</a>';
    }
    return '<section class="fill"><header><h2>' . h(t('unif.existencies')) . '</h2>' . $botons . '</header>' . taula_existencies((int)$f['id']) . '</section>';
}

/** Avís en un lliurament si la talla s'ha quedat sense existències (falta apuntar alguna compra) */
function avis_lliurament(array $f): ?string
{
    $e = existencies((int)$f['uniforme_id'])[$f['talla'] ?? ''] ?? null;
    return $e && $e[2] < 0 ? t('unif.avis_negatiu') : null;
}

/** Pantalla d'uniformes: existències de cada article */
function pagina_uniformes(): void
{
    requereix('instruments.view');
    $articles = q("SELECT * FROM uniformes WHERE estat = 'actiu' ORDER BY tipus, nom")->fetchAll();
    $unitats = 0;
    foreach ($articles as $a) {
        $unitats += array_sum(array_map(fn($x) => max(0, $x[2]), existencies((int)$a['id'])));
    }
    $prestecs = (int)valor("SELECT COALESCE(SUM(quantitat), 0) FROM uniforme_lliuraments WHERE tipus = 'prestec' AND data_retorn IS NULL");
    $venut = (float)valor("SELECT COALESCE(SUM(import), 0) FROM uniforme_lliuraments WHERE strftime('%Y', data) = strftime('%Y', 'now', 'localtime')");

    $html = '<div class="barra">';
    if (pot('instruments.edit')) {
        $tornar = url('uniformes');
        $html .= '<a class="boto" href="' . h(url('edita', ['e' => 'uniforme_lliuraments', 'tornar' => $tornar])) . '">+ ' . h(t('unif.nou_lliurament')) . '</a>'
            . '<a class="boto secundari" href="' . h(url('edita', ['e' => 'uniforme_compres', 'tornar' => $tornar])) . '">+ ' . h(t('unif.nova_compra')) . '</a>'
            . '<a class="boto secundari" href="' . h(url('edita', ['e' => 'uniformes'])) . '">+ ' . h(t('unif.nou_article')) . '</a>';
    }
    $html .= '<span class="espai"></span><a class="boto secundari" href="' . h(url('llista', ['e' => 'uniformes'])) . '">' . h(t('ent.uniformes')) . '</a>'
        . '<a class="boto secundari" href="' . h(url('llista', ['e' => 'uniforme_compres'])) . '">' . h(t('ent.uniforme_compres')) . '</a>'
        . '<a class="boto secundari" href="' . h(url('llista', ['e' => 'uniforme_lliuraments'])) . '">' . h(t('ent.uniforme_lliuraments')) . '</a>'
        . '<button type="button" class="secundari" onclick="window.print()">🖨 ' . h(t('llist.imprimeix')) . '</button></div>';
    $html .= '<div class="targetes">' . targeta((string)count($articles), t('unif.articles')) . targeta((string)$unitats, t('unif.unitats'))
        . targeta((string)$prestecs, t('unif.prestades'), url('llista', ['e' => 'uniforme_lliuraments', 'filtre' => 'prestecs']))
        . targeta(euros($venut), t('unif.cobrat_any', date('Y'))) . '</div>';
    if (!$articles) {
        $html .= '<p class="buit">' . h(t('unif.cap_article')) . '</p>';
    }
    foreach ($articles as $a) {
        $html .= '<section class="fill"><header><h2><a href="' . h(url('fitxa', ['e' => 'uniformes', 'id' => $a['id']])) . '">' . h($a['nom']) . '</a>'
            . (mb_strtolower($a['nom']) !== mb_strtolower(t('opt.' . $a['tipus'])) ? ' <span class="etiqueta">' . h(t('opt.' . $a['tipus'])) . '</span>' : '') . ($a['preu'] !== null ? ' <span class="etiqueta">' . h(euros((float)$a['preu'])) . '</span>' : '') . '</h2></header>'
            . taula_existencies((int)$a['id']) . '</section>';
    }
    $html .= '<p class="nota">' . h(t('unif.nota')) . '</p>';
    pagina(t('menu.uniformes'), $html);
}
