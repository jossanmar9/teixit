<?php
// Loteria: talonaris de participacions repartits, venuts, tornats i liquidats
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Primera i última participació d'un talonari */
function rang_talonari(array $loteria, int $numero): array
{
    $mida = (int)$loteria['participacions_talonari'];
    return [($numero - 1) * $mida + 1, $numero * $mida];
}

/** Quant ha de liquidar un talonari (venudes × preu) */
function deute_talonari(array $t, array $loteria): float
{
    return round((int)$t['venudes'] * (float)$loteria['preu'], 2);
}

/** Avís al llistat: talonari entregat sense liquidar o amb diners pendents */
function avis_talonari(array $t): ?string
{
    $lo = fila('SELECT * FROM loteries WHERE id = ?', [$t['loteria_id']]);
    if (!$lo || !$t['persona_id']) {
        return null;
    }
    if ($t['venudes'] === null && $t['tornades'] === null) {
        return t('loteria.avis_sense_liquidar');
    }
    $falta = deute_talonari($t, $lo) - (float)$t['import_liquidat'];
    return $falta > 0.004 ? t('loteria.avis_falta', euros($falta)) : null;
}

function pagina_loteria(): void
{
    requereix('economia.view');
    $lo = fila('SELECT * FROM loteries WHERE id = ?', [(int)get('id')]);
    if (!$lo) {
        redirigeix('llista', ['e' => 'loteries']);
    }
    $id = (int)$lo['id'];
    $mida = (int)$lo['participacions_talonari'];
    $error = null;

    // Repartir talonaris nous a una persona
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requereix('economia.edit');
        $persona = (int)post('persona_id');
        $quants = (int)post('quants');
        if (!$persona || !valor('SELECT id FROM persones WHERE id = ?', [$persona])) {
            $error = t('err.obligatori', t('camp.persona_id'));
        } elseif ($quants < 1 || $quants > 100) {
            $error = t('loteria.err_quants');
        } else {
            $seguent = (int)valor('SELECT COALESCE(MAX(numero), 0) FROM talonaris WHERE loteria_id = ?', [$id]) + 1;
            db()->beginTransaction();
            for ($i = 0; $i < $quants; $i++) {
                q('INSERT INTO talonaris(loteria_id, numero, persona_id, data_entrega) VALUES(?, ?, ?, ?)', [$id, $seguent + $i, $persona, date('Y-m-d')]);
            }
            audita('reparteix_talonaris', 'loteries', $id, "$quants → $persona");
            db()->commit();
            avis(t('loteria.repartits', $quants, $seguent, $seguent + $quants - 1));
            redirigeix('loteria', ['id' => $id]);
        }
    }

    $talonaris = q("SELECT t.*, " . NOM_PERSONA_SQL . " AS persona FROM talonaris t LEFT JOIN persones p ON p.id = t.persona_id WHERE t.loteria_id = ? ORDER BY t.numero", [$id])->fetchAll();
    $tot = ['venudes' => 0, 'tornades' => 0, 'deute' => 0.0, 'liquidat' => 0.0, 'repartides' => 0];
    $files = '';
    foreach ($talonaris as $t) {
        [$de, $fins] = rang_talonari($lo, (int)$t['numero']);
        $deute = deute_talonari($t, $lo);
        $dif = (float)$t['import_liquidat'] - $deute;
        $pendents = $mida - (int)$t['venudes'] - (int)$t['tornades'];
        $tot['venudes'] += (int)$t['venudes'];
        $tot['tornades'] += (int)$t['tornades'];
        $tot['deute'] += $deute;
        $tot['liquidat'] += (float)$t['import_liquidat'];
        $tot['repartides'] += $t['persona_id'] ? $mida : 0;
        $u = url('fitxa', ['e' => 'talonaris', 'id' => $t['id']]);
        $files .= '<tr data-href="' . h($u) . '"><td><a href="' . h($u) . '">' . (int)$t['numero'] . '</a></td><td>' . sprintf('%04d–%04d', $de, $fins) . '</td><td>' . h($t['persona'] ?? '—') . '</td>'
            . '<td class="num">' . h((string)($t['venudes'] ?? '')) . '</td><td class="num">' . h((string)($t['tornades'] ?? '')) . '</td><td class="num">' . ($t['venudes'] === null && $t['tornades'] === null ? '' : $pendents)
            . '</td><td class="num">' . h(euros($deute)) . '</td><td class="num">' . h($t['import_liquidat'] === null ? '' : euros($t['import_liquidat'])) . '</td><td class="num' . ($dif < -0.004 ? ' dif-neg' : '') . '">'
            . ($t['import_liquidat'] === null && !$t['venudes'] ? '' : h(euros($dif))) . '</td></tr>';
    }
    $benefici = $tot['venudes'] * ((float)$lo['preu'] - (float)$lo['juga']);

    $html = $error ? '<div class="avis error">' . h($error) . '</div>' : '';
    $html .= '<div class="accions"><a class="boto secundari" href="' . h(url('fitxa', ['e' => 'loteries', 'id' => $id])) . '">' . h(t('loteria.fitxa')) . '</a>'
        . ($talonaris ? '<a class="boto secundari" target="_blank" href="' . h(url('participacions', ['loteria' => $id])) . '">🖨 ' . h(t('loteria.imprimeix_totes')) . '</a>' : '') . '</div>';
    $html .= '<div class="targetes">'
        . targeta((string)count($talonaris), t('loteria.talonaris', $tot['repartides']))
        . targeta((string)$tot['venudes'], t('loteria.venudes', $tot['tornades']))
        . targeta(euros($tot['liquidat']), t('loteria.liquidat', euros($tot['deute'])))
        . targeta(euros($benefici), t('loteria.benefici', euros((float)$lo['preu'] - (float)$lo['juga'])))
        . '</div>';
    $html .= '<p>' . h(t('loteria.descripcio', $lo['numeros'], euros($lo['preu']), euros($lo['juga']), $mida, data_llegible($lo['data_sorteig']) ?: '—', euros($tot['venudes'] * (float)$lo['juga']))) . '</p>';

    if (pot('economia.edit')) {
        $ops = '';
        foreach (opcions_ref('persones') as $pid => $nom) {
            $ops .= '<option value="' . (int)$pid . '">' . h($nom) . '</option>';
        }
        $html .= '<form method="post" class="formulari en-fila">' . csrf_camp() . '<div class="camps">'
            . '<div class="camp"><label>' . h(t('loteria.a_qui')) . '</label><select name="persona_id" data-cerca="1" required><option value="">—</option>' . $ops . '</select></div>'
            . '<div class="camp"><label>' . h(t('loteria.quants')) . '</label><input type="number" name="quants" min="1" max="100" value="1" required></div></div>'
            . '<div class="accions"><button>🎟 ' . h(t('loteria.reparteix')) . '</button></div><small>' . h(t('loteria.reparteix_ajuda')) . '</small></form>';
    }

    if (!$talonaris) {
        $html .= '<p class="buit">' . h(t('loteria.sense_talonaris')) . '</p>';
    } else {
        $html .= '<div class="taula-wrap"><table class="taula loteria-resum"><thead><tr><th>' . h(t('loteria.col_talonari')) . '</th><th>' . h(t('loteria.col_participacions')) . '</th><th>'
            . h(t('loteria.col_persona')) . '</th><th class="num">' . h(t('camp.venudes')) . '</th><th class="num">' . h(t('camp.tornades')) . '</th><th class="num">' . h(t('loteria.col_pendents'))
            . '</th><th class="num">' . h(t('loteria.col_deute')) . '</th><th class="num">' . h(t('camp.import_liquidat')) . '</th><th class="num">' . h(t('loteria.col_diferencia')) . '</th></tr></thead><tbody>'
            . $files . '</tbody><tfoot><tr><td colspan="3">' . h(t('total')) . '</td><td class="num">' . $tot['venudes'] . '</td><td class="num">' . $tot['tornades'] . '</td><td></td><td class="num">'
            . h(euros($tot['deute'])) . '</td><td class="num">' . h(euros($tot['liquidat'])) . '</td><td class="num">' . h(euros($tot['liquidat'] - $tot['deute'])) . '</td></tr></tfoot></table></div>';
    }
    $html .= '<p class="nota">' . h(t('loteria.nota')) . '</p>';
    pagina($lo['nom'], $html, t('ent1.loteries'));
}

/** Participacions per a imprimir: les d'un talonari o les de tots els talonaris d'un sorteig */
function pagina_participacions(): void
{
    requereix('economia.view');
    if (get('talonari') !== '') {
        $talonaris = q('SELECT * FROM talonaris WHERE id = ?', [(int)get('talonari')])->fetchAll();
    } else {
        $talonaris = q('SELECT * FROM talonaris WHERE loteria_id = ? ORDER BY numero', [(int)get('loteria')])->fetchAll();
    }
    $lo = $talonaris ? fila('SELECT * FROM loteries WHERE id = ?', [$talonaris[0]['loteria_id']]) : null;
    $participacions = [];
    foreach ($talonaris as $t) {
        [$de, $fins] = rang_talonari($lo, (int)$t['numero']);
        for ($n = $de; $n <= $fins; $n++) {
            $participacions[] = [$n, (int)$t['numero']];
        }
    }
    document_inici(t('loteria.participacions'), t('loteria.n_participacions', count($participacions)), t('loteria.consell'));
    if (!$lo) {
        echo '<p class="buit">' . h(t('cap_resultat')) . '</p>';
        document_fi();
        return;
    }
    $societat = config('societat_nom') ?: 'Teixit';
    $logo = url_logo();
    $donatiu = (float)$lo['preu'] - (float)$lo['juga'];
    $sorteig = t('loteria.sorteig_del', $lo['nom'], $lo['data_sorteig'] ? data_llegible($lo['data_sorteig']) : '');
    foreach (array_chunk($participacions, 8) as $full) {
        echo '<section class="full full-participacions">';
        foreach ($full as [$n, $talonari]) {
            echo '<div class="participacio"><div class="p-cap">' . ($logo ? '<img src="' . h($logo) . '" alt="">' : '') . '<b>' . h($societat) . '</b></div>'
                . '<div class="p-sorteig">' . h($sorteig) . '</div>'
                . '<div class="p-numeros">' . h($lo['numeros']) . '</div>'
                . '<div class="p-imports"><span>' . h(t('loteria.p_juga')) . ' <b>' . h(euros($lo['juga'])) . '</b></span><span>' . h(t('loteria.p_donatiu')) . ' <b>' . h(euros($donatiu)) . '</b></span><span>'
                . h(t('loteria.p_preu')) . ' <b>' . h(euros($lo['preu'])) . '</b></span></div>'
                . '<div class="p-num">' . h(t('loteria.p_num', sprintf('%04d', $n))) . ' <small>(' . h(t('loteria.p_talonari', $talonari)) . ')</small></div>'
                . '<div class="p-peu">' . nl2br(h($lo['peu'] ?: t('loteria.peu_defecte'))) . '</div></div>';
        }
        echo '</section>';
    }
    audita('imprimeix_participacions', 'loteries', $lo['id'], (string)count($participacions));
    document_fi();
}
