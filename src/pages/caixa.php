<?php
// Llibre de caixa: moviments apuntats a mà i, sense apuntar-los, els rebuts cobrats, les remeses i la loteria liquidada
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const CATEGORIA_REBUT = ['quota_soci' => 'quotes', 'matricula' => 'escola', 'classe' => 'escola', 'loteria' => 'loteria', 'altres' => 'altres_moviments'];

/** Tots els moviments d'un any: [data, concepte, categoria, compte, import (+/-), enllaç] */
function moviments_caixa(int $any): array
{
    $ini = "$any-01-01";
    $fi = "$any-12-31";
    $m = [];
    foreach (q('SELECT * FROM moviments WHERE data BETWEEN ? AND ?', [$ini, $fi])->fetchAll() as $x) {
        $m[] = [$x['data'], $x['concepte'], $x['categoria'], $x['compte'], ($x['tipus'] === 'ingres' ? 1 : -1) * (float)$x['import'], url('fitxa', ['e' => 'moviments', 'id' => $x['id']]), $x['justificant']];
    }
    // Rebuts cobrats en mà o per transferència (els domiciliats en una remesa cobrada van junts, en la remesa)
    foreach (q("SELECT r.*, " . NOM_PERSONA_SQL . " AS pagador FROM rebuts r JOIN persones p ON p.id = r.persona_id
        WHERE r.estat = 'pagat' AND r.data_pagament BETWEEN ? AND ? AND (r.forma_pagament <> 'domiciliacio'
            OR r.remesa_id IS NULL OR r.remesa_id NOT IN (SELECT id FROM remeses WHERE estat = 'cobrada'))", [$ini, $fi])->fetchAll() as $r) {
        $m[] = [$r['data_pagament'], t('caixa.rebut', $r['numero'], $r['pagador'], $r['concepte']), CATEGORIA_REBUT[$r['tipus']] ?? 'altres_moviments',
            $r['forma_pagament'] === 'efectiu' ? 'caixa' : 'banc', (float)$r['import'], url('fitxa', ['e' => 'rebuts', 'id' => $r['id']]), $r['numero']];
    }
    // Remeses cobrades: una línia per remesa, com apareix al banc (sense els rebuts retornats)
    foreach (q("SELECT re.id, re.referencia, re.data_cobrament, COUNT(r.id) AS n, SUM(r.import) AS s FROM remeses re
        JOIN rebuts r ON r.remesa_id = re.id AND r.estat = 'pagat' AND r.forma_pagament = 'domiciliacio'
        WHERE re.estat = 'cobrada' AND re.data_cobrament BETWEEN ? AND ? GROUP BY re.id", [$ini, $fi])->fetchAll() as $re) {
        $m[] = [$re['data_cobrament'], t('caixa.remesa', $re['referencia'], $re['n']), 'quotes', 'banc', (float)$re['s'], url('remesa', ['id' => $re['id']]), $re['referencia']];
    }
    // Loteria: diners lliurats de cada talonari
    foreach (q("SELECT t.*, lo.nom AS loteria, " . NOM_PERSONA_SQL . " AS persona FROM talonaris t JOIN loteries lo ON lo.id = t.loteria_id
        LEFT JOIN persones p ON p.id = t.persona_id WHERE t.import_liquidat > 0 AND COALESCE(t.data_liquidacio, t.data_entrega) BETWEEN ? AND ?", [$ini, $fi])->fetchAll() as $t) {
        $m[] = [$t['data_liquidacio'] ?: $t['data_entrega'], t('caixa.talonari', $t['loteria'], $t['numero'], $t['persona'] ?? ''), 'loteria', 'caixa', (float)$t['import_liquidat'],
            url('fitxa', ['e' => 'talonaris', 'id' => $t['id']]), null];
    }
    usort($m, fn($a, $b) => strcmp($a[0], $b[0]));
    return $m;
}

function pagina_caixa(): void
{
    requereix('economia.view');
    $any = (int)(get('any') ?: date('Y'));
    $compte = in_array(get('compte'), ['banc', 'caixa'], true) ? get('compte') : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('economia.edit');
        foreach (['banc', 'caixa'] as $c) {
            $v = str_replace(',', '.', trim((string)post("saldo_$c")));
            desa_config("caixa_saldo_{$any}_$c", is_numeric($v) ? (string)(float)$v : null);
        }
        avis(t('desat'));
        redirigeix('caixa', ['any' => $any]);
    }

    $saldoInicial = ['banc' => (float)config("caixa_saldo_{$any}_banc"), 'caixa' => (float)config("caixa_saldo_{$any}_caixa")];
    $tots = moviments_caixa($any);
    $movs = $compte ? array_values(array_filter($tots, fn($x) => $x[3] === $compte)) : $tots;

    if (get('csv') === '1') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="llibre-caixa-' . $any . ($compte ? "-$compte" : '') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [t('camp.data'), t('camp.concepte'), t('camp.categoria'), t('camp.compte'), t('camp.justificant'), t('caixa.ingres'), t('caixa.despesa')], ';', '"', '');
        foreach ($movs as [$d, $c, $cat, $cmp, $imp, , $just]) {
            fputcsv($out, [data_llegible($d), $c, t('opt.' . $cat), t('opt.' . $cmp), $just, $imp > 0 ? number_format($imp, 2, ',', '') : '', $imp < 0 ? number_format(-$imp, 2, ',', '') : ''], ';', '"', '');
        }
        audita('exporta', 'caixa', null, (string)$any);
        exit;
    }

    $saldo = $compte ? $saldoInicial[$compte] : array_sum($saldoInicial);
    $ingressos = $despeses = 0.0;
    $perCategoria = [];
    $files = '<tr class="saldo-inicial"><td>' . h(data_llegible("$any-01-01")) . '</td><td colspan="3">' . h(t('caixa.saldo_inicial')) . '</td><td></td><td></td><td class="num">' . h(euros($saldo)) . '</td></tr>';
    foreach ($movs as [$d, $c, $cat, $cmp, $imp, $enllac]) {
        $saldo += $imp;
        $imp > 0 ? $ingressos += $imp : $despeses -= $imp;
        $perCategoria[$cat][$imp > 0 ? 0 : 1] = ($perCategoria[$cat][$imp > 0 ? 0 : 1] ?? 0) + abs($imp);
        $files .= '<tr data-href="' . h($enllac) . '"><td>' . h(data_llegible($d)) . '</td><td><a href="' . h($enllac) . '">' . h($c) . '</a></td><td>' . h(t('opt.' . $cat)) . '</td><td>' . h(t('opt.' . $cmp))
            . '</td><td class="num">' . ($imp > 0 ? h(euros($imp)) : '') . '</td><td class="num">' . ($imp < 0 ? h(euros(-$imp)) : '') . '</td><td class="num' . ($saldo < 0 ? ' dif-neg' : '') . '">' . h(euros($saldo)) . '</td></tr>';
    }

    $anys = '';
    for ($a = (int)date('Y') - 3; $a <= (int)date('Y'); $a++) {
        $anys .= '<option' . ($a === $any ? ' selected' : '') . '>' . $a . '</option>';
    }
    $comptes = '<option value="">' . h(t('caixa.tots_comptes')) . '</option>';
    foreach (['banc', 'caixa'] as $c) {
        $comptes .= '<option value="' . $c . '"' . ($c === $compte ? ' selected' : '') . '>' . h(t('opt.' . $c)) . '</option>';
    }
    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="caixa"><select name="any" onchange="this.form.submit()">' . $anys . '</select>'
        . '<select name="compte" onchange="this.form.submit()">' . $comptes . '</select><span class="espai"></span>'
        . '<a class="boto secundari" href="' . h(url('caixa', ['any' => $any, 'compte' => $compte, 'csv' => 1])) . '">' . h(t('exporta_csv')) . '</a>'
        . '<button type="button" class="secundari" onclick="window.print()">🖨 ' . h(t('llist.imprimeix')) . '</button>'
        . (pot('economia.edit') ? '<a class="boto" href="' . h(url('edita', ['e' => 'moviments', 'tornar' => url('caixa', ['any' => $any])])) . '">+ ' . h(t('caixa.nou')) . '</a>' : '') . '</form>';
    $html .= '<div class="targetes">' . targeta(euros($ingressos), t('caixa.ingressos', $any)) . targeta(euros($despeses), t('caixa.despeses', $any))
        . targeta(euros($ingressos - $despeses), t('caixa.resultat')) . targeta(euros($saldo), t('caixa.saldo_final')) . '</div>';
    $html .= '<div class="taula-wrap"><table class="taula taula-informe"><thead><tr><th>' . h(t('camp.data')) . '</th><th>' . h(t('camp.concepte')) . '</th><th>' . h(t('camp.categoria')) . '</th><th>'
        . h(t('camp.compte')) . '</th><th class="num">' . h(t('caixa.ingres')) . '</th><th class="num">' . h(t('caixa.despesa')) . '</th><th class="num">' . h(t('caixa.saldo')) . '</th></tr></thead><tbody>' . $files
        . '</tbody><tfoot><tr><td colspan="4">' . h(t('total')) . '</td><td class="num">' . h(euros($ingressos)) . '</td><td class="num">' . h(euros($despeses)) . '</td><td class="num">' . h(euros($saldo)) . '</td></tr></tfoot></table></div>';

    // Resum per categories, per a la memòria i l'assemblea
    if ($perCategoria) {
        ksort($perCategoria);
        $html .= '<section class="fill"><header><h2>' . h(t('caixa.per_categoria')) . '</h2></header><div class="taula-wrap"><table class="taula taula-informe"><thead><tr><th>' . h(t('camp.categoria'))
            . '</th><th class="num">' . h(t('caixa.ingressos_col')) . '</th><th class="num">' . h(t('caixa.despeses_col')) . '</th></tr></thead><tbody>';
        foreach ($perCategoria as $cat => $v) {
            $html .= '<tr><td>' . h(t('opt.' . $cat)) . '</td><td class="num">' . h(euros($v[0] ?? 0)) . '</td><td class="num">' . h(euros($v[1] ?? 0)) . '</td></tr>';
        }
        $html .= '</tbody></table></div></section>';
    }

    if (pot('economia.edit')) {
        $html .= '<form method="post" class="formulari no-imprimir">' . csrf_camp() . '<fieldset class="camp ample"><legend>' . h(t('caixa.saldos', $any)) . '</legend><small>' . h(t('caixa.saldos_ajuda')) . '</small><div class="camps">'
            . '<div class="camp"><label>' . h(t('opt.banc')) . '</label><input name="saldo_banc" inputmode="decimal" value="' . h(config("caixa_saldo_{$any}_banc")) . '"></div>'
            . '<div class="camp"><label>' . h(t('opt.caixa')) . '</label><input name="saldo_caixa" inputmode="decimal" value="' . h(config("caixa_saldo_{$any}_caixa")) . '"></div></div></fieldset>'
            . '<div class="accions"><button>' . h(t('desa')) . '</button></div></form>';
    }
    $html .= '<p class="nota">' . h(t('caixa.nota')) . '</p>';
    pagina(t('menu.caixa') . ' ' . $any, $html);
}
