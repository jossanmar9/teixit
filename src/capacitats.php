<?php
// Rols i capacitats. Teixit té dos rols fixos: administrador i administratiu.
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const ROLS = ['admin', 'administratiu'];

function totes_capacitats(): array
{
    return [
        'persones.view', 'persones.edit',
        'socis.view', 'socis.edit',
        'escola.view', 'escola.edit', 'escola.notes',
        'agrupacions.view', 'agrupacions.edit',
        'instruments.view', 'instruments.edit',
        'economia.view', 'economia.edit',
        'societat.view', 'societat.edit',
        'copia.manage',
        // Només l'administrador
        'usuaris.manage', 'config.manage', 'auditoria.view', 'copia.restaura',
    ];
}

function rols_per_defecte(): array
{
    $nomesAdmin = ['usuaris.manage', 'config.manage', 'auditoria.view', 'copia.restaura'];
    return [
        'admin' => totes_capacitats(),
        'administratiu' => array_values(array_diff(totes_capacitats(), $nomesAdmin)),
    ];
}

/** Crea els dos rols i n'actualitza les capacitats (també en bases de dades d'una versió anterior) */
function sembra_rols(PDO $pdo): void
{
    $versio = 'rols-5';
    $st = $pdo->prepare('SELECT valor FROM config WHERE clau = ?');
    $st->execute(['versio_rols']);
    if ($st->fetchColumn() === $versio) {
        return;
    }
    $pdo->beginTransaction();
    foreach (rols_per_defecte() as $clau => $caps) {
        $pdo->prepare('INSERT OR IGNORE INTO rols(clau, nom) VALUES(?, NULL)')->execute([$clau]);
        $st = $pdo->prepare('SELECT id FROM rols WHERE clau = ?');
        $st->execute([$clau]);
        $id = (int)$st->fetchColumn();
        $pdo->prepare('DELETE FROM rol_capacitats WHERE rol_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO rol_capacitats(rol_id, capacitat) VALUES(?, ?)');
        foreach ($caps as $c) {
            $ins->execute([$id, $c]);
        }
    }
    $pdo->prepare('INSERT INTO config(clau, valor) VALUES(?, ?) ON CONFLICT(clau) DO UPDATE SET valor = excluded.valor')->execute(['versio_rols', $versio]);
    $pdo->commit();
}

function nom_rol(array $rol): string
{
    return t('rol.' . $rol['clau']);
}
