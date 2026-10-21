<?php
// Escola: introducció de qualificacions
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Grups actius del curs actual */
function grups_permesos(): array
{
    $curs = curs_actual();
    if (!$curs) {
        return [];
    }
    return array_column(q("SELECT g.id, a.nom || ' · ' || g.nom AS e FROM grups g JOIN assignatures a ON a.id = g.assignatura_id
        WHERE g.curs_id = ? AND g.estat = 'actiu' ORDER BY e", [$curs['id']])->fetchAll(), 'e', 'id');
}

function pot_grup(int $grupId): bool
{
    return (bool)valor('SELECT 1 FROM grups WHERE id = ?', [$grupId]);
}

function alumnes_grup(int $grupId): array
{
    return q("SELECT al.id, " . NOM_PERSONA_SQL . " AS nom FROM matricula_grups mg JOIN matricules m ON m.id = mg.matricula_id
        JOIN alumnes al ON al.id = m.alumne_id JOIN persones p ON p.id = al.persona_id
        WHERE mg.grup_id = ? AND m.estat = 'actiu' ORDER BY p.cognoms, p.nom", [$grupId])->fetchAll();
}

function selector_grup(string $ruta, array $grups, int $actual, array $extra = []): string
{
    $html = '<form class="barra" method="get"><input type="hidden" name="r" value="' . h($ruta) . '">'
        . select_simple('grup', $grups, $actual, true, t('tria_grup'));
    foreach ($extra as $k => $v) {
        $html .= $v;
    }
    return $html . '<button>' . h(t('filtrar')) . '</button></form>';
}

function sense_grups(): string
{
    if (!curs_actual()) {
        return '<div class="buit">' . h(t('inici.sense_curs')) . '</div>';
    }
    return '<div class="buit">' . h(t('escola.sense_grups')) . '</div>';
}

function pagina_notes(): void
{
    requereix('escola.notes');
    $grups = grups_permesos();
    $grup = (int)get('grup', '0');
    if ($grup && !pot_grup($grup)) {
        requereix('__cap__');
    }
    if (!$grups && !$grup) {
        pagina(t('menu.notes'), sense_grups());
        return;
    }
    if (!isset($grups[$grup]) && $grup) {
        $grups[$grup] = opcions_ref('grups')[$grup] ?? '#' . $grup;
    }
    $periodes = [];
    $periode = (int)get('periode', '0');
    if ($grup) {
        $cursId = valor('SELECT curs_id FROM grups WHERE id = ?', [$grup]);
        $periodes = array_column(q('SELECT id, nom FROM periodes WHERE curs_id = ? ORDER BY ordre', [$cursId])->fetchAll(), 'nom', 'id');
        if (!isset($periodes[$periode])) {
            $periode = (int)array_key_first($periodes);
        }
    }

    if ($grup && $periode && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $notes = (array)($_POST['nota'] ?? []);
        $obs = (array)($_POST['obs'] ?? []);
        $prof = valor('SELECT professor_id FROM grups WHERE id = ?', [$grup]);
        db()->beginTransaction();
        foreach (alumnes_grup($grup) as $al) {
            $n = mb_substr(trim((string)($notes[$al['id']] ?? '')), 0, 20);
            $o = trim((string)($obs[$al['id']] ?? ''));
            if ($n === '' && $o === '') {
                q('DELETE FROM qualificacions WHERE grup_id = ? AND alumne_id = ? AND periode_id = ?', [$grup, $al['id'], $periode]);
                continue;
            }
            q("INSERT INTO qualificacions(grup_id, alumne_id, periode_id, professor_id, nota, observacions, modificat) VALUES(?,?,?,?,?,?, datetime('now','localtime'))
               ON CONFLICT(grup_id, alumne_id, periode_id) DO UPDATE SET nota = excluded.nota, observacions = excluded.observacions,
               professor_id = COALESCE(excluded.professor_id, professor_id), modificat = excluded.modificat",
                [$grup, $al['id'], $periode, $prof, $n ?: null, $o ?: null]);
        }
        db()->commit();
        audita('qualificacions', 'grups', $grup, $periodes[$periode] ?? '');
        avis(t('desat'));
        redirigeix('notes', ['grup' => $grup, 'periode' => $periode]);
    }

    $html = selector_grup('notes', $grups, $grup, $periodes ? [select_simple('periode', $periodes, $periode, false)] : []);
    if (!$grup) {
        pagina(t('menu.notes'), $html . '<p class="buit petit">' . h(t('tria_grup')) . '</p>');
        return;
    }
    if (!$periodes) {
        $html .= '<div class="avis info">' . h(t('notes.sense_periodes')) . (pot('escola.edit') ? ' <a href="' . h(url('edita', ['e' => 'periodes', 'f[curs_id]' => $cursId])) . '">' . h(t('notes.crea_periode')) . '</a>' : '') . '</div>';
        pagina(t('menu.notes'), $html, $grups[$grup]);
        return;
    }
    $alumnes = alumnes_grup($grup);
    $fet = array_column(q('SELECT alumne_id, nota, observacions FROM qualificacions WHERE grup_id = ? AND periode_id = ?', [$grup, $periode])->fetchAll(), null, 'alumne_id');
    if (!$alumnes) {
        $html .= '<div class="buit">' . h(t('escola.grup_buit')) . '</div>';
    } else {
        $html .= '<form method="post">' . csrf_camp() . '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . h(t('ent1.alumnes')) . '</th><th>' . h(t('camp.nota'))
            . '</th><th>' . h(t('notes.observacions')) . '</th></tr></thead><tbody>';
        foreach ($alumnes as $al) {
            $html .= '<tr><td>' . h($al['nom']) . '</td><td><input class="nota" name="nota[' . (int)$al['id'] . ']" maxlength="20" value="' . h($fet[$al['id']]['nota'] ?? '') . '"></td>'
                . '<td><textarea rows="1" name="obs[' . (int)$al['id'] . ']">' . h($fet[$al['id']]['observacions'] ?? '') . '</textarea></td></tr>';
        }
        $html .= '</tbody></table></div><p class="peu-taula">' . h(t('notes.ajuda')) . '</p><div class="accions"><button>' . h(t('desa')) . '</button>'
            . '<a class="boto secundari" href="' . h(url('butlleti', ['curs' => $cursId, 'grup' => $grup])) . '">🖨 ' . h(t('menu.butlletins')) . '</a></div></form>';
    }
    pagina(t('menu.notes'), $html, $grups[$grup] . ' · ' . $periodes[$periode]);
}
