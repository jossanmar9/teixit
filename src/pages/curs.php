<?php
// Assistent per a passar de curs: crea el curs nou a partir de l'anterior
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** «2026/2027» → «2027/2028»; «2026-27» → «2027-28»; si no, el mateix nom amb « (nou)» */
function nom_curs_seguent(string $nom): string
{
    if (preg_match('/^(.*?)(\d{4})([\/\-–])(\d{2,4})(.*)$/u', $nom, $m)) {
        $b = strlen($m[4]) === 2 ? sprintf('%02d', ((int)$m[4] + 1) % 100) : (string)((int)$m[4] + 1);
        return $m[1] . ((int)$m[2] + 1) . $m[3] . $b . $m[5];
    }
    if (preg_match('/^(.*?)(\d{4})(.*)$/u', $nom, $m)) {
        return $m[1] . ((int)$m[2] + 1) . $m[3];
    }
    return $nom . ' (2)';
}

function un_any_mes(?string $data): ?string
{
    return $data ? date('Y-m-d', strtotime($data . ' +1 year')) : null;
}

function pagina_passa_curs(): void
{
    requereix('escola.edit');
    $vell = fila('SELECT * FROM cursos WHERE id = ?', [(int)get('curs')]);
    if (!$vell) {
        redirigeix('llista', ['e' => 'cursos']);
    }
    $n = [
        'periodes' => (int)valor('SELECT COUNT(*) FROM periodes WHERE curs_id = ?', [$vell['id']]),
        'grups' => (int)valor("SELECT COUNT(*) FROM grups WHERE curs_id = ? AND estat = 'actiu'", [$vell['id']]),
        'horaris' => (int)valor("SELECT COUNT(*) FROM horaris h JOIN grups g ON g.id = h.grup_id WHERE g.curs_id = ? AND g.estat = 'actiu'", [$vell['id']]),
        'matricules' => (int)valor("SELECT COUNT(*) FROM matricules m JOIN alumnes al ON al.id = m.alumne_id WHERE m.curs_id = ? AND m.estat = 'actiu' AND al.estat = 'actiu'", [$vell['id']]),
    ];
    $error = null;
    $marcat = fn(string $c, bool $def) => $_SERVER['REQUEST_METHOD'] === 'POST' ? post($c) === '1' : $def;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = trim((string)post('nom'));
        $inici = normalitza(['tipus' => 'date'], post('data_inici'));
        $fi = normalitza(['tipus' => 'date'], post('data_fi'));
        if ($nom === '') {
            $error = t('err.obligatori', t('camp.nom'));
        } elseif (valor('SELECT COUNT(*) FROM cursos WHERE nom = ?', [$nom])) {
            $error = t('passa.err_existeix', $nom);
        } elseif ($inici && $fi && $fi <= $inici) {
            $error = t('err.dates');
        }
        if (!$error) {
            db()->beginTransaction();
            q('INSERT INTO cursos(nom, data_inici, data_fi, estat, actual) VALUES(?, ?, ?, ?, 0)', [$nom, $inici, $fi, 'actiu']);
            $nou = (int)db()->lastInsertId();
            $fet = ['periodes' => 0, 'grups' => 0, 'horaris' => 0, 'matricules' => 0];
            if (post('periodes') === '1') {
                $fet['periodes'] = q('INSERT INTO periodes(curs_id, nom, ordre) SELECT ?, nom, ordre FROM periodes WHERE curs_id = ? ORDER BY ordre', [$nou, $vell['id']])->rowCount();
            }
            if (post('grups') === '1') {
                $ambPersones = post('professors') === '1';
                foreach (q("SELECT * FROM grups WHERE curs_id = ? AND estat = 'actiu' ORDER BY id", [$vell['id']])->fetchAll() as $g) {
                    // Només es copia el professor si encara està d'alta
                    $prof = $ambPersones && $g['professor_id'] && valor("SELECT estat FROM professors WHERE id = ?", [$g['professor_id']]) === 'actiu' ? $g['professor_id'] : null;
                    q('INSERT INTO grups(curs_id, assignatura_id, nom, professor_id, aula_id, estat) VALUES(?, ?, ?, ?, ?, ?)',
                        [$nou, $g['assignatura_id'], $g['nom'], $prof, $ambPersones ? $g['aula_id'] : null, 'actiu']);
                    $gNou = (int)db()->lastInsertId();
                    $fet['grups']++;
                    if (post('horaris') === '1') {
                        $fet['horaris'] += q('INSERT INTO horaris(grup_id, dia, hora_inici, hora_fi) SELECT ?, dia, hora_inici, hora_fi FROM horaris WHERE grup_id = ?', [$gNou, $g['id']])->rowCount();
                    }
                }
            }
            if (post('matricules') === '1') {
                $fet['matricules'] = q("INSERT INTO matricules(alumne_id, curs_id, data, estat) SELECT m.alumne_id, ?, ?, 'actiu' FROM matricules m JOIN alumnes al ON al.id = m.alumne_id
                    WHERE m.curs_id = ? AND m.estat = 'actiu' AND al.estat = 'actiu'", [$nou, $inici ?: date('Y-m-d'), $vell['id']])->rowCount();
            }
            if (post('actual') === '1') {
                q('UPDATE cursos SET actual = (id = ?)', [$nou]);
            }
            if (post('tanca') === '1') {
                q("UPDATE cursos SET estat = 'tancat' WHERE id = ?", [$vell['id']]);
            }
            audita('passa_curs', 'cursos', $nou, $vell['nom'] . ' → ' . $nom . ' · ' . json_encode($fet));
            db()->commit();
            avis(t('passa.fet', $nom, $fet['periodes'], $fet['grups'], $fet['horaris'], $fet['matricules']));
            redirigeix('fitxa', ['e' => 'cursos', 'id' => $nou]);
        }
    }

    $check = fn(string $c, string $text, bool $def, int $quants = -1) => '<label><input type="checkbox" name="' . $c . '" value="1"' . ($marcat($c, $def) ? ' checked' : '') . '> '
        . h($text) . ($quants >= 0 ? ' <span class="compte">' . $quants . '</span>' : '') . '</label>';
    $html = ($error ? '<div class="avis error">' . h($error) . '</div>' : '')
        . '<p>' . h(t('passa.intro', $vell['nom'])) . '</p>'
        . '<form method="post" class="formulari">' . csrf_camp() . '<div class="camps">'
        . '<div class="camp"><label>' . h(t('passa.nom_nou')) . ' <span class="obl">*</span></label><input name="nom" required value="' . h(post('nom', nom_curs_seguent($vell['nom']))) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.data_inici')) . '</label><input type="date" name="data_inici" value="' . h(post('data_inici', (string)un_any_mes($vell['data_inici']))) . '"></div>'
        . '<div class="camp"><label>' . h(t('camp.data_fi')) . '</label><input type="date" name="data_fi" value="' . h(post('data_fi', (string)un_any_mes($vell['data_fi']))) . '"></div>'
        . '<fieldset class="camp ample"><legend>' . h(t('passa.que_copia')) . '</legend><div class="opcions vertical">'
        . $check('periodes', t('passa.periodes'), true, $n['periodes'])
        . $check('grups', t('passa.grups'), true, $n['grups'])
        . $check('professors', t('passa.professors'), true)
        . $check('horaris', t('passa.horaris'), true, $n['horaris'])
        . $check('matricules', t('passa.matricules'), false, $n['matricules'])
        . '</div><small>' . h(t('passa.matricules_ajuda')) . '</small></fieldset>'
        . '<fieldset class="camp ample"><legend>' . h(t('passa.despres')) . '</legend><div class="opcions vertical">'
        . $check('actual', t('passa.actual'), true)
        . $check('tanca', t('passa.tanca', $vell['nom']), false)
        . '</div></fieldset>'
        . '</div><div class="accions"><button>⏭ ' . h(t('passa.crea')) . '</button><a class="boto secundari" href="' . h(url('fitxa', ['e' => 'cursos', 'id' => $vell['id']])) . '">' . h(t('cancel·la')) . '</a></div></form>'
        . '<p class="nota">' . h(t('passa.nota')) . '</p>';
    pagina(t('passa.titol'), $html, $vell['nom']);
}
